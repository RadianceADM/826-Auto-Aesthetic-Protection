<?php

namespace App\Models;

use Database\Factories\VehicleSizeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A size the price list is split by (Motorcycle, Sedan, SUV…), edited in
 * /admin → Vehicle sizes.
 */
class VehicleSize extends Model
{
    /** @use HasFactory<VehicleSizeFactory> */
    use HasFactory;

    protected $fillable = ['name', 'examples', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @return HasMany<ServiceRate, $this>
     */
    public function rates(): HasMany
    {
        return $this->hasMany(ServiceRate::class);
    }

    /**
     * @return array{id: int, name: string, examples: string|null}
     */
    public function toSite(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'examples' => $this->examples,
        ];
    }
}
