<x-mail::message>
@switch ($kind)
@case ('reserved')
# New reservation
@break
@case ('hold')
# New hold, waiting for the fee
@break
@case ('payment')
# Payment screenshot to check
@break
@case ('home_request')
# Home service request
@break
@case ('cancelled')
# Cancelled by the customer
@break
@default
# Moved by the customer
@endswitch

**{{ $booking->name }}** · {{ $booking->reference }} · {{ $booking->branch?->name }}

- **When:** {{ $booking->whenLabel() }}
- **Services:** {{ $booking->servicesLabel() }}
- **Vehicle:** {{ $booking->vehicleLabel() ?: '—' }}
- **Mobile:** {{ $booking->phone }}
@if ($booking->email)
- **Email:** {{ $booking->email }}
@endif
@if ($booking->total_price !== null)
- **Estimate:** ₱{{ number_format($booking->total_price) }}
@endif
@if ($booking->amount_due)
- **Reservation fee:** ₱{{ number_format($booking->amount_due) }}{{ $booking->hold_expires_at && $booking->status === 'awaiting_payment' ? ', held until '.$booking->hold_expires_at->format('g:i A') : '' }}
@endif
@if ($booking->isHome())
- **Address:** {{ $booking->address }}{{ $booking->area ? " ({$booking->area})" : '' }}
@if ($booking->landmark)
- **Landmark:** {{ $booking->landmark }}
@endif
- **Water / power / shade:** {{ collect(['has_water', 'has_power', 'has_shade'])->map(fn ($field) => $booking->{$field} === null ? '?' : ($booking->{$field} ? 'yes' : 'no'))->implode(' / ') }}
@endif

@if ($booking->message)
<x-mail::panel>
{!! nl2br(e($booking->message)) !!}
</x-mail::panel>
@endif

@if ($kind === 'payment')
Check the screenshot against your GCash or Maya, then mark it paid in the admin.
@elseif ($kind === 'home_request')
Call or text {{ $booking->name }} to agree on a time, then schedule it in the admin.
@endif

<x-mail::button :url="$adminUrl">
Open in the admin
</x-mail::button>
</x-mail::message>
