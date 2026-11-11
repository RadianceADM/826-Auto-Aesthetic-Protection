<?php

namespace App\Filament\Resources\Photos\Pages;

use App\Filament\Resources\Photos\PhotoResource;
use App\Filament\Support\ImageUpload;
use App\Models\Photo;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListPhotos extends ListRecords
{
    protected static string $resource = PhotoResource::class;

    /**
     * Each photo is converted to WebP on save, so keep one batch well inside
     * a request's time limit.
     */
    public const MAX_BATCH = 30;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('upload')
                ->label('Upload photos')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->modalWidth('3xl')
                ->schema([
                    ImageUpload::make('images', 'photos')
                        ->label('Photos')
                        ->multiple()
                        ->maxFiles(self::MAX_BATCH)
                        ->panelLayout('grid')
                        ->required()
                        ->helperText('Up to '.self::MAX_BATCH.' at a time. They are added at the end of the list.'),
                    ...PhotoResource::detailFields(),
                ])
                ->modalSubmitActionLabel('Add photos')
                ->action(function (array $data) {
                    set_time_limit(300);

                    $sortOrder = (int) Photo::max('sort_order');

                    foreach (array_values($data['images']) as $path) {
                        Photo::create([
                            'image' => $path,
                            'category' => $data['category'],
                            'is_featured' => $data['is_featured'] ?? false,
                            'sort_order' => ++$sortOrder,
                        ]);
                    }

                    Notification::make()
                        ->success()
                        ->title(count($data['images']).' photos added')
                        ->send();
                }),
        ];
    }
}
