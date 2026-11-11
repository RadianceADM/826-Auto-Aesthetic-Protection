<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * The booking rules from /admin → Booking rules: when customers can still
 * cancel or move a reservation on their own, how many unpaid holds and
 * upcoming bookings one customer can have, and the owner's terms. One row,
 * cached until the next save.
 */
class BookingSetting extends Model
{
    private const CACHE_KEY = 'app826.booking-settings';

    protected $fillable = [
        'cancel_cutoff_hours',
        'reschedule_enabled',
        'reschedule_cutoff_hours',
        'max_reschedules',
        'max_unpaid_holds_per_guest',
        'max_upcoming_per_guest',
        'policy_text',
    ];

    protected function casts(): array
    {
        return [
            'cancel_cutoff_hours' => 'integer',
            'reschedule_enabled' => 'boolean',
            'reschedule_cutoff_hours' => 'integer',
            'max_reschedules' => 'integer',
            'max_unpaid_holds_per_guest' => 'integer',
            'max_upcoming_per_guest' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    public static function instance(): self
    {
        return static::query()->oldest('id')->first() ?? static::create()->refresh();
    }

    public static function current(): self
    {
        $attributes = Cache::rememberForever(self::CACHE_KEY, fn () => static::instance()->getAttributes());

        return (new static)->newFromBuilder($attributes);
    }

    /**
     * @return array<string, mixed>
     */
    public function toSite(): array
    {
        return [
            'cancelCutoffHours' => $this->cancel_cutoff_hours,
            'rescheduleEnabled' => $this->reschedule_enabled,
            'rescheduleCutoffHours' => $this->reschedule_cutoff_hours,
            'maxReschedules' => $this->max_reschedules,
            'policy' => $this->policy_text,
        ];
    }
}
