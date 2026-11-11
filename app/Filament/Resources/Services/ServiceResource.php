<?php

namespace App\Filament\Resources\Services;

use App\Filament\Resources\Services\Pages\CreateService;
use App\Filament\Resources\Services\Pages\EditService;
use App\Filament\Resources\Services\Pages\ListServices;
use App\Filament\Support\ImageUpload;
use App\Models\Branch;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceRate;
use App\Models\VehicleSize;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * What customers can book (owners only), with a price and duration for
 * each vehicle size. A size left blank isn't offered for that service.
 */
class ServiceResource extends Resource
{
    protected static ?string $model = Service::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'Shop';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public const UNITS = ['hours' => 'Hours', 'days' => 'Days'];

    /**
     * The longest job, in open days.
     */
    public const MAX_DAYS = 60;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Service')
                ->columnSpan(2)
                ->columns(2)
                ->components([
                    TextInput::make('name')
                        ->placeholder('Graphene coating')
                        ->required()
                        ->maxLength(120)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (?string $state, Set $set, ?Service $record) {
                            if (! $record) {
                                $set('slug', Str::slug((string) $state));
                            }
                        }),
                    Select::make('service_category_id')
                        ->label('Category')
                        ->options(fn () => ServiceCategory::query()->ordered()->pluck('name', 'id')->all())
                        ->required(),
                    TextInput::make('slug')
                        ->label('Web address')
                        ->prefix('/services/')
                        ->required()
                        ->maxLength(80)
                        ->alphaDash()
                        ->unique(ignoreRecord: true),
                    TextInput::make('summary')
                        ->label('One-line summary')
                        ->maxLength(160),
                    Textarea::make('description')
                        ->rows(4)
                        ->maxLength(3000)
                        ->columnSpanFull(),
                    Repeater::make('inclusions')
                        ->label('What’s included')
                        ->simple(TextInput::make('item')->maxLength(160)->required())
                        ->addActionLabel('Add a line')
                        ->reorderable()
                        ->defaultItems(0)
                        ->columnSpanFull(),
                ]),
            Section::make('On the site')
                ->columnSpan(1)
                ->components([
                    Toggle::make('is_active')->label('Bookable')->default(true)->helperText('Off hides it from the site and the booking form.'),
                    Toggle::make('home_service_ok')->label('Can be done at home')->helperText('Offered in home-service requests.'),
                    ImageUpload::make('image', 'services')->label('Picture'),
                    TextInput::make('sort_order')->label('Order')->integer()->default(0),
                ]),
            Section::make('Prices and durations')
                ->description('One row per vehicle size. Leave the price blank if this service isn’t offered for that size. Hours: the job is done the same day if it fits before closing. Days: ready at closing on the last day, counting the drop-off day.')
                ->columnSpanFull()
                ->columns(['lg' => 2])
                ->components(fn () => VehicleSize::query()->ordered()->get()->map(fn (VehicleSize $size) => Fieldset::make($size->name.($size->is_active ? '' : ' (hidden)'))
                    ->columns(3)
                    ->components([
                        TextInput::make("rates.{$size->id}.price")
                            ->label('Price')
                            ->prefix('₱')
                            ->integer()
                            ->minValue(0)
                            ->maxValue(10_000_000)
                            ->live(onBlur: true),
                        Select::make("rates.{$size->id}.unit")
                            ->label('Takes')
                            ->options(self::UNITS)
                            ->default('hours')
                            ->selectablePlaceholder(false)
                            ->live()
                            ->required(fn (Get $get) => filled($get("rates.{$size->id}.price"))),
                        TextInput::make("rates.{$size->id}.amount")
                            ->label(fn (Get $get) => $get("rates.{$size->id}.unit") === 'days' ? 'Days' : 'Hours')
                            ->numeric()
                            ->minValue(fn (Get $get) => $get("rates.{$size->id}.unit") === 'days' ? 1 : 0.25)
                            ->maxValue(fn (Get $get) => $get("rates.{$size->id}.unit") === 'days' ? self::MAX_DAYS : 200)
                            ->step(fn (Get $get) => $get("rates.{$size->id}.unit") === 'days' ? 1 : 0.25)
                            ->required(fn (Get $get) => filled($get("rates.{$size->id}.price"))),
                    ]))->values()->all()),
            Section::make('Branches')
                ->columnSpanFull()
                ->components([
                    CheckboxList::make('branches')
                        ->label('Offered at')
                        ->relationship('branches', 'name', fn ($query) => $query->orderBy('sort_order')->orderBy('name'))
                        ->getOptionLabelFromRecordUsing(fn (Branch $record) => $record->name.($record->status === 'draft' ? ' (draft)' : ''))
                        ->bulkToggleable()
                        ->columns(['md' => 3]),
                ]),
        ]);
    }

    /**
     * The service's rates as the form edits them.
     *
     * @return array<int, array{price: int|null, unit: string, amount: float|int|null}>
     */
    public static function ratesForForm(?Service $service): array
    {
        $rates = [];

        foreach (VehicleSize::query()->ordered()->get() as $size) {
            $rate = $service?->rates->firstWhere('vehicle_size_id', $size->id);
            $days = (int) $rate?->duration_days;

            $rates[$size->id] = [
                'price' => $rate?->price,
                'unit' => $days > 0 ? 'days' : 'hours',
                'amount' => $days > 0 ? $days : ($rate?->duration_minutes ? round($rate->duration_minutes / 60, 2) : null),
            ];
        }

        return $rates;
    }

    /**
     * Saves the grid: a price and duration for each size that has a price,
     * and no rate for the sizes left blank.
     *
     * @param  array<int|string, array<string, mixed>>  $rates
     */
    public static function saveRates(Service $service, array $rates): void
    {
        foreach (VehicleSize::query()->pluck('id') as $sizeId) {
            $row = $rates[$sizeId] ?? $rates[(string) $sizeId] ?? [];
            $price = $row['price'] ?? null;
            $amount = is_numeric($row['amount'] ?? null) ? (float) $row['amount'] : null;

            if (blank($price) || $amount === null || $amount <= 0) {
                ServiceRate::query()->where('service_id', $service->id)->where('vehicle_size_id', $sizeId)->delete();

                continue;
            }

            $days = ($row['unit'] ?? 'hours') === 'days';

            ServiceRate::updateOrCreate(
                ['service_id' => $service->id, 'vehicle_size_id' => $sizeId],
                [
                    'price' => (int) $price,
                    'duration_days' => $days ? max(1, min(self::MAX_DAYS, (int) round($amount))) : null,
                    'duration_minutes' => $days ? null : max(15, (int) round($amount * 60)),
                ],
            );
        }
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->modifyQueryUsing(fn ($query) => $query->with(['category', 'rates.vehicleSize'])->withCount('branches'))
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->description(fn (Service $record) => $record->category?->name),
                TextColumn::make('prices')
                    ->label('Prices')
                    ->state(function (Service $record) {
                        $prices = $record->rates->filter->isBookable()->pluck('price');

                        return $prices->isEmpty() ? 'Not set' : '₱'.number_format($prices->min()).($prices->min() !== $prices->max() ? ' – ₱'.number_format($prices->max()) : '');
                    })
                    ->description(fn (Service $record) => $record->rates->filter->isBookable()->count().' of '.VehicleSize::query()->active()->count().' sizes')
                    ->color(fn (Service $record) => $record->rates->filter->isBookable()->isEmpty() ? 'warning' : null),
                TextColumn::make('branches_count')
                    ->label('Branches')
                    ->alignCenter(),
                IconColumn::make('home_service_ok')->label('At home')->boolean(),
                IconColumn::make('is_active')->label('Bookable')->boolean(),
            ])
            ->filters([
                SelectFilter::make('service_category_id')
                    ->label('Category')
                    ->options(fn () => ServiceCategory::query()->ordered()->pluck('name', 'id')->all()),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListServices::route('/'),
            'create' => CreateService::route('/create'),
            'edit' => EditService::route('/{record}/edit'),
        ];
    }
}
