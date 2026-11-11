<?php

namespace App\Filament\Resources\BranchBlocks;

use App\Filament\Concerns\ScopedToStaffBranches;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\BranchBlocks\Pages\ManageBranchBlocks;
use App\Models\BranchBlock;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Holidays, half days and bays out of service, per branch (or for every
 * branch, by the owner). Customers can't pick a time that runs into them.
 */
class BranchBlockResource extends Resource
{
    use ScopedToStaffBranches;

    protected static function includesEveryBranchRecords(): bool
    {
        return true;
    }

    protected static ?string $model = BranchBlock::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNoSymbol;

    protected static string|UnitEnum|null $navigationGroup = 'Bookings';

    protected static ?string $navigationLabel = 'Closures and bays';

    protected static ?string $modelLabel = 'closure';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('branch_id')
                ->label('Branch')
                ->options(fn () => BookingResource::branchOptions())
                ->placeholder(fn () => auth()->user()?->isOwner() ? 'Every branch' : 'Pick a branch')
                ->required(fn () => ! auth()->user()?->isOwner())
                ->helperText(fn () => auth()->user()?->isOwner() ? 'Leave blank to close every branch (Christmas, Holy Week).' : null),
            ToggleButtons::make('kind')
                ->label('What happens')
                ->options(BranchBlock::KINDS)
                ->default('closed')
                ->required()
                ->live(),
            TextInput::make('title')
                ->label('Why')
                ->placeholder('Bonifacio Day, brownout, bay 2 under repair…')
                ->required()
                ->maxLength(120),
            TextInput::make('bays')
                ->label('Bays out')
                ->integer()
                ->minValue(1)
                ->maxValue(50)
                ->placeholder('All')
                ->helperText('Leave blank for every bay.')
                ->visible(fn (Get $get) => $get('kind') === 'bays'),
            Toggle::make('whole_days')
                ->label('Whole days')
                ->default(true)
                ->live()
                ->dehydrated(false)
                ->columnSpanFull(),
            DatePicker::make('first_day')
                ->label('From')
                ->displayFormat('D, M j, Y')
                ->required(fn (Get $get) => $get('whole_days'))
                ->visible(fn (Get $get) => $get('whole_days'))
                ->dehydrated(false),
            DatePicker::make('last_day')
                ->label('To (the last day)')
                ->displayFormat('D, M j, Y')
                ->afterOrEqual('first_day')
                ->required(fn (Get $get) => $get('whole_days'))
                ->visible(fn (Get $get) => $get('whole_days'))
                ->dehydrated(false),
            DateTimePicker::make('starts_at')
                ->label('From')
                ->seconds(false)
                ->minutesStep(15)
                ->displayFormat('D, M j, Y g:i A')
                ->required(fn (Get $get) => ! $get('whole_days'))
                ->visible(fn (Get $get) => ! $get('whole_days')),
            DateTimePicker::make('ends_at')
                ->label('Until')
                ->seconds(false)
                ->minutesStep(15)
                ->displayFormat('D, M j, Y g:i A')
                ->after('starts_at')
                ->required(fn (Get $get) => ! $get('whole_days'))
                ->visible(fn (Get $get) => ! $get('whole_days')),
            Textarea::make('note')
                ->label('Private note')
                ->rows(2)
                ->maxLength(1000)
                ->columnSpanFull(),
        ]);
    }

    /**
     * Whole days become midnight to midnight.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    public static function prepare(array $data, array $raw): array
    {
        if ($raw['whole_days'] ?? false) {
            $data['starts_at'] = CarbonImmutable::parse($raw['first_day'])->startOfDay();
            $data['ends_at'] = CarbonImmutable::parse($raw['last_day'] ?? $raw['first_day'])->addDay()->startOfDay();
        }

        if (($data['kind'] ?? 'closed') !== 'bays') {
            $data['bays'] = null;
        }

        if (! auth()->user()?->isOwner() && blank($data['branch_id'] ?? null)) {
            abort(403);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function fill(BranchBlock $record, array $data): array
    {
        $whole = $record->starts_at->isStartOfDay() && $record->ends_at->isStartOfDay();

        return [
            ...$data,
            'whole_days' => $whole,
            'first_day' => $whole ? $record->starts_at->toDateString() : null,
            'last_day' => $whole ? $record->ends_at->subDay()->toDateString() : null,
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_at')
            ->columns([
                TextColumn::make('starts_at')
                    ->label('When')
                    ->formatStateUsing(fn (BranchBlock $record) => $record->dateLabel())
                    ->sortable(),
                TextColumn::make('title')
                    ->label('Why')
                    ->description(fn (BranchBlock $record) => $record->kind === 'bays' ? ($record->bays ? "{$record->bays} bay".($record->bays === 1 ? '' : 's').' out' : 'Every bay out') : 'Closed'),
                TextColumn::make('branch.name')
                    ->label('Branch')
                    ->placeholder('Every branch'),
            ])
            ->filters([
                TernaryFilter::make('upcoming')
                    ->label('When')
                    ->trueLabel('Upcoming')
                    ->falseLabel('Past')
                    ->default(true)
                    ->queries(
                        true: fn (Builder $query) => $query->where('ends_at', '>=', now()),
                        false: fn (Builder $query) => $query->where('ends_at', '<', now()),
                    ),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(fn (BranchBlock $record, array $data) => self::fill($record, $data))
                    ->mutateDataUsing(fn (array $data, EditAction $action) => self::prepare($data, $action->getRawData())),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBranchBlocks::route('/'),
        ];
    }
}
