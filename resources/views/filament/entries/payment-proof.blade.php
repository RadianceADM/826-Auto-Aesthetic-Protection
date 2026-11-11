{{-- The guest's payment screenshot, from the private disk (admin only) --}}
@php($record = $getRecord())
<a href="{{ route('admin.bookings.proof', $record) }}" target="_blank" rel="noopener" class="inline-block">
    <img
        src="{{ route('admin.bookings.proof', $record) }}"
        alt="Payment screenshot from {{ $record->name }}"
        class="max-h-[32rem] w-auto rounded-lg ring-1 ring-gray-950/10 dark:ring-white/10"
        loading="lazy"
    />
</a>
<p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Opens full size in a new tab. Check it against your GCash or Maya app before marking it paid.</p>
