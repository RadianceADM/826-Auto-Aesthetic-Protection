<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use App\Models\Branch;
use App\Support\Reservations;
use Carbon\CarbonImmutable;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * A booking the team takes at the counter, over the phone or on Messenger.
 * Onsite ones are checked against the bays like the site's (any time the
 * branch is open) and confirmed straight away.
 */
class CreateBooking extends CreateRecord
{
    protected static string $resource = BookingResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $branch = Branch::query()->visibleTo(auth()->user())->findOrFail($data['branch_id']);
        $home = ($data['kind'] ?? 'onsite') === 'home';

        try {
            $quote = Reservations::quote($branch, (int) $data['vehicle_size_id'], array_map('intval', $data['services'] ?? []), home: $home);
        } catch (ValidationException $e) {
            $this->failWith($e);
        }

        $details = [
            ...$data,
            'admin_note' => $data['admin_note'] ?? null,
        ];

        if ($home) {
            $booking = Reservations::requestHomeService($branch, null, $quote, [
                ...$details,
                'preferred_date' => $data['preferred_date'] ?? (filled($data['starts_at'] ?? null) ? CarbonImmutable::parse($data['starts_at'])->toDateString() : today()->toDateString()),
                'preferred_window' => $data['preferred_window'] ?? 'any',
            ]);

            $booking->update(array_filter([
                'source' => $data['source'],
                'admin_note' => $data['admin_note'] ?? null,
                'read_at' => now(),
            ], fn ($value) => $value !== null));

            return $booking;
        }

        try {
            return Reservations::reserve(
                $branch,
                null,
                $quote,
                CarbonImmutable::parse($data['starts_at'])->startOfMinute(),
                $details,
                source: $data['source'] ?? 'admin',
                force: (bool) ($data['force'] ?? false),
            );
        } catch (ValidationException $e) {
            $this->failWith($e);
        } catch (LockTimeoutException) {
            Notification::make()->danger()->title('Busy, try again')->send();
            $this->halt();
        }
    }

    private function failWith(ValidationException $e): never
    {
        $message = collect($e->errors())->flatten()->first();
        Notification::make()->danger()->title('Not booked')->body($message)->persistent()->send();

        throw ValidationException::withMessages(['data.starts_at' => $message]);
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        /** @var Booking $booking */
        $booking = $this->getRecord();

        return $booking->isHome() ? 'Home service request added' : 'Booked: '.$booking->whenLabel();
    }
}
