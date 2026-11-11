<?php

namespace App\Filament\Pages;

use App\Models\BookingSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * The booking rules for every branch (owners only): when customers can
 * still cancel or move a booking themselves, how many unpaid holds and
 * upcoming bookings one customer can have, and the terms shown before
 * they reserve.
 */
class BookingSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Booking rules';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Booking rules';

    protected static ?string $slug = 'booking-rules';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isOwner();
    }

    public function mount(): void
    {
        $this->form->fill(BookingSetting::instance()->attributesToArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Cancelling and moving')
                    ->description('What customers can do themselves in My bookings. The team can always cancel or move a booking from the admin.')
                    ->columns(2)
                    ->components([
                        TextInput::make('cancel_cutoff_hours')
                            ->label('Cancel online up to')
                            ->suffix('hours before drop-off')
                            ->integer()
                            ->minValue(0)
                            ->maxValue(720)
                            ->required(),
                        Toggle::make('reschedule_enabled')
                            ->label('Customers can move their booking')
                            ->live(),
                        TextInput::make('reschedule_cutoff_hours')
                            ->label('Move online up to')
                            ->suffix('hours before drop-off')
                            ->integer()
                            ->minValue(0)
                            ->maxValue(720)
                            ->required()
                            ->visible(fn (Get $get) => (bool) $get('reschedule_enabled')),
                        TextInput::make('max_reschedules')
                            ->label('Times a booking can be moved')
                            ->integer()
                            ->minValue(1)
                            ->maxValue(10)
                            ->required()
                            ->visible(fn (Get $get) => (bool) $get('reschedule_enabled')),
                    ]),
                Section::make('Per customer')
                    ->description('Keeps one Google account from holding every bay. Set 0 for no limit.')
                    ->columns(2)
                    ->components([
                        TextInput::make('max_unpaid_holds_per_guest')
                            ->label('Unpaid holds at a time')
                            ->integer()
                            ->minValue(0)
                            ->maxValue(20)
                            ->required(),
                        TextInput::make('max_upcoming_per_guest')
                            ->label('Upcoming bookings at a time')
                            ->integer()
                            ->minValue(0)
                            ->maxValue(50)
                            ->required(),
                    ]),
                Section::make('Terms')
                    ->components([
                        Textarea::make('policy_text')
                            ->label('Shown before customers reserve')
                            ->rows(6)
                            ->maxLength(4000)
                            ->placeholder('Refunds of the reservation fee, late arrivals, pickup…')
                            ->helperText('Your own terms, in your words. Leave blank to show none.'),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([$this->getFormContentComponent()]);
    }

    public function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler('save')
            ->footer([
                Actions::make([
                    Action::make('save')
                        ->label('Save rules')
                        ->submit('save')
                        ->keyBindings(['mod+s']),
                ]),
            ]);
    }

    public function save(): void
    {
        BookingSetting::instance()->update($this->form->getState());

        Notification::make()->success()->title('Booking rules saved')->send();
    }
}
