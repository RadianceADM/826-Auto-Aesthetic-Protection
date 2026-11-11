import type { Guest } from '../types';
import { site } from './site';

/** Laravel's token, from the meta tag in site.blade.php: every POST sends it */
export const csrfToken = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

export const guest: Guest | null = site.auth.guest;

/**
 * Where signing in starts: Google when it's set up, else (local
 * development only) the test customer. Null when neither is there.
 */
const signInBase: string | null = site.auth.googleEnabled ? site.auth.signInUrl : site.auth.localSignInUrl;

/** Whether this copy signs in the local test customer instead of Google */
export const localSignIn = !site.auth.googleEnabled && Boolean(site.auth.localSignInUrl);

/** A reservation or request needs a signed-in customer (a demo saves nothing, so it doesn't) */
export const signInNeeded = !guest && !site.demo;

/** Signing in is possible on this copy */
export const canSignIn = signInBase !== null;

/**
 * Sign-in, coming back to `path` (site-relative, without site.base:
 * "/book?step=7").
 */
export function signInHref(path: string): string {
    const url = new URL(signInBase ?? site.auth.signInUrl, window.location.origin);
    url.searchParams.set('return', path.startsWith('/') ? path : `/${path}`);

    return url.toString();
}

/**
 * Ends the session. Whatever the answer, the page is reloaded or left
 * after it, so it shows the real state.
 */
export async function signOut(): Promise<void> {
    try {
        await fetch(site.auth.signOutUrl, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
            credentials: 'same-origin',
        });
    } catch {
        // Offline: the reload shows whether it went through
    }
}

/**
 * After signing out: My bookings is private, so home; anywhere else, the
 * same page again.
 */
export function afterSignOut(pathname: string): void {
    if (pathname.startsWith('/bookings')) {
        window.location.assign(`${site.base}/`);
        return;
    }

    window.location.reload();
}

/** "Juan dela Cruz" → "JC", "juan" → "J" */
export function initials(name: string): string {
    const words = name.trim().split(/\s+/).filter(Boolean);

    if (words.length === 0) {
        return '?';
    }

    const first = words[0][0];
    const last = words.length > 1 ? words[words.length - 1][0] : '';

    return `${first}${last}`.toUpperCase();
}
