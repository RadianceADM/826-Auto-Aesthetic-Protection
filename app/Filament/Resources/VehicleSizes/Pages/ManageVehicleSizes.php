<?php

namespace App\Filament\Resources\VehicleSizes\Pages;

use App\Filament\Resources\VehicleSizes\VehicleSizeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageVehicleSizes extends ManageRecords
{
    protected static string $resource = VehicleSizeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add a size'),
        ];
    }
}
