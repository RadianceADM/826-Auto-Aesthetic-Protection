<?php

namespace App\Support;

use App\Mail\CustomerBookingUpdate;
use App\Mail\TeamBookingAlert;
use App\Models\Booking;
use App\Models\MailSetting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Booking emails, once email is set up in the admin. A mail problem is
 * logged and never stops the booking itself.
 */
final class Notifier
{
    /**
     * To the branch's alert address, else the main one.
     */
    public static function team(Booking $booking, string $kind): bool
    {
        $settings = MailSetting::instance();
        $to = $booking->branch?->alert_email ?: $settings->alertAddress();

        if (! $settings->isConfigured() || blank($to)) {
            return false;
        }

        try {
            Mail::to($to)->send(new TeamBookingAlert($booking, $kind));

            return true;
        } catch (Throwable $e) {
            Log::warning("Could not email the team about booking {$booking->id} ({$kind}): {$e->getMessage()}");

            return false;
        }
    }

    public static function customer(Booking $booking, string $kind): bool
    {
        if (blank($booking->email) || ! MailSetting::instance()->isConfigured()) {
            return false;
        }

        try {
            Mail::to($booking->email)->send(new CustomerBookingUpdate($booking, $kind));

            return true;
        } catch (Throwable $e) {
            Log::warning("Could not email booking {$booking->id} ({$kind}): {$e->getMessage()}");

            return false;
        }
    }
}
