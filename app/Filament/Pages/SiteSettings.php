<?php

namespace App\Filament\Pages;

use App\Filament\Support\ImageUpload;
use App\Models\SiteSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * 826's tagline, intro, Home page picture and main contact links (owners
 * only). Each branch keeps its own address, hours and phone.
 */
class SiteSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Site settings';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Site settings';

    protected static ?string $slug = 'site-settings';

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
        $this->form->fill(SiteSetting::instance()->attributesToArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('About 826')
                    ->columns(3)
                    ->components([
                        Group::make([
                            TextInput::make('tagline')
                                ->maxLength(120)
                                ->helperText('One short line under the name.'),
                            Textarea::make('intro')
                                ->rows(4)
                                ->maxLength(400)
                                ->helperText('A few sentences about 826, for the Home page, link previews and search engines.'),
                        ])->columnSpan(2),
                        Group::make([
                            ImageUpload::make('hero_image', 'site')
                                ->label('Home page picture')
                                ->helperText('The big picture at the top of the Home page.'),
                            ImageUpload::make('og_image', 'site')
                                ->label('Link-preview picture')
                                ->helperText('Optional. Used when a page is shared on Facebook or Messenger. Use a 1200×630 picture.'),
                        ])->columnSpan(1),
                    ]),
                Section::make('Main contact')
                    ->description('Shown in the footer and when a branch has no number of its own. Leave a field blank to hide it.')
                    ->columns(2)
                    ->components([
                        TextInput::make('phone')
                            ->label('Mobile (call or text)')
                            ->tel()
                            ->maxLength(40),
                        TextInput::make('contact_email')
                            ->label('Contact email')
                            ->email()
                            ->maxLength(190),
                        $this->linkInput('facebook_url', 'Facebook page', 'https://www.facebook.com/…'),
                        $this->linkInput('instagram_url', 'Instagram', 'https://www.instagram.com/…'),
                        $this->linkInput('messenger_url', 'Messenger link', 'https://m.me/…'),
                    ]),
            ]);
    }

    private function linkInput(string $name, string $label, string $placeholder): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->placeholder($placeholder)
            ->url()
            ->startsWith(['https://'])
            ->maxLength(255);
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
                        ->label('Save settings')
                        ->submit('save')
                        ->keyBindings(['mod+s']),
                ]),
            ]);
    }

    public function save(): void
    {
        SiteSetting::instance()->update($this->form->getState());

        Notification::make()->success()->title('Site settings saved')->body('The site is updated.')->send();
    }
}
