<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Support\Notifier;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Unpaid holds past their deadline become "expired" and the customer is
 * told. The bay was already free (availability checks the deadline itself);
 * this keeps the lists tidy and sends the email. Runs every minute.
 */
#[Signature('bookings:expire-holds')]
#[Description('Expire unpaid reservation holds whose time is up')]
class ExpireHolds extends Command
{
    public function handle(): int
    {
        $expired = 0;

        Booking::query()->lapsedHolds()->with('branch')->orderBy('id')->each(function (Booking $booking) use (&$expired) {
            // Only flip it if nobody paid in the meantime
            $updated = Booking::query()->whereKey($booking->id)->lapsedHolds()->update(['status' => 'expired', 'cancelled_at' => now()]);

            if ($updated) {
                $expired++;
                Notifier::customer($booking->refresh(), 'expired');
            }
        });

        $this->info($expired === 1 ? '1 hold expired.' : "{$expired} holds expired.");

        return self::SUCCESS;
    }
}
