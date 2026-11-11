import { useCallback, useEffect, useRef, useState } from 'react';
import { Link, NavLink, useLocation } from 'react-router-dom';

import useModalLayer from '../hooks/useModalLayer';
import { guest } from '../lib/auth';
import { copy } from '../lib/copy';
import { homeBranches, phone, site } from '../lib/site';
import { toggleTheme, useTheme } from '../lib/theme';
import AccountMenu from './AccountMenu';
import { Mark } from './Brand';
import { ArrowRight, Moon, Phone, Sun } from './Icons';

/** The pages in the bar, in order; Home service and Our work only once there's something there */
export const navLinks = [
    { to: '/services', label: copy.nav.services },
    { to: '/branches', label: copy.nav.branches },
    ...(homeBranches.length ? [{ to: '/home-service', label: copy.nav.homeService }] : []),
    ...(site.photos.length ? [{ to: '/gallery', label: copy.nav.gallery }] : []),
];

/**
 * Light or dark, one button: it shows the mode it switches to, and says
 * whether dark is on (aria-pressed; the name stays the same).
 */
function ThemeToggle() {
    const dark = useTheme() === 'dark';

    return (
        <button
            type="button"
            onClick={toggleTheme}
            aria-pressed={dark}
            aria-label={copy.theme.label}
            title={dark ? copy.theme.toLight : copy.theme.toDark}
            className="press grid size-11 shrink-0 place-items-center rounded-full text-text/85 hover:bg-text/6 hover:text-text"
        >
            {dark ? <Sun className="size-5" /> : <Moon className="size-5" />}
        </button>
    );
}

/**
 * The bar on every page: the mark, the pages, light/dark, the account and
 * Book now. On phones the pages fold into a menu that covers the page.
 */
export default function Navbar() {
    const [open, setOpen] = useState(false);
    const [scrolled, setScrolled] = useState(false);
    const header = useRef<HTMLElement>(null);
    const burger = useRef<HTMLButtonElement>(null);
    const firstLink = useRef<HTMLAnchorElement>(null);
    const location = useLocation();
    const closeMenu = useCallback(() => setOpen(false), []);
    const onBook = location.pathname === '/book';

    // The open menu: focus in it, Escape closes, the page holds still
    useModalLayer(header, { active: open, onClose: closeMenu, initialFocus: firstLink, returnFocus: burger });

    useEffect(() => setOpen(false), [location]);

    useEffect(() => {
        const onScroll = () => setScrolled(window.scrollY > 8);

        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });

        return () => window.removeEventListener('scroll', onScroll);
    }, []);

    // Wide enough for the links in the bar: the menu goes
    useEffect(() => {
        if (!open) {
            return;
        }

        const wide = window.matchMedia('(min-width: 1024px)');
        const onWide = (event: MediaQueryListEvent) => event.matches && setOpen(false);
        wide.addEventListener('change', onWide);

        return () => wide.removeEventListener('change', onWide);
    }, [open]);

    const linkClass = ({ isActive }: { isActive: boolean }) =>
        `press relative rounded-control px-3.5 py-2.5 text-[0.95rem] font-medium ${isActive ? 'text-text after:absolute after:inset-x-3.5 after:-bottom-px after:h-0.5 after:rounded-full after:bg-accent' : 'text-text/75 hover:bg-text/6 hover:text-text'}`;

    const menuLinks = [{ to: '/', label: copy.nav.home }, ...navLinks, ...(guest ? [{ to: '/bookings', label: copy.nav.bookings }] : [])];

    return (
        <header
            ref={header}
            // No blur while the menu is open: a backdrop filter would hold the fixed menu inside the header
            className={`sticky top-0 z-50 pt-[env(safe-area-inset-top)] ${open ? 'bg-surface' : 'bg-surface/95 backdrop-blur-md'} ${scrolled || open ? 'shadow-[0_1px_0_var(--line)]' : ''}`}
        >
            {site.demo && (
                <p className="flex min-h-8 items-center justify-center gap-2 bg-surface-3 px-4 py-1 text-center text-[0.8125rem] leading-tight text-text">
                    <span className="eyebrow text-[0.68rem]">{copy.demo.label}</span>
                    <span className="sm:hidden">{copy.demo.short}</span>
                    <span className="hidden sm:inline">{copy.demo.body}</span>
                </p>
            )}
            <nav className="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:h-[4.5rem] sm:px-8 xl:px-12" aria-label={copy.nav.main}>
                <Link to="/" aria-label={copy.nav.homeLabel(site.name)} className="press -my-1 -ml-1 flex items-center rounded-control p-1">
                    <Mark className="h-7 w-auto sm:h-8" />
                </Link>

                <div className="flex items-center gap-0.5 sm:gap-1">
                    <div className="hidden items-center gap-0.5 lg:flex">
                        {navLinks.map((link) => (
                            <NavLink key={link.to} to={link.to} className={linkClass}>
                                {link.label}
                            </NavLink>
                        ))}
                        {phone && (
                            <a href={`tel:${phone.href}`} className="press ml-1 inline-flex items-center gap-2 rounded-control px-3.5 py-2.5 text-[0.95rem] font-medium text-text/75 tabular hover:bg-text/6 hover:text-text">
                                <Phone className="size-4" /> <span className="max-xl:sr-only">{phone.label}</span>
                            </a>
                        )}
                    </div>

                    <ThemeToggle />
                    <AccountMenu />

                    {!onBook && (
                        <Link to="/book" className="press ml-1.5 inline-flex min-h-10 items-center rounded-control bg-accent px-3.5 text-[0.9rem] font-semibold whitespace-nowrap text-on-accent hover:bg-accent-hover sm:ml-2 sm:min-h-11 sm:px-5">
                            {copy.nav.book}
                        </Link>
                    )}

                    <button
                        ref={burger}
                        type="button"
                        className="-mr-2 ml-0.5 grid size-11 place-items-center rounded-full text-text hover:bg-text/6 lg:hidden"
                        aria-expanded={open}
                        aria-controls="mobile-menu"
                        aria-label={open ? copy.nav.closeMenu : copy.nav.openMenu}
                        onClick={() => setOpen((value) => !value)}
                    >
                        <span aria-hidden="true" className="relative block h-3.5 w-5">
                            <span className={`absolute left-0 h-[1.5px] w-full rounded bg-current transition-transform ${open ? 'top-1.5 rotate-45' : 'top-0'}`} />
                            <span className={`absolute top-1.5 left-0 h-[1.5px] w-full rounded bg-current ${open ? 'opacity-0' : ''}`} />
                            <span className={`absolute left-0 h-[1.5px] w-full rounded bg-current transition-transform ${open ? 'top-1.5 -rotate-45' : 'top-3'}`} />
                        </span>
                    </button>
                </div>
            </nav>

            <div
                id="mobile-menu"
                className={`fixed inset-x-0 bottom-0 flex-col overflow-y-auto overscroll-contain bg-surface px-4 pt-2 pb-[max(1.5rem,env(safe-area-inset-bottom))] sm:px-8 lg:hidden ${open ? 'fade-in flex' : 'hidden'} ${site.demo ? 'top-[calc(6rem+env(safe-area-inset-top))] sm:top-[calc(6.5rem+env(safe-area-inset-top))]' : 'top-[calc(4rem+env(safe-area-inset-top))] sm:top-[calc(4.5rem+env(safe-area-inset-top))]'}`}
            >
                <ul>
                    {menuLinks.map((link, index) => (
                        <li key={link.to} className="border-b border-line">
                            <NavLink
                                ref={index === 0 ? firstLink : undefined}
                                to={link.to}
                                end={link.to === '/'}
                                className={({ isActive }) => `press flex min-h-16 items-center justify-between gap-4 ${isActive ? 'text-accent' : 'text-text'}`}
                            >
                                <span className="font-display text-[1.6rem]">{link.label}</span>
                                <ArrowRight className="size-5 shrink-0 opacity-60" />
                            </NavLink>
                        </li>
                    ))}
                </ul>
                <Link to="/book" className="press mt-6 inline-flex min-h-13 items-center justify-center gap-2 rounded-control bg-accent px-6 text-base font-semibold text-on-accent hover:bg-accent-hover">
                    {copy.cta.reserve} <ArrowRight className="size-4" />
                </Link>
                {phone && (
                    <div className="mt-auto pt-10">
                        <p className="eyebrow text-muted">{copy.nav.callOrText}</p>
                        <a href={`tel:${phone.href}`} className="price mt-2 inline-flex min-h-11 items-center text-2xl text-text">
                            {phone.label}
                        </a>
                    </div>
                )}
            </div>
        </header>
    );
}
