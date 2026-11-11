<?php

namespace App\Models;

use App\Models\Concerns\HasImageRenditions;
use App\Support\ImagePipeline;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * A picture of 826's work for the gallery. Featured ones also show on the
 * Home page. Categories with no photos stay hidden on the site.
 */
class Photo extends Model
{
    use HasFactory, HasImageRenditions;

    /**
     * In the order the site shows them.
     */
    public const CATEGORIES = [
        'coating' => 'Coating',
        'ppf' => 'Paint protection film',
        'tint' => 'Window tint',
        'detailing' => 'Detailing',
        'shop' => 'The shops',
    ];

    protected $fillable = [
        'image',
        'caption',
        'category',
        'is_featured',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    public function imageAttributes(): array
    {
        return ['image'];
    }

    protected static function booted(): void
    {
        // Runs after HasImageRenditions has converted the upload, so these
        // are the master's dimensions (the gallery reserves space with them)
        static::saving(function (Photo $photo) {
            if ($photo->isDirty('image') || ! $photo->width) {
                $size = $photo->image ? @getimagesize(Storage::disk('public')->path($photo->image)) : false;
                [$photo->width, $photo->height] = $size ? [$size[0], $size[1]] : [null, null];
            }
        });
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Shape used by the React site (window.APP826.photos).
     */
    public function toSite(): array
    {
        return [
            'id' => $this->id,
            'caption' => $this->caption,
            'category' => $this->category,
            'width' => $this->width,
            'height' => $this->height,
            'isFeatured' => $this->is_featured,
            ...app(ImagePipeline::class)->urls($this->image),
            'full' => app(ImagePipeline::class)->url($this->image, 'lg'),
        ];
    }
}
