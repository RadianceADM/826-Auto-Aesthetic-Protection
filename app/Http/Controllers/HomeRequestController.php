<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ReadsBookingInput;
use App\Models\Booking;
use App\Support\Notifier;
use App\Support\Reservations;
use App\Support\Schedule\Availability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * POST /api/home-requests: a customer asks a branch to come to them. No
 * bay and no time yet: the branch calls to agree on one.
 */
class HomeRequestController extends Controller
{
    use ReadsBookingInput;

    public const OTHER_AREA = 'Other';

    public function store(Request $request): JsonResponse
    {
        if (filled($request->input(self::HONEYPOT))) {
            return response()->json(['ok' => true], 201);
        }

        $guest = $this->guest();

        if (! $guest && ! config('app.demo')) {
            return response()->json(['message' => 'Sign in with Google to send your request.', 'signIn' => true], 401);
        }

        $branch = $this->listedBranch($request->input('branch'));

        if (! $branch->takesHomeRequests()) {
            throw ValidationException::withMessages(['branch' => "{$branch->name} doesn’t do home service. Pick another branch."]);
        }

        $areas = $branch->homeServiceAreas();
        $first = today()->addDay()->toDateString();
        $last = Availability::lastDay($branch)->toDateString();

        $details = $request->validate([
            'branch' => ['required', 'integer'],
            'size' => ['required', 'integer'],
            'services' => ['required', 'array', 'min:1', 'max:12'],
            'services.*' => ['integer'],
            'preferred_date' => ['required', 'date_format:Y-m-d', "after_or_equal:{$first}", "before_or_equal:{$last}"],
            'preferred_window' => ['required', Rule::in(array_keys(Booking::WINDOWS))],
            'address' => ['required', 'string', 'max:500'],
            'area' => $areas === [] ? ['nullable', 'string', 'max:120'] : ['required', Rule::in([...$areas, self::OTHER_AREA])],
            'area_other' => ['nullable', 'required_if:area,'.self::OTHER_AREA, 'string', 'max:120'],
            'landmark' => ['nullable', 'string', 'max:200'],
            'has_water' => ['nullable', 'boolean'],
            'has_power' => ['nullable', 'boolean'],
            'has_shade' => ['nullable', 'boolean'],
            ...$this->contactRules(),
        ], [
            'services.required' => 'Pick at least one service.',
            'preferred_date.required' => 'Pick the day you’d like us to come.',
            'preferred_date.after_or_equal' => 'Pick a day from tomorrow on.',
            'preferred_date.before_or_equal' => 'That day is too far ahead to book yet.',
            'preferred_window.required' => 'Pick morning, afternoon or any time.',
            'address.required' => 'Add the address where the car will be.',
            'area.required' => 'Pick your area.',
            'area.in' => 'Pick your area from the list, or Other.',
            'area_other.required_if' => 'Tell us your area.',
            ...$this->contactMessages(),
        ]);

        $quote = Reservations::quote($branch, (int) $details['size'], $this->serviceIds($request), home: true);

        if (($details['area'] ?? null) === self::OTHER_AREA) {
            $details['area'] = 'Other: '.trim((string) $details['area_other']);
        }

        if (config('app.demo')) {
            return response()->json(['ok' => true, 'demo' => true], 201);
        }

        $booking = Reservations::requestHomeService($branch, $guest, $quote, $details);

        if (blank($guest->phone)) {
            $guest->update(['phone' => $details['phone']]);
        }

        Notifier::team($booking, 'home_request');
        Notifier::customer($booking, 'home_received');

        return response()->json(['ok' => true, 'booking' => $booking->refresh()->load(['items', 'branch'])->toCustomer()], 201);
    }
}
