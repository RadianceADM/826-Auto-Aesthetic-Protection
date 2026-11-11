<?php

namespace App\Filament\Support;

use App\Support\ImagePipeline;
use Filament\Forms\Components\FileUpload;

/**
 * Photo upload field shared by every resource: raster images only (no SVG),
 * shrunk in the browser to 2400px on the long edge (camera photos upload in
 * seconds), stored on the public disk under a random name. The model
 * converts it to WebP with renditions on save (HasImageRenditions).
 */
class ImageUpload
{
    public const TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public static function make(string $name, string $directory): FileUpload
    {
        return FileUpload::make($name)
            ->image()
            ->disk('public')
            ->directory($directory)
            ->visibility('public')
            ->acceptedFileTypes(self::TYPES)
            ->maxSize(20480)
            ->automaticallyResizeImagesMode('contain')
            ->automaticallyResizeImagesToWidth((string) ImagePipeline::MASTER_MAX_EDGE)
            ->automaticallyResizeImagesToHeight((string) ImagePipeline::MASTER_MAX_EDGE);
    }
}
