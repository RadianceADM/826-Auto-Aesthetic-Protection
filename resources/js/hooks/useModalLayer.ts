import { type RefObject, useEffect, useRef, useSyncExternalStore } from 'react';

type Options = {
    onClose: () => void;
    /** What gets focus on open; else whatever inside is marked data-autofocus, else the first focusable thing */
    initialFocus?: RefObject<HTMLElement | null>;
    /** Where focus goes on close; else back where it was on open */
    returnFocus?: RefObject<HTMLElement | null>;
    active?: boolean;
    /** The phone's Back gesture (and the browser's Back button) closes it, instead of leaving the page */
    history?: boolean;
    /** Hold the page still behind it (default). Off for a desktop popover, which scrolls along with its field */
    lockScroll?: boolean;
    /** The colour behind the scrollbar's space while the page is locked, to match what covers the page */
    gutter?: string;
};

const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), iframe, [tabindex]:not([tabindex="-1"])';

/** The open layers, newest last: only the top one answers Escape and Tab */
const layers: symbol[] = [];
let locks = 0;
const gutters: string[] = [];

/** Layers with a history entry of their own, newest last */
type HistoryLayer = { depth: number; close: () => void; closedByBack: boolean };
const historyLayers: HistoryLayer[] = [];
/** history.back() calls of ours still on their way (a layer closed by its own button) */
let pendingBacks = 0;

/** Whatever wants to know when a layer opens or closes (the bars at the bottom hide) */
const watchers = new Set<() => void>();
const announce = () => watchers.forEach((watcher) => watcher());

// One listener for every layer: Back closes the layers above the entry it lands on
if (typeof window !== 'undefined') {
    window.addEventListener('popstate', (event) => {
        if (pendingBacks > 0) {
            pendingBacks--;
            announce();
            return;
        }

        const depth = Number((event.state as { app826Layer?: number } | null)?.app826Layer ?? 0);

        [...historyLayers].reverse().forEach((layer) => {
            if (layer.depth > depth) {
                layer.closedByBack = true;
                layer.close();
            }
        });
    });
}

/**
 * True while any dialog, sheet, popover or the lightbox is open, and until
 * a closed layer's history entry is gone.
 */
export function useLayerOpen(): boolean {
    return useSyncExternalStore(
        (onChange) => {
            watchers.add(onChange);

            return () => watchers.delete(onChange);
        },
        () => layers.length > 0 || pendingBacks > 0,
        () => false,
    );
}

function focusables(container: HTMLElement): HTMLElement[] {
    return [...container.querySelectorAll<HTMLElement>(FOCUSABLE)].filter((element) => element.tabIndex >= 0 && element.getClientRects().length > 0 && !element.closest('[inert]'));
}

function paintGutter() {
    document.documentElement.style.backgroundColor = gutters[gutters.length - 1] ?? '';
}

/**
 * What every dialog, popover and the lightbox need: focus moves in and
 * stays in (Tab wraps), Escape closes, the page behind stops scrolling,
 * and focus goes back to where it was on close. Layers stack, so a
 * lightbox opened over the photo tour closes on its own. With `history`,
 * Back closes it too.
 */
export default function useModalLayer(
    ref: RefObject<HTMLElement | null>,
    { onClose, initialFocus, returnFocus, active = true, history = false, lockScroll = true, gutter }: Options,
): { keepFocus: () => void } {
    const close = useRef(onClose);
    const restoreFocus = useRef(true);

    useEffect(() => {
        close.current = onClose;
    }, [onClose]);

    useEffect(() => {
        if (!active) {
            return;
        }

        const layer = Symbol('layer');
        restoreFocus.current = true;
        const previous = document.activeElement instanceof HTMLElement ? document.activeElement : null;
        const root = document.documentElement;

        layers.push(layer);

        if (lockScroll && locks++ === 0) {
            root.style.overflow = 'hidden';
        }

        if (lockScroll && gutter) {
            gutters.push(gutter);
            paintGutter();
        }

        // Its own entry in the history, so Back lands on the page under it
        let entry: HistoryLayer | null = null;

        if (history) {
            entry = { depth: historyLayers.length + 1, close: () => close.current(), closedByBack: false };
            historyLayers.push(entry);
            window.history.pushState({ ...(window.history.state ?? {}), app826Layer: entry.depth }, '');
        }

        announce();

        const container = ref.current;
        // Something inside can ask for it (the calendar's date); else the first thing to tab to
        const target = initialFocus?.current ?? container?.querySelector<HTMLElement>('[data-autofocus]') ?? (container ? focusables(container)[0] : null) ?? container;
        target?.focus({ preventScroll: true });

        const onKey = (event: KeyboardEvent) => {
            if (layers[layers.length - 1] !== layer || !ref.current) {
                return;
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                close.current();
                return;
            }

            if (event.key !== 'Tab') {
                return;
            }

            const items = focusables(ref.current);

            if (items.length === 0) {
                event.preventDefault();
                ref.current.focus();
                return;
            }

            const first = items[0];
            const last = items[items.length - 1];
            const current = document.activeElement;
            const inside = current instanceof Node && ref.current.contains(current);

            if (event.shiftKey && (current === first || !inside)) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && (current === last || !inside)) {
                event.preventDefault();
                first.focus();
            }
        };

        document.addEventListener('keydown', onKey);

        return () => {
            document.removeEventListener('keydown', onKey);
            layers.splice(layers.indexOf(layer), 1);

            if (lockScroll && --locks === 0) {
                root.style.overflow = '';
            }

            if (lockScroll && gutter) {
                gutters.splice(gutters.lastIndexOf(gutter), 1);
                paintGutter();
            }

            if (entry) {
                historyLayers.splice(historyLayers.indexOf(entry), 1);

                // Closed by its own button or Escape while its entry is still the current one: step back over it.
                // Not after Back (the entry is gone already) or after a link moved on to another page.
                const current = Number((window.history.state as { app826Layer?: number } | null)?.app826Layer ?? 0);

                if (!entry.closedByBack && current === entry.depth) {
                    pendingBacks++;
                    window.history.back();
                }
            }

            announce();

            const back = returnFocus?.current ?? previous;

            if (restoreFocus.current && back?.isConnected) {
                back.focus({ preventScroll: true });
            }
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [active]);

    // A click somewhere else on the page closed it: focus stays where the click put it
    return { keepFocus: () => (restoreFocus.current = false) };
}
