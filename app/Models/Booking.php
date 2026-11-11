<?php

namespace App\Models;

use App\Support\Schedule\Length;
use Carbon\CarbonImmutable;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A reservation or a home-service request.
 *
 * Onsite reservations are made by customers on the site: the car takes one
 * of the branch's bays from starts_at (drop-off) until ends_at (ready by).
 * With the reservation fee on, the bay is held until hold_expires_at for the
 * customer to send a payment screenshot; staff check it and the booking is
 * confirmed. Home-service requests take no bay: staff schedule them.
 *
 * @property CarbonImmutable|null $starts_at
 * @property CarbonImmutable|null $ends_at
 * @property CarbonImmutable|null $hold_expires_at
 */
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    public const KINDS = [
        'onsite' => 'At the branch',
        'home' => 'Home service',
    ];

    public const STATUSES = [
        'requested' => 'Home service request',
        'awaiting_payment' => 'Waiting for the reservation fee',
        'payment_review' => 'Screenshot to check',
        'confirmed' => 'Reserved',
        'checked_in' => 'In the shop',
        'ready' => 'Ready for pickup',
        'completed' => 'Done',
        'expired' => 'Hold expired',
        'cancelled' => 'Cancelled',
        'declined' => 'Declined',
        'no_show' => 'No-show',
    ];

    /**
     * Statuses that keep a bay for an onsite booking. An unpaid hold
     * (awaiting_payment) keeps it only until hold_expires_at.
     */
    public const HOLDS_BAY = ['payment_review', 'confirmed', 'checked_in', 'ready'];

    /**
     * Not finished, cancelled or lapsed: still on the team's list.
     */
    public const OPEN = ['requested', 'awaiting_payment', 'payment_review', 'confirmed', 'checked_in', 'ready'];

    public const WINDOWS = [
        'morning' => 'Morning',
        'afternoon' => 'Afternoon',
        'any' => 'Any time',
    ];

    public const SOURCES = [
        'site' => 'The site',
        'walk_in' => 'Walk-in',
        'phone' => 'Call or message',
        'admin' => 'Added in the admin',
        'demo' => 'Sample data',
    ];

    /**
     * Payment screenshots stay off the public disk: only the customer
     * (signed in or with the booking's key) and the branch's team can open them.
     */
    public const PROOF_DISK = 'local';

    /**
     * Letters and digits that can't be misread over the phone.
     */
    private const REFERENCE_ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    protected $fillable = [
        'kind',
        'source',
        'status',
        'branch_id',
        'guest_id',
        'starts_at',
        'ends_at',
        'preferred_date',
        'preferred_window',
        'vehicle_size_id',
        'vehicle_size_name',
        'vehicle_model',
        'plate_number',
        'name',
        'phone',
        'email',
        'address',
        'area',
        'landmark',
        'has_water',
        'has_power',
        'has_shade',
        'message',
        'admin_note',
        'total_price',
        'total_days',
        'total_minutes',
        'amount_due',
        'amount_kind',
        'hold_expires_at',
        'payment_method',
        'payment_proof',
        'payment_submitted_at',
        'payment_rejected_reason',
        'payment_note',
        'paid_at',
        'confirmed_at',
        'checked_in_at',
        'ready_at',
        'completed_at',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
        'reschedule_count',
        'reminded_at',
        'read_at',
    ];

    /**
     * The private link's key opens the booking without signing in, so it is
     * never sent along with the booking itself.
     *
     * @var list<string>
     */
    protected $hidden = ['access_key', 'payment_proof'];

    protected $attributes = [
        'kind' => 'onsite',
        'source' => 'site',
        'total_days' => 0,
        'total_minutes' => 0,
        'reschedule_count' => 0,
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'preferred_date' => 'immutable_date',
            'has_water' => 'boolean',
            'has_power' => 'boolean',
            'has_shade' => 'boolean',
            'total_price' => 'integer',
            'total_days' => 'integer',
            'total_minutes' => 'integer',
            'amount_due' => 'integer',
            'hold_expires_at' => 'immutable_datetime',
            'payment_submitted_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
            'confirmed_at' => 'immutable_datetime',
            'checked_in_at' => 'immutable_datetime',
            'ready_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'reminded_at' => 'immutable_datetime',
            'read_at' => 'immutable_datetime',
            'reschedule_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Booking $booking) {
            $booking->access_key ??= Str::random(40);
            $booking->reference ??= self::newReference();
        });

        static::saving(function (Booking $booking) {
            if ($booking->plate_number !== null) {
                $booking->plate_number = self::normalizePlate($booking->plate_number);
            }

            if ($booking->amount_due !== null && $booking->total_price !== null) {
                $booking->amount_kind = $booking->amount_due < $booking->total_price ? 'fee' : 'full';
            }

            if ($booking->isDirty('status')) {
                $now = now()->toImmutable();

                match ($booking->status) {
                    'confirmed' => $booking->confirmed_at ??= $now,
                    'checked_in' => $booking->checked_in_at ??= $now,
                    'ready' => $booking->ready_at ??= $now,
                    'completed' => $booking->completed_at ??= $now,
                    'cancelled', 'declined', 'expired' => $booking->cancelled_at ??= $now,
                    default => null,
                };
            }
        });
    }

    public static function newReference(): string
    {
        do {
            $code = '';

            for ($i = 0; $i < 5; $i++) {
                $code .= self::REFERENCE_ALPHABET[random_int(0, strlen(self::REFERENCE_ALPHABET) - 1)];
            }

            $reference = "826-{$code}";
        } while (static::query()->where('reference', $reference)->exists());

        return $reference;
    }

    /**
     * "abc 1234" → "ABC1234".
     */
    public static function normalizePlate(string $plate): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $plate));
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Guest, $this>
     */
    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    /**
     * @return BelongsTo<VehicleSize, $this>
     */
    public function vehicleSize(): BelongsTo
    {
        return $this->belongsTo(VehicleSize::class);
    }

    /**
     * @return HasMany<BookingItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(BookingItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Onsite bookings that keep a bay right now: confirmed or in progress,
     * a screenshot waiting to be checked, or an unpaid hold that hasn't
     * lapsed (checked against the clock, so it frees up even without cron).
     */
    public function scopeHoldingABay(Builder $query): void
    {
        $query->where('kind', 'onsite')
            ->whereNotNull('starts_at')
            ->whereNotNull('ends_at')
            ->where(fn (Builder $status) => $status
                ->whereIn('status', self::HOLDS_BAY)
                ->orWhere(fn (Builder $hold) => $hold
                    ->where('status', 'awaiting_payment')
                    ->where('hold_expires_at', '>', now())));
    }

    public function scopeTouching(Builder $query, CarbonImmutable $from, CarbonImmutable $to): void
    {
        $query->where('starts_at', '<', $to)->where('ends_at', '>', $from);
    }

    public function scopeUnread(Builder $query): void
    {
        $query->whereNull('read_at');
    }

    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', self::OPEN);
    }

    /**
     * Something for the team to do: a screenshot to check, or a home-service
     * request to schedule.
     */
    public function scopeNeedsAction(Builder $query): void
    {
        $query->whereIn('status', ['payment_review', 'requested']);
    }

    /**
     * Unpaid holds whose time is up, for bookings:expire-holds.
     */
    public function scopeLapsedHolds(Builder $query): void
    {
        $query->where('status', 'awaiting_payment')
            ->whereNotNull('hold_expires_at')
            ->where('hold_expires_at', '<=', now());
    }

    /**
     * Only the branches the team member works at (all of them for owners).
     */
    public function scopeVisibleTo(Builder $query, ?User $user): void
    {
        if (! $user?->isOwner()) {
            $query->whereIn('branch_id', $user?->branchIds() ?? []);
        }
    }

    public function isOnsite(): bool
    {
        return $this->kind === 'onsite';
    }

    public function isHome(): bool
    {
        return $this->kind === 'home';
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true) && $this->stage() !== 'expired';
    }

    /**
     * Where the booking is, as the customer sees it. An unpaid hold whose
     * time is up reads as expired before the command flips its status.
     */
    public function stage(): string
    {
        if ($this->status === 'awaiting_payment' && $this->hold_expires_at !== null && $this->hold_expires_at->lessThanOrEqualTo(now())) {
            return 'expired';
        }

        return $this->status;
    }

    public function isAwaitingPayment(): bool
    {
        return $this->stage() === 'awaiting_payment';
    }

    public function holdsABay(): bool
    {
        return $this->isOnsite()
            && $this->starts_at !== null
            && (in_array($this->status, self::HOLDS_BAY, true) || $this->stage() === 'awaiting_payment');
    }

    public function length(): Length
    {
        return new Length((int) $this->total_days, (int) $this->total_minutes);
    }

    /**
     * What's left to pay at the branch once the reservation fee is in.
     * Not shown until 826 says whether the fee counts toward the price.
     */
    public function balance(): ?int
    {
        return null;
    }

    /**
     * The customer's page, site-relative, with the private key so it opens
     * without signing in ("/bookings/12?key=…").
     */
    public function customerPath(): string
    {
        return "/bookings/{$this->getKey()}?key={$this->access_key}";
    }

    public function customerUrl(): string
    {
        return url($this->customerPath());
    }

    /**
     * Whether a request may open this booking: its signed-in customer, or the key.
     */
    public function isOpenedBy(?Guest $guest, ?string $key): bool
    {
        if ($guest && $this->guest_id !== null && $this->guest_id === $guest->getKey()) {
            return true;
        }

        return filled($key) && filled($this->access_key) && hash_equals($this->access_key, (string) $key);
    }

    /**
     * "Mon, Nov 16, 9:00 AM → ready Wed, Nov 18, 6:00 PM", or the home
     * request's preferred day.
     */
    public function whenLabel(): string
    {
        if ($this->starts_at && $this->ends_at) {
            $ready = $this->ends_at->isSameDay($this->starts_at)
                ? $this->ends_at->format('g:i A')
                : $this->ends_at->format('D, M j, g:i A');

            return $this->starts_at->format('D, M j, Y, g:i A')." → ready {$ready}";
        }

        if ($this->preferred_date) {
            return 'Asked for '.$this->preferred_date->format('D, M j, Y').($this->preferred_window ? ' ('.strtolower(self::WINDOWS[$this->preferred_window] ?? $this->preferred_window).')' : '');
        }

        return '—';
    }

    /**
     * "Graphene coating, Nano ceramic tint".
     */
    public function servicesLabel(): string
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();

        return $items->pluck('service_name')->implode(', ');
    }

    public function vehicleLabel(): string
    {
        return collect([$this->vehicle_model, $this->vehicle_size_name ? "({$this->vehicle_size_name})" : null, $this->plate_number])
            ->filter()
            ->implode(' ');
    }

    /**
     * Whether the customer may still cancel it themselves.
     */
    public function canBeCancelledByCustomer(): bool
    {
        if (! $this->isOpen() || in_array($this->status, ['checked_in', 'ready'], true)) {
            return false;
        }

        if ($this->starts_at === null || in_array($this->stage(), ['requested', 'awaiting_payment'], true)) {
            return true;
        }

        return now()->lessThanOrEqualTo($this->starts_at->subHours(BookingSetting::current()->cancel_cutoff_hours));
    }

    /**
     * Whether the customer may move it to another time themselves: a paid
     * or confirmed onsite reservation, before the cutoff, within the limit.
     */
    public function canBeRescheduledByCustomer(): bool
    {
        $settings = BookingSetting::current();

        return $this->isOnsite()
            && in_array($this->status, ['confirmed', 'payment_review'], true)
            && $this->starts_at !== null
            && $settings->reschedule_enabled
            && $this->reschedule_count < $settings->max_reschedules
            && now()->lessThanOrEqualTo($this->starts_at->subHours($settings->reschedule_cutoff_hours));
    }

    /**
     * Where the customer pays this booking's fee.
     *
     * @return list<array{key: string, label: string, qr: string|null, accountName: string|null, accountNumber: string|null}>
     */
    public function paymentMethods(): array
    {
        return $this->branch?->paymentMethods() ?? PaymentSetting::instance()->methods();
    }

    /**
     * Shape used by the customer's pages (/api/my-bookings).
     *
     * @return array<string, mixed>
     */
    public function toCustomer(?string $key = null): array
    {
        $stage = $this->stage();
        $paying = in_array($stage, ['awaiting_payment', 'payment_review'], true);
        $branch = $this->branch;

        return [
            'id' => $this->getKey(),
            'reference' => $this->reference,
            'url' => $this->customerPath(),
            'kind' => $this->kind,
            'status' => $this->status,
            'stage' => $stage,
            'branch' => $branch ? [
                'id' => $branch->id,
                'name' => $branch->name,
                'slug' => $branch->slug,
                'address' => $branch->address,
                'phone' => $branch->phone,
                'phoneHref' => $branch->phoneHref(),
                'messenger' => $branch->messenger_url,
                'map' => $branch->map(),
            ] : null,
            'startsAt' => $this->starts_at?->toIso8601String(),
            'readyBy' => $this->ends_at?->toIso8601String(),
            'when' => $this->whenLabel(),
            'preferredDate' => $this->preferred_date?->toDateString(),
            'preferredWindow' => $this->preferred_window,
            'vehicle' => [
                'size' => $this->vehicle_size_name,
                'sizeId' => $this->vehicle_size_id,
                'model' => $this->vehicle_model,
                'plate' => $this->plate_number,
            ],
            'items' => ($this->relationLoaded('items') ? $this->items : $this->items()->get())->map->toCustomer()->values()->all(),
            'serviceIds' => ($this->relationLoaded('items') ? $this->items : $this->items()->get())->pluck('service_id')->filter()->values()->all(),
            'length' => $this->length()->toArray(),
            'totalPrice' => $this->total_price,
            'address' => $this->isHome() ? $this->address : null,
            'area' => $this->isHome() ? $this->area : null,
            'amountDue' => $this->amount_due,
            'balance' => $this->balance(),
            'holdExpiresAt' => $stage === 'awaiting_payment' ? $this->hold_expires_at?->toIso8601String() : null,
            'payment' => [
                'method' => $this->payment_method,
                'submittedAt' => $this->payment_submitted_at?->toIso8601String(),
                'rejectedReason' => $stage === 'awaiting_payment' ? $this->payment_rejected_reason : null,
                'proofUrl' => $this->payment_proof
                    ? url("/api/my-bookings/{$this->getKey()}/proof").($key ? '?key='.rawurlencode($key) : '')
                    : null,
                'paidAt' => $this->paid_at?->toIso8601String(),
            ],
            'methods' => $paying ? $this->paymentMethods() : [],
            'instructions' => $paying ? PaymentSetting::instance()->instructions : null,
            'canCancel' => $this->canBeCancelledByCustomer(),
            'canReschedule' => $this->canBeRescheduledByCustomer(),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
