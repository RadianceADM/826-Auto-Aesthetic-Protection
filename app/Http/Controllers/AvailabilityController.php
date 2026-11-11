<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ReadsBookingInput;
use App\Models\Branch;
use App\Support\Reservations;
use App\Support\Schedule\Availability;
use App\Support\Schedule\Length;
use App\Support\Schedule\Slot;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Which days and drop-off times a branch has room for, for the services a
 * customer picked. Times only: who booked them is never sent.
 */
class AvailabilityController extends Controller
{
    use ReadsBookingInput;

    /**
     * GET /api/availability?branch=1&size=2&services[]=3&month=2026-11
     * (or &booking=12&key=… to move an existing booking).
     */
    public function month(Request $request): JsonResponse
    {
        [$branch, $length, $total] = $this->job($request);

        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->query('month'))
            ? CarbonImmutable::createFromFormat('!Y-m', $request->query('month'))
            : CarbonImmutable::today();

        $month = $month->startOfMonth()
            ->max(CarbonImmutable::today()->startOfMonth())
            ->min(Availability::lastDay($branch)->startOfMonth());

        return response()->json([
            ...Availability::month($branch, $length, $month),
            'length' => $length->toArray(),
            'total' => $total,
        ])->header('Cache-Control', 'no-store');
    }

    /**
     * GET /api/availability/day?branch=1&size=2&services[]=3&date=2026-11-17
     */
    public function day(Request $request): JsonResponse
    {
        [$branch, $length, $total, $exceptId] = $this->job($request);
        $date = $this->dateFrom($request->query('date'));

        return response()->json([
            'date' => $date->toDateString(),
            'length' => $length->toArray(),
            'total' => $total,
            'slots' => array_map(fn (Slot $slot) => $slot->toArray(), Availability::day($branch, $length, $date, $exceptId)),
        ])->header('Cache-Control', 'no-store');
    }

    /**
     * The branch and the job's length: from the picked services, or from
     * the booking being moved.
     *
     * @return array{0: Branch, 1: Length, 2: int|null, 3: int|null}
     */
    private function job(Request $request): array
    {
        if ($request->filled('booking')) {
            $booking = $this->openableBooking($request, $request->query('booking'));
            abort_unless($booking->isOnsite() && $booking->branch, 404);

            return [$booking->branch, $booking->length(), $booking->total_price, $booking->id];
        }

        $branch = $this->listedBranch($request->query('branch'));
        abort_unless($branch->isOnsiteBookable(), 409, 'This branch doesn’t take online bookings yet.');

        $quote = Reservations::quote($branch, (int) $request->query('size'), $this->serviceIds($request));

        return [$branch, $quote['length'], $quote['total'], null];
    }
}
