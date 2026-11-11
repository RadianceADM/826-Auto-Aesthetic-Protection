import { Link, useLocation } from 'react-router-dom';

import { cars, creditedCars } from '../lib/cars';
import { copy } from '../lib/copy';
import { phone, site } from '../lib/site';
import { Mark } from './Brand';
import { ChevronDown, Facebook, Instagram, Mail, Messenger, Phone } from './Icons';
import { navLinks } from './Navbar';

const footLink = 'press inline-flex min-h-11 items-center gap-2.5 text-text/80 hover:text-text lg:min-h-9';

export default function Footer() {
    const { facebook, instagram, messenger, email, tagline } = site.settings;
    // The booking wizard's bar is pinned to the bottom on phones: room for it under the footer
    const underBar = useLocation().pathname === '/book';

    return (
        <footer className={`mt-auto border-t border-line bg-surface-2 ${underBar ? 'pb-24 lg:pb-0' : ''}`}>
            <div className="mx-auto grid max-w-7xl gap-10 px-4 pt-12 pb-10 sm:px-8 md:grid-cols-2 lg:grid-cols-[1.4fr_1fr_1fr_1.2fr] xl:px-12">
                <div>
                    <Link to="/" className="press inline-block rounded-control" aria-label={copy.nav.homeLabel(site.name)}>
                        <Mark className="h-10 w-auto" />
                    </Link>
                    <span aria-hidden="true" className="gold-rule mt-4 block w-36" />
                    <p className="mt-4 font-semibold">{site.name}</p>
                    {tagline && <p className="mt-1.5 max-w-xs text-[0.95rem] leading-relaxed text-muted">{tagline}</p>}
                </div>

                <nav aria-label={copy.footer.explore}>
                    <p className="eyebrow text-muted">{copy.footer.explore}</p>
                    <ul className="mt-3">
                        {[...navLinks, { to: '/book', label: copy.cta.reserve }].map((link) => (
                            <li key={link.to}>
                                <Link to={link.to} className={footLink}>
                                    {link.label}
                                </Link>
                            </li>
                        ))}
                    </ul>
                </nav>

                <div>
                    <p className="eyebrow text-muted">{copy.footer.branches}</p>
                    <ul className="mt-3">
                        {site.branches.map((branch) => (
                            <li key={branch.id}>
                                <Link to={`/branches/${branch.slug}`} className={footLink}>
                                    {branch.name}
                                    {branch.status !== 'open' && <span className="text-sm text-muted">· {copy.branch.comingSoon}</span>}
                                </Link>
                            </li>
                        ))}
                    </ul>
                </div>

                <div>
                    <p className="eyebrow text-muted">{copy.footer.contact}</p>
                    <ul className="mt-3">
                        {phone && (
                            <li>
                                <a href={`tel:${phone.href}`} className={footLink}>
                                    <Phone className="size-4.5 text-accent" /> <span className="tabular">{phone.label}</span>
                                </a>
                            </li>
                        )}
                        {email && (
                            <li>
                                <a href={`mailto:${email}`} className={footLink}>
                                    <Mail className="size-4.5 shrink-0 text-accent" />
                                    <span>
                                        {email.split('@')[0]}
                                        <wbr />@{email.split('@').slice(1).join('@')}
                                    </span>
                                </a>
                            </li>
                        )}
                        {messenger && (
                            <li>
                                <a href={messenger} target="_blank" rel="noopener noreferrer" className={footLink}>
                                    <Messenger className="size-4.5 text-accent" /> {copy.footer.messenger}
                                </a>
                            </li>
                        )}
                        {facebook && (
                            <li>
                                <a href={facebook} target="_blank" rel="noopener noreferrer" className={footLink}>
                                    <Facebook className="size-4.5 text-accent" /> {copy.footer.facebook}
                                </a>
                            </li>
                        )}
                        {instagram && (
                            <li>
                                <a href={instagram} target="_blank" rel="noopener noreferrer" className={footLink}>
                                    <Instagram className="size-4.5 text-accent" /> {copy.footer.instagram}
                                </a>
                            </li>
                        )}
                    </ul>
                </div>
            </div>
            <div className="border-t border-line">
                <div className="mx-auto flex max-w-7xl flex-col gap-x-8 gap-y-1 px-4 py-4 text-sm text-muted sm:px-8 md:flex-row md:items-start md:justify-between xl:px-12">
                    <p className="py-3">
                        © {new Date().getFullYear()} {site.name}
                    </p>
                    {cars.length > 0 && <CarCredits />}
                </div>
            </div>
        </footer>
    );
}

/**
 * Where the car pictures come from: they're stand-ins from Wikimedia
 * Commons, credited as their licences ask, on every page.
 */
function CarCredits() {
    return (
        <details className="group md:max-w-xl md:text-right">
            <summary className="press inline-flex min-h-11 cursor-pointer list-none items-center gap-1.5 rounded-control font-medium text-text/80 hover:text-text [&::-webkit-details-marker]:hidden">
                {copy.credits.title}
                <ChevronDown className="size-4 transition-transform group-open:rotate-180" />
            </summary>
            <div className="pt-1 pb-3 text-left md:text-right">
                <p className="leading-relaxed">{creditedCars.length > 0 ? copy.credits.note : copy.credits.none}</p>
                {creditedCars.length > 0 && (
                    <ul className="mt-2 space-y-1.5 leading-relaxed">
                        {creditedCars.map((car) => (
                            <li key={car.slug}>
                                <a href={car.source} target="_blank" rel="noopener noreferrer" className="link">
                                    {car.attribution}
                                </a>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </details>
    );
}
