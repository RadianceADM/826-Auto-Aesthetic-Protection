<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Support\Notifier;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Emails customers with a confirmed drop-off tomorrow, once each. Runs at
 * 6 PM.
 */
#[Signature('bookings:remind')]
#[Description('Remind customers of tomorrow\'s drop-offs')]
class SendReminders extends Command
{
    public function handle(): int
    {
        $tomorrow = today()->addDay();
        $sent = 0;

        Booking::query()
            ->with(['branch', 'items'])
            ->where('status', 'confirmed')
            ->whereNull('reminded_at')
            ->whereNotNull('email')
            ->where('starts_at', '>=', $tomorrow)
            ->where('starts_at', '<', $tomorrow->copy()->addDay())
            ->orderBy('starts_at')
            ->each(function (Booking $booking) use (&$sent) {
                if (Notifier::customer($booking, 'reminder')) {
                    $booking->update(['reminded_at' => now()]);
                    $sent++;
                }
            });

        $this->info($sent === 1 ? '1 reminder sent.' : "{$sent} reminders sent.");

        return self::SUCCESS;
    }
}
