<?php

namespace App\Filament\Pages;

use App\Models\MailSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Mail;
use Throwable;
use UnitEnum;

/**
 * 826's mailbox login (owners only): booking alerts and invites are sent
 * from it. Alerts go to "Send booking alerts to", or to the mailbox itself.
 */
class EmailSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAtSymbol;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Email settings';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'Email settings';

    protected static ?string $slug = 'email-settings';

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
        $this->form->fill(MailSetting::instance()->attributesToArray());
    }

    public function form(Schema $schema): Schema
    {
        $settings = MailSetting::instance();

        return $schema
            ->statePath('data')
            ->components([
                Section::make('Mailbox')
                    ->description('The email account the site sends from. New booking requests are emailed to it, or to the address below.')
                    ->columns(2)
                    ->components([
                        TextInput::make('address')
                            ->label('Email address')
                            ->email()
                            ->required()
                            ->maxLength(190),
                        TextInput::make('from_name')
                            ->label('Sender name')
                            ->placeholder('826 Auto Aesthetic & Protection')
                            ->maxLength(80)
                            ->helperText('What people see as the sender.'),
                        TextInput::make('password')
                            ->label('Mailbox password')
                            ->password()
                            ->revealable()
                            ->autocomplete('new-password')
                            ->required(! $settings->isConfigured())
                            ->maxLength(190)
                            ->helperText($settings->isConfigured()
                                ? 'Saved (hidden). Leave blank to keep it, or type a new one if you changed it.'
                                : 'The password of the email account (Hostinger: hPanel → Emails). Stored encrypted.')
                            ->columnSpanFull(),
                        TextInput::make('notify_email')
                            ->label('Send booking alerts to')
                            ->email()
                            ->maxLength(190)
                            ->helperText('Optional, e.g. a Gmail the team checks. Leave blank to send them to the mailbox above.')
                            ->columnSpanFull(),
                    ]),
                Section::make('Server')
                    ->description('Hostinger’s, already filled in. Only change these if the mailbox moves to another provider.')
                    ->collapsed()
                    ->columns(2)
                    ->components([
                        TextInput::make('smtp_host')->label('Outgoing (SMTP) server')->required()->maxLength(190),
                        Select::make('smtp_port')->label('SMTP port')->options([465 => '465 (SSL)', 587 => '587 (STARTTLS)'])->required()->native(false),
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
                        ->label('Save')
                        ->submit('save')
                        ->keyBindings(['mod+s']),
                    Action::make('test')
                        ->label('Save and send a test')
                        ->color('gray')
                        ->icon(Heroicon::OutlinedSignal)
                        ->action('test'),
                ]),
            ]);
    }

    public function save(): void
    {
        $this->store();

        Notification::make()->success()->title('Email settings saved')->send();
    }

    /**
     * Sends an email to the mailbox itself: it should arrive a moment later.
     */
    public function test(): void
    {
        $settings = $this->store();

        try {
            MailSetting::applyToConfig();
            Mail::purge();
            Mail::raw('This is a test from the '.config('app.name').' admin. If you can read it, sending works.', fn ($message) => $message
                ->to($settings->address)
                ->subject(config('app.name').' admin: test email'));
        } catch (Throwable $e) {
            Notification::make()->danger()->persistent()
                ->title('Sending failed')
                ->body("Check the address and password. ({$e->getMessage()})")
                ->send();

            return;
        }

        Notification::make()->success()->persistent()
            ->title('Email works')
            ->body("Sent a test email to {$settings->address}. It should arrive in a minute.")
            ->send();
    }

    private function store(): MailSetting
    {
        $data = $this->form->getState();
        $settings = MailSetting::instance();

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $settings->update($data);
        $this->form->fill([...$settings->attributesToArray(), 'password' => null]);

        return $settings;
    }
}
