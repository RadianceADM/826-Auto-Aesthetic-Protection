<?php

namespace App\Filament\Resources\Services\Pages;

use App\Filament\Resources\Services\ServiceResource;
use App\Models\Service;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

/**
 * Changing a price or duration never touches bookings already made: they
 * keep what they were booked with.
 */
class EditService extends EditRecord
{
    protected static string $resource = ServiceResource::class;

    /**
     * @var array<int|string, array<string, mixed>>
     */
    private array $rates = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Service $service */
        $service = $this->getRecord()->load('rates');

        return [...$data, 'rates' => ServiceResource::ratesForForm($service)];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->rates = $data['rates'] ?? [];
        unset($data['rates']);

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var Service $service */
        $service = $this->getRecord();
        ServiceResource::saveRates($service, $this->rates);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->modalDescription('Bookings already made keep this service’s name, price and duration. Turning off “Bookable” hides it instead.'),
        ];
    }
}
