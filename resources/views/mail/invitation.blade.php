<x-mail::message>
# You're invited

{{ $invitedBy ? "{$invitedBy} added you" : 'You were added' }} to the {{ $studio }} admin, where the team keeps its bookings, calendar, rates, photos and settings.

Choose a password to get started, then sign in at {{ $adminUrl }} with **{{ $user->email }}**.

<x-mail::button :url="$setPasswordUrl">
Choose your password
</x-mail::button>

This link works for {{ $expiresInHours }} hours. If it has run out, use **Forgot password?** on the sign-in page, or ask for a new invite.
</x-mail::message>
