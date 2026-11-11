import { lazy, type ComponentType } from 'react';

// Code splitting (RadianceADM/blueprints PERFORMANCE_BLUEPRINT.md §5): a page that
// isn't a landing page ships in its own file. The first page loads without it, and
// the browser fetches it once it's idle, so moving to it later doesn't wait.

type Loader = () => Promise<{ default: ComponentType }>;

const loaders: Loader[] = [];
const RELOADED = 'lazy-page-reloaded';

/**
 * After a deploy, an open tab still asks for the old file names, which are gone.
 * Load the page fresh once (that brings the new names); a second miss is a real error.
 */
function reloadOnce(load: Loader): Loader {
    return () =>
        load().then(
            (page) => {
                sessionStorage.removeItem(RELOADED);
                return page;
            },
            (error) => {
                if (sessionStorage.getItem(RELOADED)) {
                    throw error;
                }
                sessionStorage.setItem(RELOADED, '1');
                window.location.reload();
                return new Promise<never>(() => {});
            },
        );
}

export function lazyPage(load: Loader) {
    loaders.push(load);
    return lazy(reloadOnce(load));
}

/** Fetch every lazy page once the first one has loaded and the browser is idle. */
export function preloadPages() {
    const connection = (navigator as Navigator & { connection?: { saveData?: boolean } }).connection;
    if (connection?.saveData) {
        return;
    }

    const fetchAll = () => loaders.forEach((load) => load().catch(() => {}));
    const whenIdle = () =>
        'requestIdleCallback' in window ? requestIdleCallback(fetchAll, { timeout: 3000 }) : setTimeout(fetchAll, 1500);

    if (document.readyState === 'complete') {
        whenIdle();
    } else {
        window.addEventListener('load', whenIdle, { once: true });
    }
}
