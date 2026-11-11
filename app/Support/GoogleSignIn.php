<?php

namespace App\Support;

use App\Models\SiteSetting;

/**
 * Sign in with Google for guests. The OAuth client comes from /admin →
 * Google sign-in (the secret is stored encrypted), else from
 * GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET. Until both are set, guests can
 * still send requests without signing in.
 */
class GoogleSignIn
{
    public static function enabled(): bool
    {
        [$id, $secret] = self::client();

        return filled($id) && filled($secret);
    }

    /**
     * The URL to register as an authorised redirect URI in Google Cloud.
     */
    public static function redirectUrl(): string
    {
        return route('auth.google.callback');
    }

    /**
     * Hands the client to Socialite for this request.
     */
    public static function configure(): void
    {
        [$id, $secret] = self::client();

        config([
            'services.google.client_id' => $id,
            'services.google.client_secret' => $secret,
            'services.google.redirect' => self::redirectUrl(),
        ]);
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private static function client(): array
    {
        $settings = SiteSetting::current();

        return filled($settings->google_client_id) && filled($settings->google_client_secret)
            ? [$settings->google_client_id, $settings->google_client_secret]
            : [config('services.google.client_id'), config('services.google.client_secret')];
    }
}
