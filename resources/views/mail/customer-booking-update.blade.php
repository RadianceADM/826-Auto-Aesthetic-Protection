<x-mail::message>
@switch ($kind)
@case ('reserved')
# You’re booked

Hi {{ $booking->name }}, your drop-off at **826 {{ $booking->branch?->name }}** is set.
@break
@case ('hold')
# Pay to keep your slot

Hi {{ $booking->name }}, we’re holding **{{ $booking->whenLabel() }}** for you until **{{ $booking->hold_expires_at?->format('g:i A') }}**.

Pay the reservation fee of **₱{{ number_format((int) $booking->amount_due) }}** by GCash or Maya, then upload a screenshot on your booking page. If no screenshot comes in by then, the slot opens up for others.
@break
@case ('paid')
# Payment received

Hi {{ $booking->name }}, we’ve checked your reservation fee. You’re booked.
@break
@case ('rejected')
# Your screenshot needs another look

Hi {{ $booking->name }}, we couldn’t match the screenshot you sent.

@if ($booking->payment_rejected_reason)
<x-mail::panel>
{{ $booking->payment_rejected_reason }}
</x-mail::panel>
@endif

Upload another one on your booking page before **{{ $booking->hold_expires_at?->format('g:i A') }}** to keep the slot.
@break
@case ('expired')
# Your hold ran out

Hi {{ $booking->name }}, no payment came in for **{{ $booking->whenLabel() }}**, so the slot is open again. You can book another time on the site.
@break
@case ('ready')
# Your car is ready

Hi {{ $booking->name }}, your {{ $booking->vehicle_model ?: 'car' }} is ready for pickup at **826 {{ $booking->branch?->name }}**.
@break
@case ('cancelled')
# Booking cancelled

Hi {{ $booking->name }}, your booking {{ $booking->reference }} is cancelled.
@break
@case ('rescheduled')
# Booking moved

Hi {{ $booking->name }}, your booking is now on **{{ $booking->whenLabel() }}**.
@break
@case ('reminder')
# See you tomorrow

Hi {{ $booking->name }}, a reminder of your drop-off at **826 {{ $booking->branch?->name }}**: **{{ $booking->whenLabel() }}**.
@break
@case ('home_received')
# We got your request

Hi {{ $booking->name }}, 826 {{ $booking->branch?->name }} will call or text you to agree on a time for your home service.
@break
@case ('home_scheduled')
# Home service scheduled

Hi {{ $booking->name }}, we’ll come to you on **{{ $booking->whenLabel() }}**.
@if ($booking->status === 'awaiting_payment' && $booking->amount_due)

Pay the reservation fee of **₱{{ number_format((int) $booking->amount_due) }}** by GCash or Maya and upload a screenshot on your booking page.
@endif
@break
@default
# About your request

Hi {{ $booking->name }}, we can’t take this home service request. Call or message the branch and we’ll help you find another way.
@endswitch

- **Reference:** {{ $booking->reference }}
@if ($booking->starts_at)
- **When:** {{ $booking->whenLabel() }}
@endif
- **Services:** {{ $booking->servicesLabel() }}
@if ($booking->total_price !== null)
- **Estimate:** ₱{{ number_format($booking->total_price) }}
@endif

<x-mail::button :url="$bookingUrl">
Open your booking
</x-mail::button>

{{ $brand }}
</x-mail::message>
