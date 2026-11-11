<?php

namespace App\Filament\Resources\Photos;

use App\Filament\Resources\Photos\Pages\EditPhoto;
use App\Filament\Resources\Photos\Pages\ListPhotos;
use App\Filament\Support\ImageUpload;
use App\Models\Photo;
use App\Support\ImagePipeline;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

/**
 * The resort's photos: the gallery, by category, and the featured ones on
 * the Home page. Drag to reorder.
 */
class PhotoResource extends Resource
{
    protected static ?string $model = Photo::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|UnitEnum|null $navigationGroup = 'Site';

    protected static ?string $navigationLabel = 'Photos';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Photo')
                    ->columnSpan(2)
                    ->components([
                        ImageUpload::make('image', 'photos')
                            ->hiddenLabel()
                            ->required(),
                    ]),
                Section::make('Details')
                    ->columnSpan(1)
                    ->components([
                        TextInput::make('caption')
                            ->maxLength(200),
                        ...self::detailFields(),
                    ]),
            ]);
    }

    /**
     * Fields shared by the edit form and the bulk upload.
     *
     * @return list<Select|TextInput|Toggle>
     */
    public static function detailFields(): array
    {
        return [
            Select::make('category')
                ->options(Photo::CATEGORIES)
                ->default('pool')
                ->required()
                ->native(false),
            Toggle::make('is_featured')
                ->label('Featured on Home')
                ->helperText('Featured photos appear on the Home page.'),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->paginated([48])
            ->columns([
                ImageColumn::make('image')
                    ->label('')
                    ->state(fn (Photo $record) => ImagePipeline::thumbPathFor($record->image))
                    ->disk('public')
                    ->imageHeight(64),
                TextColumn::make('caption')
                    ->placeholder('No caption')
                    ->limit(40)
                    ->searchable(),
                TextColumn::make('category')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Photo::CATEGORIES[$state] ?? $state),
                ToggleColumn::make('is_featured')
                    ->label('Featured'),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->options(Photo::CATEGORIES),
                TernaryFilter::make('is_featured')
                    ->label('Featured'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('categorize')
                        ->label('Change category')
                        ->icon(Heroicon::OutlinedTag)
                        ->schema([
                            Select::make('category')
                                ->options(Photo::CATEGORIES)
                                ->required()
                                ->native(false),
                        ])
                        ->action(fn (Collection $records, array $data) => $records->each->update(['category' => $data['category']]))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('feature')
                        ->label('Feature on Home')
                        ->icon(Heroicon::OutlinedStar)
                        ->action(fn (Collection $records) => $records->each->update(['is_featured' => true]))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('unfeature')
                        ->label('Remove from Home')
                        ->icon(Heroicon::OutlinedMinusCircle)
                        ->action(fn (Collection $records) => $records->each->update(['is_featured' => false]))
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPhotos::route('/'),
            'edit' => EditPhoto::route('/{record}/edit'),
        ];
    }
}
