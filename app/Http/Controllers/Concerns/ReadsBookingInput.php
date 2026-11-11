<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Guest;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Reading the branch, services, date and time a customer picked, shared by
 * the availability, reservation and home-service endpoints.
 */
trait ReadsBookingInput
{
    /**
     * The honeypot field: hidden from people, filled in by bots.
     */
    public const HONEYPOT = 'fax_number';

    protected function guest(): ?Guest
    {
        $guest = Auth::guard('guest')->user();

        return $guest instanceof Guest ? $guest : null;
    }

    /**
     * A branch shown on the site; drafts are never found.
     */
    protected function listedBranch(mixed $id): Branch
    {
        $branch = is_numeric($id) ? Branch::query()->listed()->find((int) $id) : null;

        if (! $branch) {
            throw ValidationException::withMessages(['branch' => 'Pick a branch from the list.']);
        }

        return $branch;
    }

    /**
     * @return list<int>
     */
    protected function serviceIds(Request $request): array
    {
        $ids = $request->input('services', []);

        return is_array($ids) ? array_values(array_map('intval', array_filter($ids, 'is_numeric'))) : [];
    }

    protected function dateFrom(mixed $value, string $field = 'date'): CarbonImmutable
    {
        $date = is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)
            ? CarbonImmutable::createFromFormat('!Y-m-d', $value)
            : false;

        if (! $date || $date->format('Y-m-d') !== $value) {
            throw ValidationException::withMessages([$field => 'Pick a date on the calendar.']);
        }

        return $date;
    }

    /**
     * The drop-off moment from "2026-11-17" and "09:00", in Manila time.
     */
    protected function startFrom(mixed $date, mixed $time): CarbonImmutable
    {
        $day = $this->dateFrom($date);

        if (! is_string($time) || ! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
            throw ValidationException::withMessages(['time' => 'Pick a drop-off time.']);
        }

        return $day->setTimeFromTimeString($time);
    }

    /**
     * A booking the request may open (signed-in owner or private key), else 404.
     */
    protected function openableBooking(Request $request, mixed $id): Booking
    {
        $booking = is_numeric($id) ? Booking::find((int) $id) : null;
        $key = is_string($request->input('key', $request->query('key'))) ? $request->input('key', $request->query('key')) : null;

        abort_unless($booking && $booking->isOpenedBy($this->guest(), $key), 404);

        return $booking;
    }

    /**
     * @return array<string, mixed>
     */
    protected function contactRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:40', 'regex:/^\+?[\d\s().-]+$/', function (string $attribute, mixed $value, Closure $fail) {
                $digits = strlen(preg_replace('/\D/', '', (string) $value));

                if ($digits < 10 || $digits > 13) {
                    $fail('Enter your mobile number, like 0917 123 4567.');
                }
            }],
            'email' => ['nullable', 'email', 'max:190'],
            'vehicle_model' => ['required', 'string', 'max:80'],
            'plate_number' => ['required', 'string', 'max:15', 'regex:/^[A-Za-z0-9 -]+$/'],
            'message' => ['nullable', 'string', 'max:1000'],
            'consent' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function contactMessages(): array
    {
        return [
            'name.required' => 'Add your name so the branch knows who to look for.',
            'name.max' => 'That name is too long. Keep it under 120 characters.',
            'phone.required' => 'Add a mobile number so the branch can reach you.',
            'phone.regex' => 'Use numbers only, like 0917 123 4567.',
            'email.email' => 'That doesn’t look like an email address. Check it, or leave it blank.',
            'vehicle_model.required' => 'Add your car’s make and model, like Toyota Vios.',
            'plate_number.required' => 'Add your plate number.',
            'plate_number.regex' => 'Use letters and numbers only for the plate.',
            'plate_number.max' => 'That plate number is too long.',
            'message.max' => 'Keep the note under 1,000 characters.',
            'consent.accepted' => 'Agree to the privacy notice to continue.',
        ];
    }
}
