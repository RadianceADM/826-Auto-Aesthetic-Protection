<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ReadsBookingInput;
use App\Models\Booking;
use App\Support\Notifier;
use App\Support\Reservations;
use App\Support\Schedule\Availability;
use App\Support\Schedule\Slot;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * POST /api/bookings: a customer reserves a drop-off time at a branch.
 */
class ReservationController extends Controller
{
    use ReadsBookingInput;

    public function store(Request $request): JsonResponse
    {
        if (filled($request->input(self::HONEYPOT))) {
            return response()->json(['ok' => true], 201);
        }

        $guest = $this->guest();

        if (! $guest && ! config('app.demo')) {
            return response()->json(['message' => 'Sign in with Google to reserve.', 'signIn' => true], 401);
        }

        $details = $request->validate([
            'branch' => ['required', 'integer'],
            'size' => ['required', 'integer'],
            'services' => ['required', 'array', 'min:1', 'max:12'],
            'services.*' => ['integer'],
            'date' => ['required', 'string'],
            'time' => ['required', 'string'],
            ...$this->contactRules(),
        ], [
            'branch.required' => 'Pick a branch.',
            'size.required' => 'Pick your vehicle’s size.',
            'services.required' => 'Pick at least one service.',
            'services.min' => 'Pick at least one service.',
            'date.required' => 'Pick a date on the calendar.',
            'time.required' => 'Pick a drop-off time.',
            ...$this->contactMessages(),
        ]);

        $branch = $this->listedBranch($details['branch']);

        if (! $branch->isOnsiteBookable()) {
            throw ValidationException::withMessages(['branch' => "{$branch->name} doesn’t take online bookings yet. Call or message the branch."]);
        }

        $quote = Reservations::quote($branch, (int) $details['size'], $this->serviceIds($request));
        $start = $this->startFrom($details['date'], $details['time']);

        // A demo checks everything like the real site, then keeps nothing
        if (config('app.demo')) {
            $slot = Availability::check($branch, $quote['length'], $start);

            if (! $slot instanceof Slot) {
                throw ValidationException::withMessages(['time' => 'That time isn’t open. Pick one from the list.']);
            }

            return response()->json(['ok' => true, 'demo' => true, 'readyLabel' => $slot->readyLabel()], 201);
        }

        try {
            $booking = Reservations::reserve($branch, $guest, $quote, $start, $details);
        } catch (LockTimeoutException) {
            return response()->json(['message' => 'Lots of people are booking right now. Try again in a moment.'], 409);
        }

        if (blank($guest->phone)) {
            $guest->update(['phone' => $details['phone']]);
        }

        $kind = $booking->status === 'awaiting_payment' ? 'hold' : 'reserved';
        Notifier::team($booking, $kind);
        Notifier::customer($booking, $kind);

        return response()->json(['ok' => true, 'booking' => $this->fresh($booking)->toCustomer()], 201);
    }

    private function fresh(Booking $booking): Booking
    {
        return $booking->refresh()->load(['items', 'branch']);
    }
}
