<?php

namespace App\Support;

use GdImage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Turns uploaded photos into a clean WebP master plus resized renditions,
 * in-process with GD (the host doesn't allow shelling out).
 *
 * Master: {dir}/{name}.webp, EXIF-oriented, metadata stripped, at most 2400px
 * on the long edge. Renditions sit next to it as {dir}/{name}-{size}.webp
 * (og is a JPEG for link previews). Nothing is ever upscaled.
 */
class ImagePipeline
{
    public const MASTER_MAX_EDGE = 2400;

    public const MASTER_QUALITY = 85;

    /**
     * Decoding takes about 4 bytes per pixel; refuse anything bigger than a
     * 40 MP camera photo before GD tries (running out of memory is a fatal
     * error that no try/catch can recover from).
     */
    public const MAX_PIXELS = 40_000_000;

    /**
     * Built in this order; thumb is last, so its presence means the set is complete.
     *
     * @var array<string, array{width: int, quality: int, format?: string}>
     */
    public const RENDITIONS = [
        'sm' => ['width' => 480, 'quality' => 80],
        'md' => ['width' => 960, 'quality' => 80],
        'lg' => ['width' => 1600, 'quality' => 82],
        'og' => ['width' => 1200, 'quality' => 82, 'format' => 'jpg'],
        'thumb' => ['width' => 200, 'quality' => 75],
    ];

    private Filesystem $disk;

    public function __construct()
    {
        $this->disk = Storage::disk('public');
    }

    public static function isWebp(string $path): bool
    {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'webp';
    }

    public static function masterPathFor(string $path): string
    {
        return self::sibling($path, pathinfo($path, PATHINFO_FILENAME).'.webp');
    }

    /**
     * The thumb rendition of a processed master, else the path itself.
     */
    public static function thumbPathFor(string $path): string
    {
        return self::isWebp($path) && Storage::disk('public')->exists($thumb = self::renditionPath($path, 'thumb'))
            ? $thumb
            : $path;
    }

    public static function renditionPath(string $master, string $size): string
    {
        $extension = self::RENDITIONS[$size]['format'] ?? 'webp';

        return self::sibling($master, pathinfo($master, PATHINFO_FILENAME)."-{$size}.{$extension}");
    }

    /**
     * Write a clean WebP master for an upload and return its path. The source
     * is left in place (the caller deletes it once the new path is saved).
     * Safe to call again after an interruption: an existing master is reused.
     */
    public function normalize(string $path): string
    {
        $master = self::masterPathFor($path);

        if ($master !== $path && $this->disk->exists($master)) {
            return $master;
        }

        $image = $this->fitLongEdge($this->load($path), self::MASTER_MAX_EDGE);
        $this->write($master, $image, 'webp', self::MASTER_QUALITY);

        return $master;
    }

    public function hasRenditions(string $master): bool
    {
        return $this->disk->exists(self::renditionPath($master, 'thumb'));
    }

    /**
     * Renditions that don't exist yet (e.g. a size added after the photo was processed).
     *
     * @return list<string>
     */
    public function missingRenditions(string $master): array
    {
        return array_values(array_filter(
            array_keys(self::RENDITIONS),
            fn (string $size) => ! $this->disk->exists(self::renditionPath($master, $size)),
        ));
    }

    /**
     * Build the missing renditions (all of them with $force). Returns whether
     * anything was written.
     */
    public function buildRenditions(string $master, bool $force = false): bool
    {
        $sizes = $force ? array_keys(self::RENDITIONS) : $this->missingRenditions($master);

        if ($sizes === []) {
            return false;
        }

        $image = $this->load($master);

        foreach (self::RENDITIONS as $size => $spec) {
            if (in_array($size, $sizes, true)) {
                $this->write(self::renditionPath($master, $size), $this->fitWidth($image, $spec['width']), $spec['format'] ?? 'webp', $spec['quality']);
            }
        }

        return true;
    }

    /**
     * Delete a master and all of its renditions.
     */
    public function delete(string $master): void
    {
        $this->disk->delete([
            $master,
            ...array_map(fn (string $size) => self::renditionPath($master, $size), array_keys(self::RENDITIONS)),
        ]);
    }

    public function exists(string $path): bool
    {
        return $this->disk->exists($path);
    }

    public function deleteFile(string $path): void
    {
        $this->disk->delete($path);
    }

    /**
     * URLs for the public site. Falls back to the master until renditions exist,
     * so a picture is never broken.
     *
     * @return array{src: string, srcset: string|null, thumb: string}
     */
    public function urls(string $master): array
    {
        $url = fn (string $path) => $this->disk->url($path);

        if (! self::isWebp($master) || ! $this->hasRenditions($master)) {
            return ['src' => $url($master), 'srcset' => null, 'thumb' => $url($master)];
        }

        return [
            'src' => $url(self::renditionPath($master, 'md')),
            'srcset' => collect(['sm', 'md', 'lg'])
                ->map(fn (string $size) => $url(self::renditionPath($master, $size)).' '.self::RENDITIONS[$size]['width'].'w')
                ->implode(', '),
            'thumb' => $url(self::renditionPath($master, 'thumb')),
        ];
    }

    /**
     * A single rendition URL (e.g. lg for the hero, og for link previews),
     * or the master while renditions are missing.
     */
    public function url(string $master, string $size): string
    {
        $path = self::isWebp($master) && $this->hasRenditions($master) ? self::renditionPath($master, $size) : $master;

        return $this->disk->url($path);
    }

    private static function sibling(string $path, string $filename): string
    {
        $directory = pathinfo($path, PATHINFO_DIRNAME);

        return $directory === '.' || $directory === '' ? $filename : "{$directory}/{$filename}";
    }

    private function load(string $path): GdImage
    {
        $file = $this->disk->path($path);
        $size = @getimagesize($file);

        if ($size === false) {
            throw new RuntimeException("Not a readable image: {$path}");
        }

        if ($size[0] * $size[1] > self::MAX_PIXELS) {
            throw new RuntimeException("Image is too large to process ({$size[0]}×{$size[1]}): {$path}");
        }

        $image = @imagecreatefromstring((string) @file_get_contents($file));

        if (! $image instanceof GdImage) {
            throw new RuntimeException("Not a readable image: {$path}");
        }

        if (! imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);

        return $this->orient($image, $file);
    }

    /**
     * Apply the camera's EXIF orientation, since the re-encoded file has no EXIF.
     */
    private function orient(GdImage $image, string $file): GdImage
    {
        $orientation = function_exists('exif_read_data') && @exif_imagetype($file) === IMAGETYPE_JPEG
            ? (int) (@exif_read_data($file)['Orientation'] ?? 1)
            : 1;

        $rotate = fn (GdImage $img, int $degrees) => imagerotate($img, $degrees, 0) ?: $img;

        switch ($orientation) {
            case 2:
                imageflip($image, IMG_FLIP_HORIZONTAL);
                break;
            case 3:
                $image = $rotate($image, 180);
                break;
            case 4:
                imageflip($image, IMG_FLIP_VERTICAL);
                break;
            case 5:
                $image = $rotate($image, -90);
                imageflip($image, IMG_FLIP_HORIZONTAL);
                break;
            case 6:
                $image = $rotate($image, -90);
                break;
            case 7:
                $image = $rotate($image, 90);
                imageflip($image, IMG_FLIP_HORIZONTAL);
                break;
            case 8:
                $image = $rotate($image, 90);
                break;
        }

        return $image;
    }

    private function fitLongEdge(GdImage $image, int $maxEdge): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $scale = $maxEdge / max($width, $height);

        return $scale >= 1 ? $image : $this->resize($image, (int) round($width * $scale), (int) round($height * $scale));
    }

    private function fitWidth(GdImage $image, int $maxWidth): GdImage
    {
        $width = imagesx($image);

        if ($width <= $maxWidth) {
            return $image;
        }

        return $this->resize($image, $maxWidth, max(1, (int) round(imagesy($image) * $maxWidth / $width)));
    }

    private function resize(GdImage $image, int $width, int $height): GdImage
    {
        $resized = imagecreatetruecolor($width, $height);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $width, $height, imagesx($image), imagesy($image));

        return $resized;
    }

    private function write(string $path, GdImage $image, string $format, int $quality): void
    {
        if ($format === 'jpg') {
            // JPEG has no transparency: flatten onto white
            $flat = imagecreatetruecolor(imagesx($image), imagesy($image));
            imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
            imagealphablending($flat, true);
            imagecopy($flat, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
            $image = $flat;
        }

        ob_start();
        $ok = $format === 'jpg' ? imagejpeg($image, null, $quality) : imagewebp($image, null, $quality);
        $bytes = (string) ob_get_clean();

        if (! $ok || $bytes === '') {
            throw new RuntimeException("Could not encode {$path}");
        }

        $this->disk->put($path, $bytes);
    }
}
