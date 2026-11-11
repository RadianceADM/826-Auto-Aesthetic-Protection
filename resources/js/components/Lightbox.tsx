import { type MouseEvent, useCallback, useEffect, useLayoutEffect, useRef } from 'react';
import { createPortal } from 'react-dom';

import useModalLayer from '../hooks/useModalLayer';
import { photoAlt } from '../lib/site';
import type { Photo } from '../types';
import { ChevronLeft, ChevronRight, Close } from './Icons';

type Props = {
    photos: Photo[];
    index: number;
    onClose: () => void;
    onChange: (index: number) => void;
};

/** How far either side of the open photo the big files load */
const NEAR = 2;

/**
 * One photo at a time over a dark screen. The photos sit side by side and
 * snap, so on a phone the next one follows your finger; the arrows (and the
 * arrow keys) jump straight to it.
 */
export default function Lightbox({ photos, index, onClose, onChange }: Props) {
    const dialog = useRef<HTMLDivElement>(null);
    const closeRef = useRef<HTMLButtonElement>(null);
    const track = useRef<HTMLDivElement>(null);
    /** The photo the track is resting on, so a swipe and the arrows don't fight */
    const shown = useRef(index);
    const photo = photos[index];
    const count = photos.length;

    const go = useCallback((step: number) => onChange((index + step + count) % count), [index, count, onChange]);

    // Focus in and kept in, Escape closes, the page holds still, focus back on close
    useModalLayer(dialog, { onClose, initialFocus: closeRef, history: true, gutter: '#0b0c0d' });

    useEffect(() => {
        const onKey = (event: KeyboardEvent) => {
            if (event.key === 'ArrowRight') go(1);
            if (event.key === 'ArrowLeft') go(-1);
        };

        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    }, [go]);

    // An arrow, a key or the opening photo: jump the track there (a swipe has already moved it)
    useLayoutEffect(() => {
        const element = track.current;

        if (element && (shown.current !== index || Math.round(element.scrollLeft / Math.max(1, element.clientWidth)) !== index)) {
            shown.current = index;
            element.scrollTo({ left: index * element.clientWidth, behavior: 'instant' });
        }
    }, [index]);

    // Turning the phone: stay on the same photo
    useEffect(() => {
        const onResize = () => track.current?.scrollTo({ left: shown.current * track.current.clientWidth, behavior: 'instant' });
        window.addEventListener('resize', onResize);

        return () => window.removeEventListener('resize', onResize);
    }, []);

    const onScroll = () => {
        const element = track.current;

        if (!element || element.clientWidth === 0) {
            return;
        }

        const resting = Math.min(count - 1, Math.max(0, Math.round(element.scrollLeft / element.clientWidth)));

        if (resting !== shown.current) {
            shown.current = resting;
            onChange(resting);
        }
    };

    // A click beside the photo (not on it) closes
    const closeOnBackdrop = (event: MouseEvent) => event.target === event.currentTarget && onClose();

    if (!photo) {
        return null;
    }

    const navButton = 'press absolute top-1/2 z-10 grid size-12 -translate-y-1/2 place-items-center rounded-full bg-white/10 text-white hover:bg-white/20';

    // On <body>, so no transformed ancestor can trap the fixed overlay
    return createPortal(
        <div
            ref={dialog}
            className="fixed inset-0 z-[70] flex flex-col bg-[#0b0c0d]/96 text-white max-sm:bg-[#0b0c0d] pt-[env(safe-area-inset-top)] pr-[env(safe-area-inset-right)] pb-[env(safe-area-inset-bottom)] pl-[env(safe-area-inset-left)]"
            role="dialog"
            aria-modal="true"
            aria-label="Photo viewer"
            onClick={closeOnBackdrop}
        >
            <div className="flex items-center justify-between gap-4 px-4 py-3 sm:px-8">
                <p className="eyebrow text-white/75 tabular" aria-live="polite">
                    {index + 1} / {count}
                </p>
                <button ref={closeRef} type="button" onClick={onClose} className="press grid size-11 place-items-center rounded-full bg-white/10 text-white hover:bg-white/20" aria-label="Close">
                    <Close />
                </button>
            </div>

            <div className="relative min-h-0 flex-1">
                <div ref={track} onScroll={onScroll} className="no-scrollbar flex size-full snap-x snap-mandatory overflow-x-auto overscroll-contain">
                    {photos.map((slide, position) => (
                        <div
                            key={slide.id}
                            className="flex h-full w-full shrink-0 snap-center snap-always items-center justify-center px-4 pb-6 sm:px-24"
                            onClick={closeOnBackdrop}
                            aria-hidden={position !== index}
                        >
                            {Math.abs(position - index) <= NEAR && (
                                <img src={slide.full} alt={photoAlt(slide)} decoding="async" className="max-h-full max-w-full object-contain" />
                            )}
                        </div>
                    ))}
                </div>

                {count > 1 && (
                    <>
                        <button type="button" onClick={() => go(-1)} className={`${navButton} left-4 hidden sm:grid`} aria-label="Previous photo">
                            <ChevronLeft />
                        </button>
                        <button type="button" onClick={() => go(1)} className={`${navButton} right-4 hidden sm:grid`} aria-label="Next photo">
                            <ChevronRight />
                        </button>
                    </>
                )}
            </div>

            {photo.caption && <p className="px-5 pb-6 text-center text-lg text-white">{photo.caption}</p>}
            {count > 1 && <p className="eyebrow pb-5 text-center text-white/75 sm:hidden">Swipe for the next one</p>}
        </div>,
        document.body,
    );
}
