<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\Timeline;
use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Today at the signed-in team member's branches: drop-offs, cars in the
 * shop, and what needs them (screenshots and home-service requests).
 */
class BookingStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    // Gentle on SQLite: refresh once a minute, not every 5 seconds
    protected ?string $pollingInterval = '60s';

    protected function getColumns(): int|array|null
    {
        return ['md' => 3];
    }

    protected function getStats(): array
    {
        $scoped = fn () => Booking::query()->visibleTo(auth()->user());
        $today = today();

        $dropOffs = $scoped()->where('kind', 'onsite')->whereIn('status', ['confirmed', 'payment_review', 'awaiting_payment'])
            ->where(fn ($hold) => $hold->whereNull('hold_expires_at')->orWhere('hold_expires_at', '>', now()))
            ->whereBetween('starts_at', [$today, $today->copy()->endOfDay()])->count();
        $inShop = $scoped()->whereIn('status', ['checked_in', 'ready'])->count();
        $ready = $scoped()->where('status', 'ready')->count();
        $toDo = $scoped()->needsAction()->count();

        return [
            Stat::make('Drop-offs today', $dropOffs)
                ->description('Reserved for today')
                ->descriptionIcon(Heroicon::OutlinedCalendarDays)
                ->color($dropOffs ? 'primary' : 'gray')
                ->url(Timeline::getUrl()),
            Stat::make('In the shop', $inShop)
                ->description($ready ? "{$ready} ready for pickup" : 'Checked in')
                ->descriptionIcon(Heroicon::OutlinedWrenchScrewdriver)
                ->color($inShop ? 'info' : 'gray')
                ->url(BookingResource::getUrl('index', ['activeTab' => 'in_shop'])),
            Stat::make('Needs you', $toDo)
                ->description($toDo ? 'Screenshots to check, home visits to schedule' : 'All caught up')
                ->descriptionIcon(Heroicon::OutlinedInboxArrowDown)
                ->color($toDo ? 'warning' : 'gray')
                ->url(BookingResource::getUrl('index', ['activeTab' => 'action'])),
        ];
    }
}
