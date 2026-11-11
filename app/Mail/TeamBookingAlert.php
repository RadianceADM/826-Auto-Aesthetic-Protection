<?php

namespace App\Mail;

use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Tells a branch's team that something happened to a booking: a new
 * reservation or hold, a payment screenshot to check, a home-service
 * request, or a customer cancelling or moving one. Replying answers the
 * customer when they left an email.
 */
class TeamBookingAlert extends Mailable
{
    public const KINDS = ['reserved', 'hold', 'payment', 'home_request', 'cancelled', 'rescheduled'];

    public function __construct(public Booking $booking, public string $kind) {}

    public function envelope(): Envelope
    {
        $booking = $this->booking;
        $when = $booking->starts_at?->format('D, M j, g:i A') ?? $booking->preferred_date?->format('D, M j');
        $branch = $booking->branch?->name;

        return new Envelope(
            replyTo: $booking->email ? [new Address($booking->email, $booking->name)] : [],
            subject: match ($this->kind) {
                'reserved' => "New reservation at {$branch}: {$booking->name}, {$when} ({$booking->reference})",
                'hold' => "New hold at {$branch}, waiting for the fee: {$booking->name}, {$when}",
                'payment' => "Screenshot to check: {$booking->name} ({$booking->reference})",
                'home_request' => "Home service request: {$booking->name}, {$when}",
                'cancelled' => "Cancelled by the customer: {$booking->name}, {$when} ({$booking->reference})",
                default => "Moved by the customer: {$booking->name}, now {$when} ({$booking->reference})",
            },
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.team-booking-alert', with: [
            'adminUrl' => BookingResource::getUrl('view', ['record' => $this->booking], panel: 'admin'),
        ]);
    }
}
