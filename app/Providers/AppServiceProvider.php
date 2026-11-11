<?php

namespace App\Providers;

use App\Filament\Support\PanelDefaults;
use App\Models\MailSetting;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->isProduction()) {
            // Every generated URL (emails, password resets, assets) uses APP_URL,
            // whatever Host header the request came with
            URL::forceRootUrl(config('app.url'));
            URL::forceScheme('https');
        }

        // Mail goes out through the mailbox set in Email settings, once it has a password
        $this->app->afterResolving('mail.manager', fn () => MailSetting::applyToConfig());

        // Choosing a password from an invite (or reset) link proves the email is theirs
        Event::listen(PasswordReset::class, function (PasswordReset $event) {
            $event->user->forceFill(['email_verified_at' => $event->user->email_verified_at ?? now()])->save();
        });

        // Filament's own dropdowns and date pickers, and an icon on every button
        PanelDefaults::apply();

        $this->limitRequests();
    }

    /**
     * Each kind of request has its own budget per visitor. Plain
     * "throttle:5,1" shares one counter between every route, so browsing
     * the calendar could use up the budget for sending a request.
     */
    private function limitRequests(): void
    {
        $by = fn (string $name) => fn (Request $request) => $request->user('guest')?->getAuthIdentifier()
            ? "{$name}|guest:".$request->user('guest')->getAuthIdentifier()
            : "{$name}|".$request->ip();

        RateLimiter::for('availability', fn (Request $request) => Limit::perMinute(120)->by($by('availability')($request)));
        RateLimiter::for('bookings', fn (Request $request) => Limit::perMinute(5)->by($by('bookings')($request)));
        RateLimiter::for('my-bookings', fn (Request $request) => Limit::perMinute(60)->by($by('my-bookings')($request)));
        RateLimiter::for('payments', fn (Request $request) => Limit::perMinute(6)->by($by('payments')($request)));
        RateLimiter::for('google', fn (Request $request) => Limit::perMinute(20)->by('google|'.$request->ip()));
    }
}
