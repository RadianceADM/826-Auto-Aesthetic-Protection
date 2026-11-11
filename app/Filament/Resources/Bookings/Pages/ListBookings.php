<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Filament\Resources\Bookings\BookingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListBookings extends ListRecords
{
    protected static string $resource = BookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add a booking'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'upcoming';
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $scoped = fn () => BookingResource::getEloquentQuery();
        $today = today();

        return [
            'action' => Tab::make('Needs you')
                ->badge(fn () => $scoped()->needsAction()->count() ?: null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->needsAction()),
            'today' => Tab::make('Today')
                ->modifyQueryUsing(fn (Builder $query) => $query->open()->whereBetween('starts_at', [$today, $today->copy()->endOfDay()])),
            'in_shop' => Tab::make('In the shop')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['checked_in', 'ready'])),
            'upcoming' => Tab::make('Upcoming')
                ->modifyQueryUsing(fn (Builder $query) => $query->open()
                    ->where(fn (Builder $open) => $open->whereNull('hold_expires_at')->orWhere('hold_expires_at', '>', now()))
                    ->where(fn (Builder $future) => $future->where('ends_at', '>=', now())->orWhereNull('starts_at'))),
            'holds' => Tab::make('Unpaid holds')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'awaiting_payment')->where(fn (Builder $hold) => $hold->whereNull('hold_expires_at')->orWhere('hold_expires_at', '>', now()))),
            'home' => Tab::make('Home service')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('kind', 'home')->open()),
            'past' => Tab::make('Done')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['completed', 'no_show'])->reorder('starts_at', 'desc')),
            'cancelled' => Tab::make('Cancelled')
                ->modifyQueryUsing(fn (Builder $query) => $query->where(fn (Builder $closed) => $closed
                    ->whereIn('status', ['cancelled', 'declined', 'expired'])
                    ->orWhere(fn (Builder $lapsed) => $lapsed->lapsedHolds()))
                    ->reorder('updated_at', 'desc')),
            'all' => Tab::make('All'),
        ];
    }
}
