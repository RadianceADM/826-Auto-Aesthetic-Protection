<?php

namespace App\Models;

use App\Support\Schedule\Length;
use Database\Factories\ServiceRateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The price and duration of one service for one vehicle size. A job takes
 * so many hours (duration_minutes) or so many open days (duration_days),
 * never both. Only a rate with a price and a duration can be booked.
 */
class ServiceRate extends Model
{
    /** @use HasFactory<ServiceRateFactory> */
    use HasFactory;

    protected $fillable = ['service_id', 'vehicle_size_id', 'price', 'duration_minutes', 'duration_days'];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'duration_minutes' => 'integer',
            'duration_days' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return BelongsTo<VehicleSize, $this>
     */
    public function vehicleSize(): BelongsTo
    {
        return $this->belongsTo(VehicleSize::class);
    }

    public function isBookable(): bool
    {
        return $this->price !== null && ($this->duration_days > 0 xor $this->duration_minutes > 0);
    }

    public function length(): Length
    {
        return $this->duration_days > 0
            ? new Length(days: (int) $this->duration_days, minutes: 0)
            : new Length(days: 0, minutes: (int) $this->duration_minutes);
    }

    /**
     * @return array{sizeId: int, price: int|null, days: int, minutes: int, duration: string}
     */
    public function toSite(): array
    {
        return [
            'sizeId' => $this->vehicle_size_id,
            'price' => $this->price,
            'days' => (int) $this->duration_days,
            'minutes' => (int) $this->duration_minutes,
            'duration' => $this->length()->label(),
        ];
    }
}
