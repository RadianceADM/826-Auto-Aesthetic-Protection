<?php

namespace App\Models;

use App\Support\Schedule\Length;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One service on a booking, as it was priced and timed when the booking
 * was made.
 */
class BookingItem extends Model
{
    protected $fillable = ['booking_id', 'service_id', 'service_name', 'category_name', 'price', 'duration_minutes', 'duration_days', 'sort_order'];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'duration_minutes' => 'integer',
            'duration_days' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function length(): Length
    {
        return new Length((int) $this->duration_days, (int) $this->duration_minutes);
    }

    /**
     * @return array{name: string, category: string|null, price: int|null, duration: string}
     */
    public function toCustomer(): array
    {
        return [
            'name' => $this->service_name,
            'category' => $this->category_name,
            'price' => $this->price,
            'duration' => $this->length()->label(),
        ];
    }
}
