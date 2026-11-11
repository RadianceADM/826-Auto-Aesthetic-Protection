import { type KeyboardEvent, useCallback, useEffect, useRef, useState } from 'react';
import { Link, useLocation } from 'react-router-dom';

import useModalLayer from '../hooks/useModalLayer';
import { afterSignOut, canSignIn, guest, signInHref, signOut } from '../lib/auth';
import { copy } from '../lib/copy';
import type { Guest } from '../types';
import Avatar from './Avatar';
import { Calendar, SignOut, User } from './Icons';

const item = 'press flex min-h-11 w-full items-center gap-3 rounded-control px-3 text-left font-medium text-text hover:bg-text/6 focus-visible:bg-text/6 disabled:opacity-60';

/**
 * The avatar, and its small menu: who's signed in, My bookings, Sign out.
 * Focus moves in and stays in, arrows move between the items, Escape or a
 * click outside closes it.
 */
function GuestMenu({ guest }: { guest: Guest }) {
    const [open, setOpen] = useState(false);
    const [leaving, setLeaving] = useState(false);
    const box = useRef<HTMLDivElement>(null);
    const panel = useRef<HTMLDivElement>(null);
    const button = useRef<HTMLButtonElement>(null);
    const location = useLocation();
    const close = useCallback(() => setOpen(false), []);
    const { keepFocus } = useModalLayer(panel, { active: open, onClose: close, returnFocus: button, lockScroll: false });

    useEffect(() => setOpen(false), [location]);

    useEffect(() => {
        if (!open) {
            return;
        }

        const onPointer = (event: PointerEvent) => {
            if (box.current?.contains(event.target as Node)) {
                return;
            }

            keepFocus();
            close();
        };

        document.addEventListener('pointerdown', onPointer);

        return () => document.removeEventListener('pointerdown', onPointer);
    }, [open, close, keepFocus]);

    const onKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
        const keys = ['ArrowDown', 'ArrowUp', 'Home', 'End'];

        if (!keys.includes(event.key)) {
            return;
        }

        const items = [...event.currentTarget.querySelectorAll<HTMLElement>('[role="menuitem"]:not([disabled])')];
        const index = items.indexOf(document.activeElement as HTMLElement);
        const next = event.key === 'Home' ? 0 : event.key === 'End' ? items.length - 1 : (index + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;

        event.preventDefault();
        items[next]?.focus();
    };

    const leave = async () => {
        setLeaving(true);
        await signOut();
        afterSignOut(location.pathname);
    };

    return (
        <div ref={box} className="relative">
            <button
                ref={button}
                type="button"
                onClick={() => setOpen((value) => !value)}
                aria-haspopup="menu"
                aria-expanded={open}
                aria-controls={open ? 'account-menu' : undefined}
                aria-label={copy.account.menu(guest.name)}
                className={`press grid size-11 shrink-0 place-items-center rounded-full hover:bg-text/6 ${open ? 'bg-text/8' : ''}`}
            >
                <Avatar guest={guest} className="size-9 text-[0.8125rem] ring-1 ring-line" />
            </button>

            {open && (
                <div
                    ref={panel}
                    id="account-menu"
                    role="menu"
                    aria-label={copy.account.menu(guest.name)}
                    tabIndex={-1}
                    onKeyDown={onKeyDown}
                    className="fade-in absolute top-full right-0 z-50 mt-2 w-[min(17.5rem,calc(100vw-2rem))] origin-top-right overflow-hidden rounded-card bg-surface-2 text-text shadow-pop ring-1 ring-line outline-none"
                >
                    <div className="flex items-center gap-3 border-b border-line px-4 py-3.5" role="none">
                        <Avatar guest={guest} className="size-10 text-sm" />
                        <div className="min-w-0">
                            <p className="truncate font-semibold">{guest.name}</p>
                            <p className="truncate text-sm text-muted">{guest.email}</p>
                        </div>
                    </div>
                    <div className="p-1.5" role="none">
                        <Link role="menuitem" to="/bookings" className={item}>
                            <Calendar className="size-5 text-accent" /> {copy.account.myBookings}
                        </Link>
                        <button role="menuitem" type="button" onClick={leave} disabled={leaving} className={item}>
                            <SignOut className="size-5 text-accent" /> {leaving ? copy.account.signingOut : copy.account.signOut}
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}

/**
 * The header's account control. Signed out: "Sign in" (an icon on
 * phones). Signed in: the avatar menu. Without any way to sign in:
 * nothing.
 */
export default function AccountMenu() {
    const location = useLocation();

    if (guest) {
        return <GuestMenu guest={guest} />;
    }

    if (!canSignIn) {
        return null;
    }

    return (
        <a
            href={signInHref(`${location.pathname}${location.search}`)}
            className="press inline-flex size-11 shrink-0 items-center justify-center gap-2 rounded-full text-[0.95rem] font-medium text-text/85 hover:bg-text/6 hover:text-text lg:w-auto lg:rounded-control lg:px-3.5"
        >
            <User className="size-5" />
            <span className="max-lg:sr-only">{copy.account.signIn}</span>
        </a>
    );
}
