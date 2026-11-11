<?php

namespace App\Models;

use App\Models\Concerns\HasImageRenditions;
use App\Support\ImagePipeline;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Something a customer can book (Graphene coating, Full body PPF, Nano
 * ceramic tint…), with a price and duration per vehicle size, offered at
 * the branches it's attached to.
 */
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory, HasImageRenditions;

    protected $fillable = [
        'service_category_id',
        'name',
        'slug',
        'summary',
        'description',
        'inclusions',
        'image',
        'sort_order',
        'is_active',
        'home_service_ok',
    ];

    protected function casts(): array
    {
        return [
            'inclusions' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'home_service_ok' => 'boolean',
        ];
    }

    public function imageAttributes(): array
    {
        return ['image'];
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->whereHas('category', fn (Builder $category) => $category->where('is_active', true));
    }

    /**
     * @return BelongsTo<ServiceCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    /**
     * @return HasMany<ServiceRate, $this>
     */
    public function rates(): HasMany
    {
        return $this->hasMany(ServiceRate::class);
    }

    /**
     * @return BelongsToMany<Branch, $this>
     */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class);
    }

    /**
     * The rate for a size, if one is set up.
     */
    public function rateFor(int $vehicleSizeId): ?ServiceRate
    {
        $rates = $this->relationLoaded('rates') ? $this->rates : $this->rates()->get();

        return $rates->firstWhere('vehicle_size_id', $vehicleSizeId);
    }

    /**
     * @return list<string>
     */
    public function inclusionList(): array
    {
        return collect(is_array($this->inclusions) ? $this->inclusions : [])
            ->map(fn ($item) => is_array($item) ? ($item['item'] ?? null) : $item)
            ->filter(fn ($item) => is_string($item) && filled($item))
            ->values()
            ->all();
    }

    /**
     * Shape used by the React site (window.APP826.catalog). Only rates that
     * can be booked are listed, and only for active sizes.
     *
     * @param  list<int>  $activeSizeIds
     * @return array<string, mixed>
     */
    public function toSite(array $activeSizeIds): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'summary' => $this->summary,
            'description' => $this->description,
            'inclusions' => $this->inclusionList(),
            'image' => $this->image ? app(ImagePipeline::class)->urls($this->image) : null,
            'homeServiceOk' => $this->home_service_ok,
            'rates' => $this->rates
                ->filter(fn (ServiceRate $rate) => $rate->isBookable() && in_array($rate->vehicle_size_id, $activeSizeIds, true))
                ->map->toSite()
                ->values()
                ->all(),
        ];
    }
}
