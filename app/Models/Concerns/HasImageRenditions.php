<?php

namespace App\Models\Concerns;

use App\Support\ImagePipeline;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Keeps a model's photos as clean WebP masters with renditions: new uploads
 * are converted on save, removed photos and their renditions are deleted.
 *
 * @mixin Model
 */
trait HasImageRenditions
{
    /**
     * Attributes holding a disk path, or a list of paths.
     *
     * @return list<string>
     */
    abstract public function imageAttributes(): array;

    /**
     * Raw uploads replaced by their WebP master during this save.
     *
     * @var list<string>
     */
    private array $replacedUploads = [];

    public static function bootHasImageRenditions(): void
    {
        static::saving(fn (Model $model) => $model->convertNewImages());
        static::saved(fn (Model $model) => $model->deleteRemovedImages());
        static::deleted(fn (Model $model) => $model->deleteImages($model->imagePaths()));
    }

    /**
     * Every image path on the model, from the current or the original values.
     *
     * @return list<string>
     */
    public function imagePaths(bool $original = false): array
    {
        return collect($this->imageAttributes())
            ->flatMap(fn (string $attribute) => (array) ($original ? $this->getOriginal($attribute) : $this->getAttribute($attribute)))
            ->filter(fn ($path) => is_string($path) && $path !== '')
            ->unique()
            ->values()
            ->all();
    }

    protected function convertNewImages(): void
    {
        $pipeline = app(ImagePipeline::class);
        $existing = $this->imagePaths(original: true);

        $convert = function (mixed $path) use ($pipeline, $existing) {
            if (! is_string($path) || $path === '' || in_array($path, $existing, true)) {
                return $path;
            }

            try {
                $master = $pipeline->normalize($path);
                $pipeline->buildRenditions($master);
            } catch (Throwable $e) {
                // Keep the upload as-is rather than failing the save; the
                // site falls back to it.
                Log::warning("Could not process image {$path}: {$e->getMessage()}");

                return $path;
            }

            if ($master !== $path) {
                $this->replacedUploads[] = $path;
            }

            return $master;
        };

        foreach ($this->imageAttributes() as $attribute) {
            $value = $this->getAttribute($attribute);

            $this->setAttribute($attribute, is_array($value)
                ? array_values(array_map($convert, $value))
                : $convert($value));
        }
    }

    protected function deleteRemovedImages(): void
    {
        $pipeline = app(ImagePipeline::class);
        $current = $this->imagePaths();

        $this->deleteImages(array_diff($this->imagePaths(original: true), $current));

        foreach (array_diff($this->replacedUploads, $current) as $upload) {
            $pipeline->deleteFile($upload);
        }

        $this->replacedUploads = [];
    }

    /**
     * @param  array<string>  $paths
     */
    protected function deleteImages(array $paths): void
    {
        $pipeline = app(ImagePipeline::class);

        foreach ($paths as $path) {
            $pipeline->delete($path);
        }
    }
}
