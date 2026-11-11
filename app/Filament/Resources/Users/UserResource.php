<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Branch;
use App\Models\User;
use App\Support\Invitations;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Throwable;
use UnitEnum;

/**
 * Who can sign in to the admin (owners only). Team members are invited by
 * email, choose their own password, and see only the branches they're
 * assigned to; owners see every branch.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Users';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->isOwner();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return self::canViewAny();
    }

    /**
     * Not yourself, and never the last owner.
     */
    public static function canDelete(Model $record): bool
    {
        return self::canViewAny()
            && ! $record->is(auth()->user())
            && ! ($record->isOwner() && User::where('is_owner', true)->count() <= 1);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(2)
                ->components([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(120),
                    TextInput::make('email')
                        ->email()
                        ->required()
                        ->maxLength(190)
                        ->unique(ignoreRecord: true),
                    Toggle::make('is_owner')
                        ->label('Owner')
                        ->helperText('Owners see every branch and manage branches, services, prices, settings and users.')
                        // You can't demote yourself (and lock everyone out of this page)
                        ->disabled(fn (?User $record) => $record?->is(auth()->user()))
                        ->live()
                        ->columnSpanFull(),
                    CheckboxList::make('branches')
                        ->label('Branches')
                        ->relationship('branches', 'name', fn ($query) => $query->orderBy('sort_order')->orderBy('name'))
                        ->helperText('The bookings, timeline and closures they work with.')
                        ->columns(2)
                        ->visible(fn (Get $get) => ! $get('is_owner'))
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->modifyQueryUsing(fn ($query) => $query->with('branches'))
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->description(fn (User $record) => $record->email),
                TextColumn::make('is_owner')
                    ->label('Role')
                    ->badge()
                    ->formatStateUsing(fn (bool $state) => $state ? 'Owner' : 'Branch staff')
                    ->color(fn (bool $state) => $state ? 'primary' : 'gray')
                    ->description(fn (User $record) => $record->isOwner() ? 'Every branch' : ($record->branches->pluck('name')->implode(', ') ?: 'No branch yet')),
                TextColumn::make('status')
                    ->state(fn (User $record) => $record->isPendingInvite() ? 'Invite sent' : 'Active')
                    ->badge()
                    ->color(fn (string $state) => $state === 'Active' ? 'success' : 'warning')
                    ->description(fn (User $record) => $record->isPendingInvite() ? $record->invited_at->diffForHumans() : null),
            ])
            ->recordActions([
                self::resendInviteAction(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function inviteAction(): Action
    {
        return Action::make('invite')
            ->label('Invite team member')
            ->icon(Heroicon::OutlinedUserPlus)
            ->modalDescription('They get an email with a link to choose their password. Branch staff see only the branches you pick.')
            ->modalSubmitActionLabel('Send invite')
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(120),
                TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(190)
                    ->unique(User::class, 'email'),
                CheckboxList::make('branches')
                    ->label('Branches')
                    ->options(fn () => Branch::query()->ordered()->pluck('name', 'id')->all())
                    ->required()
                    ->columns(2),
            ])
            ->action(function (array $data, Action $action) {
                try {
                    $user = app(Invitations::class)->invite($data['name'], $data['email'], auth()->user());
                    $user->branches()->sync(array_map('intval', $data['branches'] ?? []));
                } catch (Throwable $e) {
                    Notification::make()->danger()->persistent()->title('Invite not sent')->body($e->getMessage())->send();
                    $action->halt();
                }

                Notification::make()->success()->title("Invite sent to {$user->email}")->send();
            });
    }

    public static function resendInviteAction(): Action
    {
        return Action::make('resendInvite')
            ->label('Resend invite')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('gray')
            ->visible(fn (User $record) => $record->isPendingInvite())
            ->requiresConfirmation()
            ->modalDescription('Emails a new link. The old one stops working.')
            ->action(function (User $record) {
                try {
                    app(Invitations::class)->send($record, auth()->user());
                } catch (Throwable $e) {
                    Notification::make()->danger()->persistent()->title('Invite not sent')->body($e->getMessage())->send();

                    return;
                }

                Notification::make()->success()->title("New invite sent to {$record->email}")->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
