<?php

namespace App\Support;

use App\Models\Booking;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Payment screenshots from guests: re-drawn with GD into a clean WebP on
 * the private disk (no metadata, nothing but pixels), at most 2400px on
 * the long edge. Nothing about an upload is trusted but its pixels.
 */
class PaymentProof
{
    public const MAX_EDGE = 2400;

    /**
     * Refuse anything bigger than a 40 MP image before GD decodes it.
     */
    public const MAX_PIXELS = 40_000_000;

    public static function store(Booking $booking, UploadedFile $file): string
    {
        $size = @getimagesize($file->getRealPath());

        if (! $size || $size[0] * $size[1] > self::MAX_PIXELS) {
            throw new RuntimeException('Unreadable or oversized image.');
        }

        $source = match ($size[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($file->getRealPath()),
            IMAGETYPE_PNG => @imagecreatefrompng($file->getRealPath()),
            IMAGETYPE_WEBP => @imagecreatefromwebp($file->getRealPath()),
            default => false,
        };

        if (! $source) {
            throw new RuntimeException('Unsupported image.');
        }

        [$width, $height] = [imagesx($source), imagesy($source)];
        $scale = min(1, self::MAX_EDGE / max($width, $height));
        $target = imagecreatetruecolor(max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));
        imagefill($target, 0, 0, imagecolorallocate($target, 255, 255, 255));
        imagecopyresampled($target, $source, 0, 0, 0, 0, imagesx($target), imagesy($target), $width, $height);

        ob_start();
        imagewebp($target, null, 85);
        $webp = (string) ob_get_clean();

        $path = "payment-proofs/{$booking->getKey()}/".Str::random(32).'.webp';
        Storage::disk(Booking::PROOF_DISK)->put($path, $webp);

        return $path;
    }

    public static function delete(?string $path): void
    {
        if ($path) {
            Storage::disk(Booking::PROOF_DISK)->delete($path);
        }
    }
}
