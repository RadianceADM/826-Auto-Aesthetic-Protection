<?php

namespace App\Models;

use App\Models\Concerns\HasPaymentAccounts;
use App\Support\GoogleSignIn;
use App\Support\Schedule\Availability;
use Carbon\CarbonImmutable;
use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One of 826's shops. Customers reserve a drop-off time here once the
 * branch is open and filled in (see missing()). Its bays are how many cars
 * it works on at once; its weekly hours say when cars can be dropped off
 * and when work runs.
 */
class Branch extends Model
{
    /** @use HasFactory<BranchFactory> */
    use HasFactory, HasPaymentAccounts;

    public const STATUSES = [
        'draft' => 'Draft (hidden)',
        'coming_soon' => 'Coming soon (listed, no booking)',
        'open' => 'Open for booking',
    ];

    public const WEEKDAYS = [
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
        7 => 'Sunday',
    ];

    public const STEPS = [15 => 'Every 15 minutes', 30 => 'Every 30 minutes', 60 => 'Every hour', 120 => 'Every 2 hours'];

    public const MAX_BAYS = 50;

    protected $fillable = [
        'name',
        'slug',
        'status',
        'sort_order',
        'city',
        'address',
        'map_url',
        'map_lat',
        'map_lng',
        'phone',
        'messenger_url',
        'facebook_url',
        'alert_email',
        'bays',
        'weekly_hours',
        'step_minutes',
        'lead_hours',
        'months_ahead',
        'multiday_dropoff_by',
        'offers_home_service',
        'home_service_areas',
        'gcash_enabled',
        'gcash_qr',
        'gcash_name',
        'gcash_number',
        'maya_enabled',
        'maya_qr',
        'maya_name',
        'maya_number',
    ];

    protected $attributes = [
        'status' => 'draft',
        'bays' => 0,
        'step_minutes' => 60,
        'lead_hours' => 2,
        'months_ahead' => 3,
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'map_lat' => 'float',
            'map_lng' => 'float',
            'bays' => 'integer',
            'weekly_hours' => 'array',
            'step_minutes' => 'integer',
            'lead_hours' => 'integer',
            'months_ahead' => 'integer',
            'offers_home_service' => 'boolean',
            'home_service_areas' => 'array',
            'gcash_enabled' => 'boolean',
            'maya_enabled' => 'boolean',
        ];
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * On the site: open and coming-soon branches (drafts are hidden).
     */
    public function scopeListed(Builder $query): void
    {
        $query->whereIn('status', ['open', 'coming_soon']);
    }

    /**
     * The branches a team member may work with: all of them for owners.
     */
    public function scopeVisibleTo(Builder $query, ?User $user): void
    {
        if (! $user?->isOwner()) {
            $query->whereIn('id', $user?->branchIds() ?? []);
        }
    }

    /**
     * @return BelongsToMany<Service, $this>
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * @return HasMany<BranchBlock, $this>
     */
    public function blocks(): HasMany
    {
        return $this->hasMany(BranchBlock::class);
    }

    /**
     * Each weekday's hours, checked: null when closed. Times are "HH:MM";
     * the last drop-off is never before opening or after closing.
     *
     * @return array<int, array{open: string, close: string, last_dropoff: string}|null>
     */
    public function weeklyHours(): array
    {
        $saved = is_array($this->weekly_hours) ? $this->weekly_hours : [];
        $hours = [];

        foreach (array_keys(self::WEEKDAYS) as $day) {
            $row = $saved[$day] ?? $saved[(string) $day] ?? null;
            $open = self::clock($row['open'] ?? null);
            $close = self::clock($row['close'] ?? null);

            if (! is_array($row) || ($row['closed'] ?? false) || ! $open || ! $close || $open >= $close) {
                $hours[$day] = null;

                continue;
            }

            $last = self::clock($row['last_dropoff'] ?? null) ?? $close;

            $hours[$day] = [
                'open' => $open,
                'close' => $close,
                'last_dropoff' => max($open, min($last, $close)),
            ];
        }

        return $hours;
    }

    /**
     * The hours on a date, as times on that date.
     *
     * @return array{open: CarbonImmutable, close: CarbonImmutable, lastDropoff: CarbonImmutable}|null
     */
    public function hoursOn(CarbonImmutable $date): ?array
    {
        $hours = $this->weeklyHours()[$date->isoWeekday()] ?? null;

        if ($hours === null) {
            return null;
        }

        $day = $date->startOfDay();

        return [
            'open' => $day->setTimeFromTimeString($hours['open']),
            'close' => $day->setTimeFromTimeString($hours['close']),
            'lastDropoff' => $day->setTimeFromTimeString($hours['last_dropoff']),
        ];
    }

    public function hasHours(): bool
    {
        return array_filter($this->weeklyHours()) !== [];
    }

    /**
     * "08:00", "8:00", "08:00:00" → "08:00"; anything else → null.
     */
    public static function clock(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', trim($value), $match)) {
            return null;
        }

        [$hour, $minute] = [(int) $match[1], (int) $match[2]];

        return $hour < 24 && $minute < 60 ? sprintf('%02d:%02d', $hour, $minute) : null;
    }

    public function multidayDropoffBy(): ?string
    {
        return self::clock($this->multiday_dropoff_by);
    }

    public function stepMinutes(): int
    {
        return array_key_exists((int) $this->step_minutes, self::STEPS) ? (int) $this->step_minutes : 60;
    }

    public function leadHours(): int
    {
        return max(0, min(24 * 14, (int) $this->lead_hours));
    }

    public function monthsAhead(): int
    {
        return max(1, min(12, (int) ($this->months_ahead ?: 3)));
    }

    /**
     * The services on offer here that can be booked for at least one size.
     *
     * @return Collection<int, Service>
     */
    public function bookableServices(): Collection
    {
        return $this->services()
            ->active()
            ->whereHas('rates', fn (Builder $rates) => $rates
                ->whereNotNull('price')
                ->where(fn (Builder $duration) => $duration->where('duration_days', '>', 0)->orWhere('duration_minutes', '>', 0))
                ->whereHas('vehicleSize', fn (Builder $size) => $size->where('is_active', true)))
            ->get();
    }

    /**
     * What still stops customers from reserving here online. Empty when
     * the branch is ready (it still needs to be set to Open).
     *
     * @return list<string>
     */
    public function missing(): array
    {
        $missing = [];

        if ((int) $this->bays <= 0) {
            $missing[] = 'Set how many cars the branch works on at once (bays).';
        }

        if (! $this->hasHours()) {
            $missing[] = 'Set the opening hours for at least one day.';
        }

        if ($this->bookableServices()->isEmpty()) {
            $missing[] = 'Offer at least one service with a price and a duration.';
        }

        if (PaymentSetting::instance()->feeEnabled() && $this->paymentMethods() === []) {
            $missing[] = 'The reservation fee is on: add a GCash or Maya account here or in Payments.';
        }

        if (! GoogleSignIn::enabled() && ! config('app.demo') && ! app()->isLocal()) {
            $missing[] = 'Set up Google sign-in (Settings → Google sign-in): customers sign in to reserve.';
        }

        return $missing;
    }

    public function isOnsiteBookable(): bool
    {
        return $this->status === 'open' && $this->missing() === [];
    }

    public function takesHomeRequests(): bool
    {
        return $this->status === 'open' && $this->offers_home_service;
    }

    /**
     * Where customers pay the reservation fee: the branch's own accounts
     * when it has any, else the main ones (never a mix).
     *
     * @return list<array{key: string, label: string, qr: string|null, accountName: string|null, accountNumber: string|null}>
     */
    public function paymentMethods(): array
    {
        $own = $this->methods();

        return $own !== [] ? $own : PaymentSetting::instance()->methods();
    }

    /**
     * @return list<string>
     */
    public function homeServiceAreas(): array
    {
        return collect(is_array($this->home_service_areas) ? $this->home_service_areas : [])
            ->filter(fn ($area) => is_string($area) && filled($area))
            ->map(fn (string $area) => trim($area))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * "0948 804 2284" → "+639488042284", for tel: links.
     */
    public function phoneHref(): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $this->phone);

        return match (true) {
            $digits === '' => null,
            str_starts_with($digits, '63') => "+{$digits}",
            str_starts_with($digits, '0') => '+63'.substr($digits, 1),
            default => $digits,
        };
    }

    /**
     * Directions from the pin, or the owner's own Google Maps link.
     *
     * @return array{google: string|null, waze: string|null, embed: string|null}|null
     */
    public function map(): ?array
    {
        $hasPin = $this->map_lat !== null && $this->map_lng !== null;

        if (! $hasPin && blank($this->map_url)) {
            return null;
        }

        $pin = $hasPin ? "{$this->map_lat},{$this->map_lng}" : null;

        return [
            'google' => $this->map_url ?: ($pin ? 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($pin) : null),
            'waze' => $pin ? 'https://waze.com/ul?ll='.rawurlencode($pin).'&navigate=yes' : null,
            'embed' => $pin ? 'https://www.google.com/maps?q='.rawurlencode($pin).'&z=15&output=embed' : null,
        ];
    }

    /**
     * Shape used by the React site (window.APP826.branches).
     *
     * @return array<string, mixed>
     */
    public function toSite(): array
    {
        $hours = $this->weeklyHours();
        $serviceIds = $this->status === 'open' ? $this->bookableServices()->modelKeys() : [];

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'status' => $this->status,
            'city' => $this->city,
            'address' => $this->address,
            'phone' => $this->phone,
            'phoneHref' => $this->phoneHref(),
            'messenger' => $this->messenger_url,
            'facebook' => $this->facebook_url,
            'map' => $this->map(),
            'hours' => collect(self::WEEKDAYS)->map(fn (string $label, int $day) => [
                'day' => $day,
                'label' => $label,
                'open' => $hours[$day]['open'] ?? null,
                'close' => $hours[$day]['close'] ?? null,
                'lastDropoff' => $hours[$day]['last_dropoff'] ?? null,
            ])->values()->all(),
            'multidayDropoffBy' => $this->multidayDropoffBy(),
            // The last day it takes bookings and home-service requests for
            'lastDay' => Availability::lastDay($this)->toDateString(),
            'bookable' => $this->isOnsiteBookable(),
            'serviceIds' => array_values(array_map('intval', $serviceIds)),
            'homeService' => [
                'offered' => $this->takesHomeRequests(),
                'areas' => $this->homeServiceAreas(),
            ],
        ];
    }
}
