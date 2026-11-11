<?php

namespace App\Providers\Filament;

use Filament\Actions\Action;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    /**
     * A neutral graphite until 826's own colours are set in the design
     * pass: filled buttons at 600 under white text, links at 700.
     *
     * @var array<int, string>
     */
    public const BRAND = [
        50 => '#f6f6f7',
        100 => '#e9eaec',
        200 => '#d3d5d9',
        300 => '#b0b4bb',
        400 => '#878c96',
        500 => '#6a707b',
        600 => '#4c515a',
        700 => '#3d4148',
        800 => '#2e3136',
        900 => '#1f2124',
        950 => '#121315',
    ];

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->passwordReset()
            // Name, email and password, from the user menu
            ->profile(isSimple: false)
            ->brandName('826 Auto Aesthetic & Protection')
            ->brandLogo(fn () => view('filament.brand'))
            ->brandLogoHeight('2.5rem')
            ->favicon(asset('favicon.ico'))
            ->colors([
                'primary' => self::BRAND,
                'gray' => Color::Stone,
            ])
            // Fonts are self-hosted in the theme (@fontsource), not loaded from a CDN
            ->font('Archivo Variable', provider: LocalFontProvider::class)
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->navigationGroups([
                NavigationGroup::make('Bookings'),
                NavigationGroup::make('Shop'),
                NavigationGroup::make('Site'),
                NavigationGroup::make('Settings'),
            ])
            ->userMenuItems([
                Action::make('viewSite')
                    ->label('View site')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn () => url('/'), shouldOpenInNewTab: true),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
