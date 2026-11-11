<?php

namespace App\Filament\Resources\VehicleSizes;

use App\Filament\Resources\VehicleSizes\Pages\ManageVehicleSizes;
use App\Models\VehicleSize;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The sizes prices are split by (owners only). Customers pick one first.
 */
class VehicleSizeResource extends Resource
{
    protected static ?string $model = VehicleSize::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = 'Shop';

    protected static ?string $navigationLabel = 'Vehicle sizes';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->placeholder('Sedan')->required()->maxLength(60),
            TextInput::make('examples')->placeholder('Vios, City, Civic')->maxLength(160)->helperText('Helps customers pick the right size.'),
            Toggle::make('is_active')->label('Shown to customers')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')->description(fn (VehicleSize $record) => $record->examples),
                TextColumn::make('rates_count')->label('Prices set')->counts('rates')->alignCenter(),
                IconColumn::make('is_active')->label('Shown')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription('Its prices go too. Bookings already made keep the size’s name.'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageVehicleSizes::route('/'),
        ];
    }
}
