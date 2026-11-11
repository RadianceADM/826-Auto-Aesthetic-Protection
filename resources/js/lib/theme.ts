import { useSyncExternalStore } from 'react';

export type Theme = 'light' | 'dark';

/** Where the visitor's own choice is kept (the inline script in site.blade.php reads it before anything paints) */
const KEY = 'app826-theme';

/** The browser's bar: the page's own background in each mode */
const BAR_COLOUR: Record<Theme, string> = { light: '#f3f4f3', dark: '#0f1113' };

/** Until a visitor presses the button: dark, the brand's choice (not the device's setting) */
const DEFAULT_THEME: Theme = 'dark';

const listeners = new Set<() => void>();

function chosen(): Theme | null {
    try {
        const value = window.localStorage.getItem(KEY);

        return value === 'light' || value === 'dark' ? value : null;
    } catch {
        return null;
    }
}

let current: Theme = (document.documentElement.dataset.theme as Theme | undefined) ?? chosen() ?? DEFAULT_THEME;

/**
 * Puts a mode on <html> at once: no colour fades (transitions are held for
 * a frame), and the browser's own colours (scrollbars, form controls, the
 * bar) follow.
 */
function apply(theme: Theme) {
    const root = document.documentElement;

    current = theme;
    root.classList.add('theme-switching');
    root.dataset.theme = theme;
    root.style.colorScheme = theme;
    document.querySelector('meta[name="theme-color"]')?.setAttribute('content', BAR_COLOUR[theme]);
    void root.offsetWidth;
    requestAnimationFrame(() => root.classList.remove('theme-switching'));
    listeners.forEach((listener) => listener());
}

// The bar's colour for the mode the page opened in
document.querySelector('meta[name="theme-color"]')?.setAttribute('content', BAR_COLOUR[current]);

// Chosen in another tab: this one follows
window.addEventListener('storage', (event) => {
    if (event.key === KEY) {
        apply(chosen() ?? DEFAULT_THEME);
    }
});

/**
 * The one button: the other mode, remembered for every page and visit.
 */
export function toggleTheme(): void {
    const next: Theme = current === 'dark' ? 'light' : 'dark';

    try {
        window.localStorage.setItem(KEY, next);
    } catch {
        // Private browsing without storage: it still switches for this visit
    }

    apply(next);
}

export function useTheme(): Theme {
    return useSyncExternalStore(
        (listener) => {
            listeners.add(listener);

            return () => listeners.delete(listener);
        },
        () => current,
        () => DEFAULT_THEME,
    );
}
