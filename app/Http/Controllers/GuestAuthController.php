<?php

namespace App\Http\Controllers;

use App\Models\Guest;
use App\Support\GoogleSignIn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

/**
 * Sign in with Google for customers (the "guest" guard). The admin's team
 * signs in to /admin separately; a guest account can't open it.
 */
class GuestAuthController extends Controller
{
    private const RETURN_KEY = 'guest.return';

    /**
     * GET /auth/google?return=/book?branch=…: off to Google, remembering
     * where to come back to.
     */
    public function redirect(Request $request): SymfonyRedirect
    {
        abort_unless(GoogleSignIn::enabled(), 404);

        $request->session()->put(self::RETURN_KEY, self::safeReturn($request->query('return')));
        GoogleSignIn::configure();

        // Always show Google's account chooser, so "Switch account" really switches
        return Socialite::driver('google')
            ->scopes(['openid', 'email', 'profile'])
            ->with(['prompt' => 'select_account'])
            ->redirect();
    }

    /**
     * GET /auth/google/callback: the guest is created or updated from their
     * Google profile, signed in, and sent back where they were.
     */
    public function callback(Request $request): RedirectResponse
    {
        abort_unless(GoogleSignIn::enabled(), 404);

        $return = self::safeReturn($request->session()->pull(self::RETURN_KEY));

        try {
            GoogleSignIn::configure();
            $profile = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            Log::info("Google sign-in failed: {$e->getMessage()}");

            return redirect(url($return).(str_contains($return, '?') ? '&' : '?').'signin=failed');
        }

        if (blank($profile->getId()) || blank($profile->getEmail())) {
            return redirect(url($return).(str_contains($return, '?') ? '&' : '?').'signin=failed');
        }

        $guest = Guest::updateOrCreate(
            ['google_id' => (string) $profile->getId()],
            [
                'name' => $profile->getName() ?: $profile->getEmail(),
                'email' => $profile->getEmail(),
                'avatar_url' => $profile->getAvatar(),
                'last_signed_in_at' => now(),
            ],
        );

        Auth::guard('guest')->login($guest, remember: true);
        $request->session()->regenerate();

        return redirect(url($return));
    }

    /**
     * GET /auth/local: local development only, while Google sign-in isn't
     * set up. Signs in a test customer so the booking flow can be tried.
     */
    public function local(Request $request): RedirectResponse
    {
        abort_unless(app()->isLocal() && ! GoogleSignIn::enabled(), 404);

        $guest = Guest::firstOrCreate(
            ['google_id' => 'local-tester'],
            ['name' => 'Local Tester', 'email' => 'tester@826auto.test', 'last_signed_in_at' => now()],
        );

        Auth::guard('guest')->login($guest, remember: true);
        $request->session()->regenerate();

        return redirect(url(self::safeReturn($request->query('return'))));
    }

    /**
     * POST /auth/logout
     */
    public function logout(Request $request): Response
    {
        Auth::guard('guest')->logout();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    /**
     * Only paths on this site ("/book?date=…"), never another host.
     */
    public static function safeReturn(mixed $path): string
    {
        $path = is_string($path) ? trim($path) : '';

        if ($path === '' || ! str_starts_with($path, '/') || str_starts_with($path, '//') || str_contains($path, '\\') || preg_match('/[\x00-\x1f]/', $path)) {
            return '/';
        }

        return $path;
    }
}
