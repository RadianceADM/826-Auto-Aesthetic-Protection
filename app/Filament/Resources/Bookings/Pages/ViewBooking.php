<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Filament\Resources\Bookings\BookingActions;
use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewBooking extends ViewRecord
{
    protected static string $resource = BookingResource::class;

    /**
     * Opening a booking marks it as read.
     */
    public function mount(int|string $record): void
    {
        parent::mount($record);

        /** @var Booking $booking */
        $booking = $this->getRecord();

        if (! $booking->isRead()) {
            $booking->update(['read_at' => now()]);
        }
    }

    public function getTitle(): string
    {
        /** @var Booking $booking */
        $booking = $this->getRecord();

        return "{$booking->name} · {$booking->reference}";
    }

    protected function getHeaderActions(): array
    {
        return [
            ...BookingActions::workflow(),
            BookingActions::call(),
            BookingActions::text(),
            ActionGroup::make([
                BookingActions::reschedule(),
                BookingActions::extend(),
                BookingActions::noShow(),
                BookingActions::link(),
                BookingActions::email(),
                EditAction::make()->label('Edit details'),
                BookingActions::cancel(),
                Action::make('markUnread')
                    ->label('Mark as unread')
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->action(function (Booking $record) {
                        $record->update(['read_at' => null]);
                        $this->redirect(BookingResource::getUrl('index'));
                    }),
                DeleteAction::make(),
            ]),
        ];
    }
}
