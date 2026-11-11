import { type ReactNode, type RefObject, useId, useRef } from 'react';
import { createPortal } from 'react-dom';

import useModalLayer from '../hooks/useModalLayer';
import { Close } from './Icons';

type Props = {
    title: string;
    onClose: () => void;
    children: ReactNode;
    /** Shown under the title */
    description?: ReactNode;
    /** What gets focus on open; else the close button */
    initialFocus?: RefObject<HTMLElement | null>;
    className?: string;
};

/**
 * A dialog over a dimmed page: closes on Escape, the close button or a
 * click on the backdrop. On phones it rises from the bottom.
 */
export default function Modal({ title, onClose, children, description, initialFocus, className = 'sm:max-w-2xl' }: Props) {
    const panel = useRef<HTMLDivElement>(null);
    const closeButton = useRef<HTMLButtonElement>(null);
    const titleId = useId();

    useModalLayer(panel, { onClose, initialFocus: initialFocus ?? closeButton });

    // On <body>, so no transformed ancestor can trap the fixed overlay
    return createPortal(
        <div
            className="fade-in fixed inset-0 z-[60] flex items-end justify-center bg-scrim sm:items-center sm:p-6"
            onPointerDown={(event) => event.target === event.currentTarget && onClose()}
        >
            <div
                ref={panel}
                role="dialog"
                aria-modal="true"
                aria-labelledby={titleId}
                tabIndex={-1}
                className={`modal-in flex max-h-[min(90dvh,52rem)] w-full flex-col overflow-hidden rounded-t-card border border-line bg-surface-2 text-text shadow-pop outline-none sm:rounded-card ${className}`}
            >
                <div className="flex items-start justify-between gap-4 border-b border-line px-5 py-4 sm:px-7">
                    <div className="min-w-0 pt-1.5">
                        <h2 id={titleId} className="text-xl leading-snug font-bold">
                            {title}
                        </h2>
                        {description && <div className="mt-1 text-sm text-muted">{description}</div>}
                    </div>
                    <button ref={closeButton} type="button" onClick={onClose} className="press -mr-2 grid size-11 shrink-0 place-items-center rounded-full hover:bg-surface-3" aria-label="Close">
                        <Close className="size-5" />
                    </button>
                </div>
                <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain px-5 py-5 pb-[max(1.25rem,env(safe-area-inset-bottom))] sm:px-7 sm:py-6">{children}</div>
            </div>
        </div>,
        document.body,
    );
}
