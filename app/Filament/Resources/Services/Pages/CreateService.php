<?php

namespace App\Filament\Resources\Services\Pages;

use App\Filament\Resources\Services\ServiceResource;
use App\Models\Service;
use Filament\Resources\Pages\CreateRecord;

class CreateService extends CreateRecord
{
    protected static string $resource = ServiceResource::class;

    /**
     * @var array<int|string, array<string, mixed>>
     */
    private array $rates = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->rates = $data['rates'] ?? [];
        unset($data['rates']);

        return [...$data, 'sort_order' => (int) ($data['sort_order'] ?? 0)];
    }

    protected function afterCreate(): void
    {
        /** @var Service $service */
        $service = $this->getRecord();
        ServiceResource::saveRates($service, $this->rates);
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
