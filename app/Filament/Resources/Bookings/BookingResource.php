<?php

namespace App\Filament\Resources\Bookings;

use App\Filament\Concerns\ScopedToStaffBranches;
use App\Filament\Resources\Bookings\Pages\CreateBooking;
use App\Filament\Resources\Bookings\Pages\EditBooking;
use App\Filament\Resources\Bookings\Pages\ListBookings;
use App\Filament\Resources\Bookings\Pages\ViewBooking;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\PaymentSetting;
use App\Models\Service;
use App\Models\VehicleSize;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

/**
 * Reservations from the site, home-service requests, and bookings the team
 * takes at the counter or over the phone. Staff see only their branches.
 */
class BookingResource extends Resource
{
    use ScopedToStaffBranches;

    protected static ?string $model = Booking::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|UnitEnum|null $navigationGroup = 'Bookings';

    protected static ?string $navigationLabel = 'Bookings';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'reference';

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->needsAction()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Screenshots to check and home-service requests to schedule';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['reference', 'name', 'phone', 'plate_number'];
    }

    public static function statusColor(string $status): string
    {
        return match ($status) {
            'requested', 'payment_review' => 'warning',
            'awaiting_payment' => 'info',
            'confirmed' => 'primary',
            'checked_in' => 'info',
            'ready', 'completed' => 'success',
            'no_show' => 'danger',
            default => 'gray',
        };
    }

    public static function statusLabel(Booking $record): string
    {
        return $record->stage() === 'expired' ? Booking::STATUSES['expired'] : (Booking::STATUSES[$record->status] ?? $record->status);
    }

    /**
     * Branches the signed-in team member may book for.
     *
     * @return array<int, string>
     */
    public static function branchOptions(): array
    {
        return Branch::query()->visibleTo(auth()->user())->ordered()->pluck('name', 'id')->all();
    }

    /**
     * The services a branch offers that have a rate for the size, as
     * "Graphene coating · ₱12,000 · 3 days".
     *
     * @return array<int, string>
     */
    public static function serviceOptions(mixed $branchId, mixed $sizeId, bool $home = false): array
    {
        if (blank($branchId) || blank($sizeId)) {
            return [];
        }

        return Service::query()
            ->active()
            ->whereHas('branches', fn (Builder $branches) => $branches->whereKey($branchId))
            ->when($home, fn (Builder $services) => $services->where('home_service_ok', true))
            ->with(['rates' => fn ($rates) => $rates->where('vehicle_size_id', $sizeId)])
            ->ordered()
            ->get()
            ->filter(fn (Service $service) => $service->rateFor((int) $sizeId)?->isBookable())
            ->mapWithKeys(function (Service $service) use ($sizeId) {
                $rate = $service->rateFor((int) $sizeId);

                return [$service->id => "{$service->name} · ₱".number_format((int) $rate->price).' · '.$rate->length()->label()];
            })
            ->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Booking')
                    ->columnSpan(2)
                    ->columns(2)
                    ->visibleOn('create')
                    ->components([
                        ToggleButtons::make('kind')
                            ->options(Booking::KINDS)
                            ->icons(['onsite' => Heroicon::OutlinedBuildingStorefront, 'home' => Heroicon::OutlinedHome])
                            ->default('onsite')
                            ->inline()
                            ->required()
                            ->live(),
                        Select::make('source')
                            ->label('Came in by')
                            ->options(['walk_in' => 'Walk-in', 'phone' => 'Call or message', 'admin' => 'Other'])
                            ->default('phone')
                            ->required(),
                        Select::make('branch_id')
                            ->label('Branch')
                            ->options(fn () => self::branchOptions())
                            ->default(fn () => count(self::branchOptions()) === 1 ? array_key_first(self::branchOptions()) : null)
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('services', [])),
                        Select::make('vehicle_size_id')
                            ->label('Vehicle size')
                            ->options(fn () => VehicleSize::query()->active()->ordered()->pluck('name', 'id')->all())
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('services', [])),
                        CheckboxList::make('services')
                            ->options(fn (Get $get) => self::serviceOptions($get('branch_id'), $get('vehicle_size_id'), $get('kind') === 'home'))
                            ->helperText(fn (Get $get) => blank($get('branch_id')) || blank($get('vehicle_size_id'))
                                ? 'Pick the branch and the vehicle size first.'
                                : 'Only services this branch offers with a price and duration for this size.')
                            ->required()
                            ->columns(1)
                            ->columnSpanFull(),
                        DateTimePicker::make('starts_at')
                            ->label(fn (Get $get) => $get('kind') === 'home' ? 'Arrive at (optional)' : 'Drop-off')
                            ->seconds(false)
                            ->minutesStep(15)
                            ->displayFormat('D, M j, Y g:i A')
                            ->required(fn (Get $get) => $get('kind') !== 'home')
                            ->helperText(fn (Get $get) => $get('kind') === 'home'
                                ? 'Leave blank to schedule it later.'
                                : 'Ready-by is worked out from the services and the branch hours.'),
                        Toggle::make('force')
                            ->label('Book even if it’s full or outside hours')
                            ->helperText('For when you’ve made room yourself. The timeline shows it as over capacity.')
                            ->visible(fn (Get $get) => $get('kind') !== 'home')
                            ->dehydrated(),
                        DatePicker::make('preferred_date')
                            ->label('Customer’s preferred day')
                            ->displayFormat('D, M j, Y')
                            ->visible(fn (Get $get) => $get('kind') === 'home'),
                        Select::make('preferred_window')
                            ->label('Preferred time')
                            ->options(Booking::WINDOWS)
                            ->default('any')
                            ->visible(fn (Get $get) => $get('kind') === 'home'),
                    ]),
                Section::make('Customer')
                    ->columnSpan(1)
                    ->components([
                        TextInput::make('name')->required()->maxLength(120),
                        TextInput::make('phone')->label('Mobile')->tel()->required()->maxLength(40),
                        TextInput::make('email')->email()->maxLength(190),
                    ]),
                Section::make('Vehicle')
                    ->columnSpan(2)
                    ->columns(2)
                    ->components([
                        TextInput::make('vehicle_model')->label('Make and model')->placeholder('Toyota Vios')->maxLength(80),
                        TextInput::make('plate_number')->label('Plate number')->maxLength(15),
                    ]),
                Section::make('Home service')
                    ->columnSpan(2)
                    ->columns(2)
                    ->visible(fn (Get $get, ?Booking $record) => ($record?->kind ?? $get('kind')) === 'home')
                    ->components([
                        Textarea::make('address')->rows(2)->maxLength(500)->required()->columnSpanFull(),
                        TextInput::make('area')->maxLength(120),
                        TextInput::make('landmark')->maxLength(200),
                        Select::make('has_water')->label('Water hose')->options([1 => 'Yes', 0 => 'No']),
                        Select::make('has_power')->label('Power outlet')->options([1 => 'Yes', 0 => 'No']),
                        Select::make('has_shade')->label('Shade or garage')->options([1 => 'Yes', 0 => 'No']),
                    ]),
                Section::make('Notes')
                    ->columnSpan(1)
                    ->components([
                        Textarea::make('message')->label('From the customer')->rows(3)->maxLength(1000),
                        Textarea::make('admin_note')->label('Private note')->rows(3)->maxLength(2000),
                    ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('When and where')
                    ->columnSpan(2)
                    ->columns(2)
                    ->components([
                        TextEntry::make('when')
                            ->label(fn (Booking $record) => $record->isHome() ? 'Schedule' : 'Drop-off → ready by')
                            ->state(fn (Booking $record) => $record->whenLabel())
                            ->weight('bold')
                            ->columnSpanFull(),
                        TextEntry::make('branch.name')->label('Branch'),
                        TextEntry::make('kind')
                            ->formatStateUsing(fn (string $state) => Booking::KINDS[$state] ?? $state)
                            ->badge()
                            ->color(fn (string $state) => $state === 'home' ? 'info' : 'gray'),
                        TextEntry::make('services')
                            ->state(fn (Booking $record) => $record->items->map(fn ($item) => $item->service_name.' · '.($item->price !== null ? '₱'.number_format($item->price) : '—').' · '.$item->length()->label())->all())
                            ->listWithLineBreaks()
                            ->bulleted()
                            ->columnSpanFull(),
                        TextEntry::make('total_price')
                            ->label('Estimate')
                            ->formatStateUsing(fn (?int $state) => $state === null ? '—' : '₱'.number_format($state)),
                        TextEntry::make('length')
                            ->label('Takes')
                            ->state(fn (Booking $record) => $record->length()->label() ?: '—'),
                        TextEntry::make('message')
                            ->label('From the customer')
                            ->prose()
                            ->formatStateUsing(fn (string $state) => nl2br(e($state)))
                            ->html()
                            ->placeholder('No note')
                            ->columnSpanFull(),
                    ]),
                Section::make('Customer')
                    ->columnSpan(1)
                    ->components([
                        TextEntry::make('status')
                            ->state(fn (Booking $record) => self::statusLabel($record))
                            ->badge()
                            ->color(fn (Booking $record) => $record->stage() === 'expired' ? 'gray' : self::statusColor($record->status))
                            ->helperText(fn (Booking $record) => $record->stage() === 'awaiting_payment' && $record->hold_expires_at
                                ? 'Held until '.$record->hold_expires_at->format('M j, g:i A')
                                : null),
                        TextEntry::make('reference')->copyable(),
                        TextEntry::make('name')
                            ->helperText(fn (Booking $record) => $record->guest ? "Signed in as {$record->guest->email}" : null),
                        TextEntry::make('phone')
                            ->label('Mobile')
                            ->copyable()
                            ->url(fn (Booking $record) => self::phoneUrl($record->phone)),
                        TextEntry::make('email')
                            ->copyable()
                            ->url(fn (Booking $record) => $record->email ? 'mailto:'.$record->email : null)
                            ->placeholder('—'),
                        TextEntry::make('vehicle')
                            ->state(fn (Booking $record) => $record->vehicleLabel() ?: null)
                            ->placeholder('—'),
                        TextEntry::make('admin_note')->label('Private note')->placeholder('—'),
                        TextEntry::make('created_at')
                            ->label(fn (Booking $record) => 'Came in by '.strtolower(Booking::SOURCES[$record->source] ?? $record->source))
                            ->dateTime('M j, Y g:i A')
                            ->helperText(fn (Booking $record) => $record->reschedule_count > 0 ? "Moved by the customer {$record->reschedule_count}×" : null),
                    ]),
                Section::make('Reservation fee')
                    ->columnSpan(2)
                    ->columnStart(1)
                    ->columns(2)
                    ->visible(fn (Booking $record) => $record->amount_due !== null || filled($record->payment_proof) || $record->paid_at !== null)
                    ->components([
                        TextEntry::make('amount_due')
                            ->label('Fee')
                            ->formatStateUsing(fn (?int $state) => $state === null ? '—' : '₱'.number_format($state))
                            ->helperText(fn (Booking $record) => $record->paid_at ? 'Paid '.$record->paid_at->format('M j, g:i A').($record->payment_note ? " · {$record->payment_note}" : '') : null),
                        TextEntry::make('payment_method')
                            ->label('Paid with')
                            ->formatStateUsing(fn (?string $state) => PaymentSetting::METHODS[$state] ?? $state)
                            ->helperText(fn (Booking $record) => $record->payment_submitted_at ? 'Screenshot sent '.$record->payment_submitted_at->format('M j, g:i A') : null)
                            ->placeholder('Not yet'),
                        TextEntry::make('payment_rejected_reason')
                            ->label('Asked for another screenshot')
                            ->color('danger')
                            ->visible(fn (Booking $record) => filled($record->payment_rejected_reason) && $record->stage() === 'awaiting_payment'),
                        ViewEntry::make('payment_proof')
                            ->label('Screenshot')
                            ->view('filament.entries.payment-proof')
                            ->visible(fn (Booking $record) => filled($record->payment_proof))
                            ->columnSpanFull(),
                    ]),
                Section::make('Home service')
                    ->columnSpan(2)
                    ->columnStart(1)
                    ->columns(2)
                    ->visible(fn (Booking $record) => $record->isHome())
                    ->components([
                        TextEntry::make('address')->columnSpanFull(),
                        TextEntry::make('area')->placeholder('—'),
                        TextEntry::make('landmark')->placeholder('—'),
                        TextEntry::make('site')
                            ->label('Water / power / shade')
                            ->state(fn (Booking $record) => collect(['has_water', 'has_power', 'has_shade'])
                                ->map(fn (string $field) => $record->{$field} === null ? 'not said' : ($record->{$field} ? 'yes' : 'no'))
                                ->implode(' / ')),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_at')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['branch', 'items']))
            ->recordClasses(fn (Booking $record) => $record->isRead() ? null : 'font-semibold')
            ->columns([
                IconColumn::make('read_at')
                    ->label('')
                    ->state(fn (Booking $record) => ! $record->isRead())
                    ->boolean()
                    ->trueIcon(Heroicon::Envelope)
                    ->falseIcon(Heroicon::OutlinedEnvelopeOpen)
                    ->trueColor('primary')
                    ->falseColor('gray')
                    ->tooltip(fn (Booking $record) => $record->isRead() ? 'Read' : 'Unread'),
                TextColumn::make('starts_at')
                    ->label('Drop-off')
                    ->state(fn (Booking $record) => $record->starts_at ?? $record->preferred_date)
                    ->formatStateUsing(fn (Booking $record) => $record->starts_at ? $record->starts_at->format('D, M j, g:i A') : 'Asked for '.$record->preferred_date?->format('D, M j'))
                    ->description(fn (Booking $record) => $record->ends_at ? 'Ready '.($record->ends_at->isSameDay($record->starts_at) ? $record->ends_at->format('g:i A') : $record->ends_at->format('D, M j, g:i A')) : Booking::KINDS[$record->kind])
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Customer')
                    ->searchable(['name', 'phone', 'reference', 'plate_number'])
                    ->description(fn (Booking $record) => "{$record->reference} · {$record->phone}"),
                TextColumn::make('services')
                    ->state(fn (Booking $record) => $record->servicesLabel())
                    ->description(fn (Booking $record) => $record->vehicleLabel())
                    ->wrap()
                    ->limit(60),
                TextColumn::make('branch.name')
                    ->label('Branch')
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->state(fn (Booking $record) => self::statusLabel($record))
                    ->color(fn (Booking $record) => $record->stage() === 'expired' ? 'gray' : self::statusColor($record->status))
                    ->description(fn (Booking $record) => $record->stage() === 'awaiting_payment' && $record->hold_expires_at ? 'until '.$record->hold_expires_at->format('g:i A') : null),
                TextColumn::make('total_price')
                    ->label('Estimate')
                    ->formatStateUsing(fn (?int $state) => $state === null ? '—' : '₱'.number_format($state))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Made')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('branch_id')
                    ->label('Branch')
                    ->options(fn () => self::branchOptions())
                    ->visible(fn () => count(self::branchOptions()) > 1),
                SelectFilter::make('kind')
                    ->options(Booking::KINDS),
                SelectFilter::make('status')
                    ->options(Booking::STATUSES)
                    ->multiple(),
            ])
            ->recordActions([
                ViewAction::make(),
                BookingActions::call(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('markRead')
                        ->label('Mark as read')
                        ->icon(Heroicon::OutlinedEnvelopeOpen)
                        ->action(fn (Collection $records) => Booking::whereKey($records->modelKeys())->unread()->update(['read_at' => now()]))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('markUnread')
                        ->label('Mark as unread')
                        ->icon(Heroicon::OutlinedEnvelope)
                        ->action(fn (Collection $records) => Booking::whereKey($records->modelKeys())->update(['read_at' => null]))
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * "0917 758 6820" → "tel:+639177586820"; null when it isn't a number.
     */
    public static function phoneUrl(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        if (strlen($digits) < 7) {
            return null;
        }

        $number = match (true) {
            str_starts_with($digits, '63') => "+{$digits}",
            str_starts_with($digits, '09') => '+63'.substr($digits, 1),
            default => (str_starts_with(trim((string) $phone), '+') ? '+' : '').$digits,
        };

        return "tel:{$number}";
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBookings::route('/'),
            'create' => CreateBooking::route('/create'),
            'view' => ViewBooking::route('/{record}'),
            'edit' => EditBooking::route('/{record}/edit'),
        ];
    }
}
