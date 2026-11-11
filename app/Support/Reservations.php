<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\BookingSetting;
use App\Models\Branch;
use App\Models\Guest;
use App\Models\PaymentSetting;
use App\Models\Service;
use App\Models\ServiceRate;
use App\Models\VehicleSize;
use App\Support\Schedule\Availability;
use App\Support\Schedule\Length;
use App\Support\Schedule\OpenCalendar;
use App\Support\Schedule\Slot;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Making, moving and cancelling reservations. Anything that checks a
 * branch's bays and then writes runs under that branch's lock, so two
 * customers can't take the last bay at the same moment.
 */
final class Reservations
{
    public const LOCK_SECONDS = 10;

    public const WAIT_SECONDS = 5;

    /**
     * Runs the callback while holding the branch's booking lock. Throws
     * LockTimeoutException when another booking keeps it too long.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function underLock(Branch|int $branch, Closure $callback): mixed
    {
        $id = $branch instanceof Branch ? $branch->getKey() : $branch;

        return Cache::lock("booking:branch:{$id}", self::LOCK_SECONDS)->block(self::WAIT_SECONDS, $callback);
    }

    /**
     * The services for a size at a branch, checked: active, offered there,
     * with a price and duration for the size (and doable at home, for home
     * service). Throws a validation error naming the field to fix.
     *
     * @param  list<int>  $serviceIds
     * @return array{size: VehicleSize, rates: Collection<int, ServiceRate>, length: Length, total: int}
     */
    public static function quote(Branch $branch, int $sizeId, array $serviceIds, bool $home = false): array
    {
        $size = VehicleSize::query()->active()->find($sizeId);

        if (! $size) {
            throw ValidationException::withMessages(['size' => 'Pick your vehicle’s size from the list.']);
        }

        $serviceIds = array_values(array_unique(array_map('intval', $serviceIds)));

        if ($serviceIds === []) {
            throw ValidationException::withMessages(['services' => 'Pick at least one service.']);
        }

        $services = $branch->services()
            ->active()
            ->whereKey($serviceIds)
            ->with(['rates' => fn ($rates) => $rates->where('vehicle_size_id', $size->id), 'category'])
            ->get()
            ->keyBy('id');

        $rates = collect();

        foreach ($serviceIds as $id) {
            /** @var Service|null $service */
            $service = $services->get($id);
            $rate = $service?->rateFor($size->id);

            if (! $service || ! $rate?->isBookable()) {
                throw ValidationException::withMessages(['services' => 'One of those services isn’t offered for a '.strtolower($size->name).' at '.$branch->name.'. Check your picks.']);
            }

            if ($home && ! $service->home_service_ok) {
                throw ValidationException::withMessages(['services' => "{$service->name} needs the shop, so it can’t be done at home."]);
            }

            $rate->setRelation('service', $service);
            $rates->push($rate);
        }

        $rates = $rates->sortBy(fn (ServiceRate $rate) => [$rate->service->category?->sort_order ?? 0, $rate->service->sort_order, $rate->service->id])->values();

        return [
            'size' => $size,
            'rates' => $rates,
            'length' => Length::sum($rates->map->length()),
            'total' => (int) $rates->sum('price'),
        ];
    }

    /**
     * Reserves a drop-off time, re-checked under the lock. With the fee on,
     * the bay is held for the customer to pay; otherwise it's confirmed.
     *
     * @param  array{size: VehicleSize, rates: Collection<int, ServiceRate>, length: Length, total: int}  $quote
     * @param  array<string, mixed>  $details  name, phone, email, vehicle_model, plate_number, message
     */
    public static function reserve(Branch $branch, ?Guest $guest, array $quote, CarbonImmutable $start, array $details, string $source = 'site', bool $force = false): Booking
    {
        $byStaff = $source !== 'site';

        return self::underLock($branch, function () use ($branch, $guest, $quote, $start, $details, $source, $byStaff, $force) {
            if ($guest && ! $byStaff) {
                self::checkGuestLimits($guest);
            }

            $slot = $byStaff
                ? Availability::checkForStaff($branch, $quote['length'], $start)
                : Availability::check($branch, $quote['length'], $start);

            if (! $slot instanceof Slot && $byStaff && $force) {
                $ready = OpenCalendar::for($branch, $start, $start)->finish($start, $quote['length']) ?? $start->addMinutes(max(60, $quote['length']->minutes));
                $slot = new Slot($start, $ready);
            }

            if (! $slot instanceof Slot) {
                throw ValidationException::withMessages(['time' => match (true) {
                    $slot === 'full' && $byStaff => 'Every bay is taken for part of that job. Pick another time, or turn on “Book even if it’s full”.',
                    $slot === 'full' => 'Someone just took that time. Pick another one.',
                    $byStaff => 'The branch is closed at that time. Pick a time within its hours, or turn on “Book even if it’s full”.',
                    default => 'That time isn’t open for drop-off. Pick one from the list.',
                }]);
            }

            // Bookings the team takes are settled at the counter
            $payments = PaymentSetting::instance();
            $fee = ! $byStaff && $payments->feeEnabled() && $quote['total'] > 0 ? $payments->amountFor($quote['total']) : 0;

            return DB::transaction(function () use ($branch, $guest, $quote, $slot, $details, $source, $fee, $payments) {
                $booking = Booking::create([
                    ...self::onlyDetails($details),
                    'kind' => 'onsite',
                    'source' => $source,
                    'status' => $fee > 0 ? 'awaiting_payment' : 'confirmed',
                    'branch_id' => $branch->id,
                    'guest_id' => $guest?->id,
                    'starts_at' => $slot->start,
                    'ends_at' => $slot->readyBy,
                    'vehicle_size_id' => $quote['size']->id,
                    'vehicle_size_name' => $quote['size']->name,
                    'total_price' => $quote['total'],
                    'total_days' => $quote['length']->days,
                    'total_minutes' => $quote['length']->minutes,
                    'amount_due' => $fee > 0 ? $fee : null,
                    'hold_expires_at' => $fee > 0 ? now()->addMinutes($payments->holdMinutes()) : null,
                    'email' => filled($details['email'] ?? null) ? $details['email'] : $guest?->email,
                    'read_at' => $source === 'site' ? null : now(),
                ]);

                self::saveItems($booking, $quote['rates']);

                return $booking;
            });
        });
    }

    /**
     * A home-service request: no bay and no time yet; the branch calls to
     * schedule it.
     *
     * @param  array{size: VehicleSize, rates: Collection<int, ServiceRate>, length: Length, total: int}  $quote
     * @param  array<string, mixed>  $details
     */
    public static function requestHomeService(Branch $branch, ?Guest $guest, array $quote, array $details): Booking
    {
        if ($guest) {
            self::checkGuestLimits($guest);
        }

        return DB::transaction(function () use ($branch, $guest, $quote, $details) {
            $booking = Booking::create([
                ...self::onlyDetails($details),
                'kind' => 'home',
                'source' => 'site',
                'status' => 'requested',
                'branch_id' => $branch->id,
                'guest_id' => $guest?->id,
                'preferred_date' => $details['preferred_date'],
                'preferred_window' => $details['preferred_window'] ?? 'any',
                'address' => $details['address'],
                'area' => $details['area'] ?? null,
                'landmark' => $details['landmark'] ?? null,
                'has_water' => $details['has_water'] ?? null,
                'has_power' => $details['has_power'] ?? null,
                'has_shade' => $details['has_shade'] ?? null,
                'vehicle_size_id' => $quote['size']->id,
                'vehicle_size_name' => $quote['size']->name,
                'total_price' => $quote['total'],
                'total_days' => $quote['length']->days,
                'total_minutes' => $quote['length']->minutes,
                'email' => filled($details['email'] ?? null) ? $details['email'] : $guest?->email,
            ]);

            self::saveItems($booking, $quote['rates']);

            return $booking;
        });
    }

    /**
     * Moves an onsite reservation to another drop-off time, keeping its
     * services, payment and length (as booked). Staff can book over a full
     * time on purpose with $force.
     */
    public static function reschedule(Booking $booking, CarbonImmutable $start, bool $byCustomer = true, bool $force = false): Booking
    {
        $branch = $booking->branch;

        return self::underLock($branch, function () use ($booking, $branch, $start, $byCustomer, $force) {
            $booking->refresh();

            if ($byCustomer && ! $booking->canBeRescheduledByCustomer()) {
                throw ValidationException::withMessages(['time' => 'This booking can’t be moved online anymore. Call or message the branch.']);
            }

            $slot = $byCustomer
                ? Availability::check($branch, $booking->length(), $start, $booking->id)
                : Availability::checkForStaff($branch, $booking->length(), $start, $booking->id);

            if (! $slot instanceof Slot) {
                if (! $force) {
                    throw ValidationException::withMessages(['time' => match (true) {
                        $slot === 'full' && ! $byCustomer => 'Every bay is taken for part of that job. Pick another time, or turn on “Book even if it’s full”.',
                        $slot === 'full' => 'That time is full. Pick another one.',
                        ! $byCustomer => 'The branch is closed at that time.',
                        default => 'That time isn’t open for drop-off. Pick one from the list.',
                    }]);
                }

                $ready = OpenCalendar::for($branch, $start, $start)->finish($start, $booking->length()) ?? $start->addMinutes(max(60, $booking->total_minutes));
                $slot = new Slot($start, $ready);
            }

            $booking->update([
                'starts_at' => $slot->start,
                'ends_at' => $slot->readyBy,
                'reschedule_count' => $byCustomer ? $booking->reschedule_count + 1 : $booking->reschedule_count,
                'reminded_at' => null,
                'read_at' => $byCustomer ? null : $booking->read_at,
            ]);

            return $booking;
        });
    }

    public static function cancel(Booking $booking, string $by, ?string $reason = null): Booking
    {
        $booking->update([
            'status' => 'cancelled',
            'cancelled_by' => $by,
            'cancel_reason' => $reason,
            'read_at' => $by === 'guest' ? null : $booking->read_at,
        ]);

        return $booking;
    }

    /**
     * One unpaid hold at a time, and only so many upcoming bookings, per
     * customer (Booking rules), so nobody can sit on every bay.
     */
    public static function checkGuestLimits(Guest $guest): void
    {
        $settings = BookingSetting::current();

        $holds = $guest->bookings()
            ->where('status', 'awaiting_payment')
            ->where(fn ($hold) => $hold->whereNull('hold_expires_at')->orWhere('hold_expires_at', '>', now()))
            ->count();

        if ($settings->max_unpaid_holds_per_guest > 0 && $holds >= $settings->max_unpaid_holds_per_guest) {
            throw ValidationException::withMessages(['time' => 'You have a booking waiting for its reservation fee. Pay or cancel it in My bookings first.']);
        }

        $upcoming = $guest->bookings()
            ->open()
            ->where(fn ($open) => $open->whereNull('hold_expires_at')->orWhere('hold_expires_at', '>', now()))
            ->where(fn ($future) => $future->whereNull('starts_at')->orWhere('ends_at', '>', now()))
            ->count();

        if ($settings->max_upcoming_per_guest > 0 && $upcoming >= $settings->max_upcoming_per_guest) {
            throw ValidationException::withMessages(['time' => "You already have {$upcoming} upcoming bookings. Call or message the branch to book more."]);
        }
    }

    /**
     * @param  array<string, mixed>  $details
     * @return array<string, mixed>
     */
    private static function onlyDetails(array $details): array
    {
        return array_intersect_key($details, array_flip(['name', 'phone', 'email', 'vehicle_model', 'plate_number', 'message', 'admin_note']));
    }

    /**
     * @param  Collection<int, ServiceRate>  $rates
     */
    private static function saveItems(Booking $booking, Collection $rates): void
    {
        foreach ($rates->values() as $index => $rate) {
            $booking->items()->create([
                'service_id' => $rate->service_id,
                'service_name' => $rate->service->name,
                'category_name' => $rate->service->category?->name,
                'price' => $rate->price,
                'duration_minutes' => (int) $rate->duration_minutes,
                'duration_days' => (int) $rate->duration_days,
                'sort_order' => $index,
            ]);
        }
    }
}
