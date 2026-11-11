<?php

namespace App\Filament\Resources\Branches\Pages;

use App\Filament\Resources\Branches\BranchResource;
use App\Models\Branch;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditBranch extends EditRecord
{
    protected static string $resource = BranchResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return BranchResource::fillHours($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return BranchResource::saveHours($data);
    }

    protected function afterSave(): void
    {
        /** @var Branch $branch */
        $branch = $this->getRecord()->refresh();

        if ($branch->status === 'open' && $branch->missing() !== []) {
            Notification::make()
                ->warning()
                ->title('Saved, but not taking online bookings yet')
                ->body(implode(' ', $branch->missing()))
                ->persistent()
                ->send();
        }

        $outside = $branch->bookings()
            ->holdingABay()
            ->where('ends_at', '>', now())
            ->get()
            ->filter(fn ($booking) => $branch->hoursOn($booking->starts_at) === null)
            ->count();

        if ($outside > 0) {
            Notification::make()
                ->warning()
                ->title("{$outside} upcoming ".($outside === 1 ? 'booking drops' : 'bookings drop').' off on a day that’s now closed')
                ->body('They keep their times. Check the timeline and move them if needed.')
                ->persistent()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('view')
                ->label('View on the site')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn (Branch $record) => url("/branches/{$record->slug}"), shouldOpenInNewTab: true)
                ->visible(fn (Branch $record) => $record->status !== 'draft'),
            DeleteAction::make(),
        ];
    }
}
