<?php

namespace App\Filament\Pages;

use App\Models\PaymentSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
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
 * The reservation fee (owners only): on or off, how much, how long a slot
 * is held for the customer to pay, and the main GCash and Maya accounts
 * (a branch can have its own instead). Customers pay in their own app and
 * upload a screenshot for the branch to check.
 */
class PaymentSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Payments';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Payments';

    protected static ?string $slug = 'payment-settings';

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
        $this->form->fill(PaymentSetting::instance()->attributesToArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Reservation fee')
                    ->description('Off: a reservation is confirmed the moment it’s made. On: the slot is held while the customer pays by GCash or Maya and uploads a screenshot; you check it and confirm. Nothing is charged on the site.')
                    ->columns(2)
                    ->components([
                        Toggle::make('reservation_fee_enabled')
                            ->label('Ask for a reservation fee')
                            ->live()
                            ->columnSpanFull(),
                        Select::make('amount_rule')
                            ->label('The fee is')
                            ->options(PaymentSetting::RULES)
                            ->default('fixed')
                            ->required()
                            ->live()
                            ->visible(fn (Get $get) => (bool) $get('reservation_fee_enabled')),
                        TextInput::make('amount_value')
                            ->label(fn (Get $get) => $get('amount_rule') === 'percent' ? 'Percent of the estimate' : 'Amount')
                            ->prefix(fn (Get $get) => $get('amount_rule') === 'fixed' ? '₱' : null)
                            ->suffix(fn (Get $get) => $get('amount_rule') === 'percent' ? '%' : null)
                            ->integer()
                            ->minValue(1)
                            ->maxValue(fn (Get $get) => $get('amount_rule') === 'percent' ? 99 : 1_000_000)
                            ->required(fn (Get $get) => $get('reservation_fee_enabled') && in_array($get('amount_rule'), ['fixed', 'percent'], true))
                            ->visible(fn (Get $get) => $get('reservation_fee_enabled') && in_array($get('amount_rule'), ['fixed', 'percent'], true))
                            ->helperText('Never more than the estimate. Bookings already made keep their fee.'),
                        TextInput::make('hold_minutes')
                            ->label('Hold the slot for')
                            ->suffix('minutes')
                            ->integer()
                            ->minValue(PaymentSetting::MIN_HOLD_MINUTES)
                            ->maxValue(PaymentSetting::MAX_HOLD_MINUTES)
                            ->default(30)
                            ->required()
                            ->helperText('If no screenshot comes in by then, the slot opens up for others.')
                            ->visible(fn (Get $get) => (bool) $get('reservation_fee_enabled')),
                        Textarea::make('instructions')
                            ->label('Payment notes for customers')
                            ->rows(3)
                            ->maxLength(1000)
                            ->placeholder('Put your booking reference in the message')
                            ->helperText('Optional. Shown under the QR codes.')
                            ->columnSpanFull()
                            ->visible(fn (Get $get) => (bool) $get('reservation_fee_enabled')),
                    ]),
                $this->method('gcash', 'GCash'),
                $this->method('maya', 'Maya'),
            ]);
    }

    private function method(string $key, string $label): Section
    {
        return Section::make($label)
            ->columns(3)
            ->components([
                Toggle::make("{$key}_enabled")
                    ->label("Accept {$label}")
                    ->helperText('Customers only see it when it’s on and has a QR code or a number. A branch with its own account uses that instead.')
                    ->columnSpanFull(),
                FileUpload::make("{$key}_qr")
                    ->label('QR code')
                    ->image()
                    ->disk(PaymentSetting::DISK)
                    ->directory('payments')
                    ->visibility('public')
                    ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                    ->maxSize(5120)
                    ->imagePreviewHeight('180')
                    ->helperText("Your {$label} QR (from the app: “Receive” or “QR”). Kept exactly as uploaded so it scans."),
                TextInput::make("{$key}_name")
                    ->label('Account name')
                    ->maxLength(120)
                    ->helperText('As the customer sees it before paying.'),
                TextInput::make("{$key}_number")
                    ->label("{$label} number")
                    ->tel()
                    ->maxLength(40),
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
                        ->label('Save payments')
                        ->submit('save')
                        ->keyBindings(['mod+s']),
                ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        if (isset($data['amount_rule']) && ! in_array($data['amount_rule'], ['fixed', 'percent'], true)) {
            $data['amount_value'] = null;
        }

        PaymentSetting::instance()->update($data);

        Notification::make()->success()->title('Payments saved')->send();
    }
}
