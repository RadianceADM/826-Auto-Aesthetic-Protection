<?php

namespace App\Filament\Resources\BranchBlocks\Pages;

use App\Filament\Resources\BranchBlocks\BranchBlockResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageBranchBlocks extends ManageRecords
{
    protected static string $resource = BranchBlockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Close or block bays')
                ->mutateDataUsing(fn (array $data, CreateAction $action) => [
                    ...BranchBlockResource::prepare($data, $action->getRawData()),
                    'created_by' => auth()->id(),
                ]),
        ];
    }
}
