<?php

namespace App\Filament\Resources\Branches;

use App\Filament\Resources\Branches\Pages\CreateBranch;
use App\Filament\Resources\Branches\Pages\EditBranch;
use App\Filament\Resources\Branches\Pages\ListBranches;
use App\Models\Branch;
use App\Models\PaymentSetting;
use App\Models\User;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * 826's branches (owners only): address and contacts, bays and hours,
 * which services each offers, home service, its own GCash or Maya, and
 * its staff. A branch takes online bookings once it's Open and nothing is
 * missing.
 */
class BranchResource extends Resource
{
    protected static ?string $model = Branch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|UnitEnum|null $navigationGroup = 'Shop';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Callout::make('Not taking online bookings yet')
                ->warning()
                ->description(fn (?Branch $record) => $record ? self::missingHtml($record) : null)
                ->visible(fn (?Branch $record, string $operation) => $operation === 'edit' && $record !== null && $record->missing() !== []),
            Tabs::make('Branch')
                ->persistTabInQueryString()
                ->tabs([
                    Tab::make('Info')
                        ->icon(Heroicon::OutlinedMapPin)
                        ->columns(2)
                        ->schema([
                            TextInput::make('name')
                                ->placeholder('Cainta – Ortigas Extension')
                                ->required()
                                ->maxLength(120)
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (?string $state, Set $set, ?Branch $record) {
                                    if (! $record) {
                                        $set('slug', Str::slug((string) $state));
                                    }
                                }),
                            TextInput::make('slug')
                                ->label('Web address')
                                ->prefix('/branches/')
                                ->required()
                                ->maxLength(80)
                                ->alphaDash()
                                ->unique(ignoreRecord: true),
                            Select::make('status')
                                ->options(Branch::STATUSES)
                                ->default('draft')
                                ->required()
                                ->selectablePlaceholder(false)
                                ->helperText('Draft: hidden. Coming soon: listed with no booking. Open: customers reserve online once nothing is missing.'),
                            TextInput::make('city')->maxLength(80),
                            Textarea::make('address')->rows(2)->maxLength(500)->columnSpanFull(),
                            TextInput::make('phone')->tel()->maxLength(40),
                            TextInput::make('messenger_url')->label('Messenger link')->url()->placeholder('https://m.me/…')->maxLength(255),
                            TextInput::make('facebook_url')->label('Facebook page')->url()->placeholder('https://www.facebook.com/…')->maxLength(255),
                            TextInput::make('map_url')->label('Google Maps link')->url()->maxLength(500)->columnSpanFull(),
                            TextInput::make('map_lat')->label('Map pin latitude')->numeric()->minValue(4)->maxValue(22),
                            TextInput::make('map_lng')->label('Map pin longitude')->numeric()->minValue(116)->maxValue(127),
                            TextInput::make('alert_email')
                                ->label('Email new bookings to')
                                ->email()
                                ->maxLength(190)
                                ->helperText('Leave blank to use the address in Email settings.'),
                            TextInput::make('sort_order')->label('Order on the site')->integer()->default(0),
                        ]),
                    Tab::make('Hours and bays')
                        ->icon(Heroicon::OutlinedClock)
                        ->columns(2)
                        ->schema([
                            TextInput::make('bays')
                                ->label('Bays (cars at once)')
                                ->integer()
                                ->minValue(0)
                                ->maxValue(Branch::MAX_BAYS)
                                ->default(0)
                                ->required()
                                ->helperText('How many cars the team works on at the same time. 0 turns off online bookings.'),
                            Select::make('step_minutes')
                                ->label('Drop-off times')
                                ->options(Branch::STEPS)
                                ->default(60)
                                ->required()
                                ->selectablePlaceholder(false),
                            TextInput::make('lead_hours')
                                ->label('Notice needed')
                                ->suffix('hours')
                                ->integer()
                                ->minValue(0)
                                ->maxValue(336)
                                ->default(2)
                                ->required()
                                ->helperText('The earliest a customer can book from now.'),
                            Select::make('months_ahead')
                                ->label('Book up to')
                                ->options(array_combine(range(1, 12), array_map(fn (int $months) => $months === 1 ? '1 month ahead' : "{$months} months ahead", range(1, 12))))
                                ->default(3)
                                ->required()
                                ->selectablePlaceholder(false),
                            TimePicker::make('multiday_dropoff_by')
                                ->label('Multi-day jobs: latest drop-off')
                                ->seconds(false)
                                ->displayFormat('g:i A')
                                ->helperText('Optional. A 3-day job dropped off late loses most of day 1; set a cutoff like 12:00 PM.'),
                            Section::make('Weekly hours')
                                ->description('When cars can be dropped off and the team works. Jobs pause outside these hours and carry on the next open day.')
                                ->columnSpanFull()
                                ->columns(['md' => 2, 'xl' => 3])
                                ->schema(collect(Branch::WEEKDAYS)->map(fn (string $label, int $day) => Fieldset::make($label)
                                    ->columns(3)
                                    ->schema([
                                        Toggle::make("weekly_hours.{$day}.open_today")
                                            ->label('Open')
                                            ->live()
                                            ->columnSpanFull(),
                                        TimePicker::make("weekly_hours.{$day}.open")
                                            ->label('Opens')
                                            ->seconds(false)
                                            ->displayFormat('g:i A')
                                            ->required(fn (Get $get) => (bool) $get("weekly_hours.{$day}.open_today"))
                                            ->visible(fn (Get $get) => (bool) $get("weekly_hours.{$day}.open_today")),
                                        TimePicker::make("weekly_hours.{$day}.close")
                                            ->label('Closes')
                                            ->seconds(false)
                                            ->displayFormat('g:i A')
                                            ->after("weekly_hours.{$day}.open")
                                            ->required(fn (Get $get) => (bool) $get("weekly_hours.{$day}.open_today"))
                                            ->visible(fn (Get $get) => (bool) $get("weekly_hours.{$day}.open_today")),
                                        TimePicker::make("weekly_hours.{$day}.last_dropoff")
                                            ->label('Last drop-off')
                                            ->seconds(false)
                                            ->displayFormat('g:i A')
                                            ->visible(fn (Get $get) => (bool) $get("weekly_hours.{$day}.open_today")),
                                    ]))->values()->all()),
                        ]),
                    Tab::make('Services')
                        ->icon(Heroicon::OutlinedSparkles)
                        ->schema([
                            CheckboxList::make('services')
                                ->label('Offered at this branch')
                                ->relationship('services', 'name', fn ($query) => $query->orderBy('sort_order')->orderBy('name'))
                                ->bulkToggleable()
                                ->columns(['md' => 2])
                                ->helperText('Prices and durations are set per service (Shop → Services).'),
                        ]),
                    Tab::make('Home service')
                        ->icon(Heroicon::OutlinedHome)
                        ->schema([
                            Toggle::make('offers_home_service')
                                ->label('Takes home-service requests')
                                ->helperText('Customers send a request with their address; the branch calls to set a time. Only services marked “Can be done at home” are offered.')
                                ->live(),
                            TagsInput::make('home_service_areas')
                                ->label('Areas covered')
                                ->placeholder('Add a city or barangay')
                                ->helperText('Customers pick one of these (or Other). Leave empty to let them type their area.')
                                ->visible(fn (Get $get) => (bool) $get('offers_home_service')),
                        ]),
                    Tab::make('Payments')
                        ->icon(Heroicon::OutlinedQrCode)
                        ->columns(2)
                        ->schema([
                            Callout::make('Optional')
                                ->info()
                                ->description('Add this branch’s own GCash or Maya if its reservation fees go to a different account. With none switched on here, customers pay to the main accounts in Settings → Payments.')
                                ->columnSpanFull(),
                            ...self::paymentFields('gcash', 'GCash'),
                            ...self::paymentFields('maya', 'Maya'),
                        ]),
                    Tab::make('Staff')
                        ->icon(Heroicon::OutlinedUsers)
                        ->schema([
                            CheckboxList::make('staff')
                                ->label('Team members at this branch')
                                ->relationship('staff', 'name', fn ($query) => $query->where('is_owner', false)->orderBy('name'))
                                ->getOptionLabelFromRecordUsing(fn (User $record) => "{$record->name} ({$record->email})")
                                ->columns(['md' => 2])
                                ->helperText('They see this branch’s bookings, timeline and closures. Owners see every branch. Invite people in Settings → Users.'),
                        ]),
                ]),
        ]);
    }

    /**
     * @return list<Component>
     */
    private static function paymentFields(string $key, string $label): array
    {
        return [
            Section::make($label)
                ->columnSpan(1)
                ->schema([
                    Toggle::make("{$key}_enabled")->label("Use this branch’s {$label}"),
                    FileUpload::make("{$key}_qr")
                        ->label('QR code')
                        ->image()
                        ->disk(PaymentSetting::DISK)
                        ->directory('payments/branches')
                        ->visibility('public')
                        ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                        ->maxSize(5120)
                        ->imagePreviewHeight('160'),
                    TextInput::make("{$key}_name")->label('Account name')->maxLength(120),
                    TextInput::make("{$key}_number")->label("{$label} number")->tel()->maxLength(40),
                ]),
        ];
    }

    public static function missingHtml(Branch $record): HtmlString
    {
        $items = collect($record->missing())->map(fn (string $item) => '<li>'.e($item).'</li>')->implode('');

        return new HtmlString('<ul class="list-disc ps-5">'.$items.'</ul>');
    }

    /**
     * Weekly hours as the form edits them (an "open today" switch per day).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function fillHours(array $data): array
    {
        $saved = is_array($data['weekly_hours'] ?? null) ? $data['weekly_hours'] : [];
        $hours = [];

        foreach (array_keys(Branch::WEEKDAYS) as $day) {
            $row = $saved[$day] ?? $saved[(string) $day] ?? null;
            $hours[$day] = [
                'open_today' => is_array($row) && filled($row['open'] ?? null),
                'open' => $row['open'] ?? null,
                'close' => $row['close'] ?? null,
                'last_dropoff' => $row['last_dropoff'] ?? null,
            ];
        }

        return [...$data, 'weekly_hours' => $hours];
    }

    /**
     * Back to the stored shape: "HH:MM" times, null for closed days.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function saveHours(array $data): array
    {
        $form = is_array($data['weekly_hours'] ?? null) ? $data['weekly_hours'] : [];
        $hours = [];

        foreach (array_keys(Branch::WEEKDAYS) as $day) {
            $row = $form[$day] ?? $form[(string) $day] ?? [];
            $hours[(string) $day] = ($row['open_today'] ?? false) && Branch::clock($row['open'] ?? null) && Branch::clock($row['close'] ?? null)
                ? [
                    'open' => Branch::clock($row['open']),
                    'close' => Branch::clock($row['close']),
                    'last_dropoff' => Branch::clock($row['last_dropoff'] ?? null),
                ]
                : null;
        }

        $data['weekly_hours'] = $hours;
        $data['multiday_dropoff_by'] = Branch::clock($data['multiday_dropoff_by'] ?? null);

        return $data;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->description(fn (Branch $record) => $record->city),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'open' => 'Open',
                        'coming_soon' => 'Coming soon',
                        default => 'Draft',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'open' => 'success',
                        'coming_soon' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('online')
                    ->label('Online booking')
                    ->state(fn (Branch $record) => $record->isOnsiteBookable() ? 'Taking bookings' : (count($record->missing()) === 0 ? 'Not open' : count($record->missing()).' to fill in'))
                    ->badge()
                    ->color(fn (Branch $record) => $record->isOnsiteBookable() ? 'success' : 'warning'),
                TextColumn::make('bays')->alignCenter(),
                TextColumn::make('staff_count')
                    ->label('Staff')
                    ->counts('staff')
                    ->alignCenter(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBranches::route('/'),
            'create' => CreateBranch::route('/create'),
            'edit' => EditBranch::route('/{record}/edit'),
        ];
    }
}
