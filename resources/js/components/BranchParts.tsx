import { Link } from 'react-router-dom';

import { copy } from '../lib/copy';
import { clock, hoursRange, hoursSummary, hoursToday, manilaWeekday, messageUrl, phone, site } from '../lib/site';
import type { Branch, BranchMap } from '../types';
import Button from './Button';
import { ArrowRight, Chat, Directions, Facebook, House, MapPin, Messenger, Phone } from './Icons';

/** Open or Coming soon, as a small label */
export function BranchStatus({ branch }: { branch: Branch }) {
    const open = branch.status === 'open';

    return (
        <span className={`eyebrow inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[0.68rem] ${open ? 'bg-accent/12 text-text' : 'bg-surface-3 text-muted'}`}>
            <span aria-hidden="true" className={`size-1.5 rounded-full ${open ? 'bg-accent' : 'bg-muted'}`} />
            {open ? copy.branch.open : copy.branch.comingSoon}
        </span>
    );
}

/** "Open today, 8:00 AM – 6:00 PM" or "Closed today" (Manila's today) */
export function TodayLine({ branch, className = '' }: { branch: Branch; className?: string }) {
    if (branch.status !== 'open') {
        return null;
    }

    const range = hoursRange(hoursToday(branch));

    return (
        <p className={`flex items-center gap-2 text-[0.95rem] ${className}`}>
            <span aria-hidden="true" className={`size-1.5 shrink-0 rounded-full ${range ? 'bg-accent' : 'bg-muted'}`} />
            {range ? copy.branch.openToday(range) : copy.branch.closedToday}
        </p>
    );
}

/**
 * A branch in a list: its name and city, whether it's open, today's hours,
 * home service, and the way to its page.
 */
export function BranchCard({ branch, headingLevel = 'h3' }: { branch: Branch; headingLevel?: 'h2' | 'h3' }) {
    const Heading = headingLevel;
    const week = hoursSummary(branch);

    return (
        <article className="group relative flex h-full flex-col panel p-5 transition-colors hover:border-field sm:p-6">
            <div className="flex flex-wrap items-center gap-2">
                <BranchStatus branch={branch} />
                {branch.homeService.offered && (
                    <span className="eyebrow inline-flex items-center gap-1.5 rounded-full bg-surface-3 px-2.5 py-1 text-[0.68rem]">
                        <House className="size-3.5" /> {copy.branch.homeService}
                    </span>
                )}
            </div>
            <Heading className="mt-4 text-xl leading-snug font-bold">
                <Link to={`/branches/${branch.slug}`} className="after:absolute after:inset-0 after:rounded-card focus-visible:outline-none after:focus-visible:outline-2 after:focus-visible:outline-offset-3 after:focus-visible:outline-accent">
                    {branch.name}
                </Link>
            </Heading>
            {(branch.address || branch.city) && (
                <p className="mt-2 flex gap-2 text-muted">
                    <MapPin className="mt-0.5 size-4.5 shrink-0" />
                    <span>{branch.address ?? branch.city}</span>
                </p>
            )}
            <TodayLine branch={branch} className="mt-4" />
            {branch.status === 'open' && (
                <ul className="mt-3 space-y-1 text-sm text-muted">
                    {week.map((run) => (
                        <li key={run.days} className="flex justify-between gap-4">
                            <span>{run.days}</span>
                            <span className="tabular">{run.hours ?? copy.branch.closed}</span>
                        </li>
                    ))}
                </ul>
            )}
            <span className="mt-auto inline-flex items-center gap-2 pt-5 font-semibold text-link">
                {copy.cta.viewBranch} <ArrowRight className="size-4" />
            </span>
        </article>
    );
}

/**
 * Every day of the week: opening hours, and the last drop-off when it's
 * earlier than closing. Today is marked.
 */
export function HoursTable({ branch }: { branch: Branch }) {
    const today = manilaWeekday();

    return (
        <table className="w-full border-collapse text-left text-[0.95rem]">
            <caption className="sr-only">{copy.branch.hours}</caption>
            <tbody>
                {branch.hours.map((day) => {
                    const isToday = day.day === today;
                    const range = hoursRange(day);

                    return (
                        <tr key={day.day} className={`border-b border-line last:border-b-0 ${isToday ? 'font-semibold' : ''}`}>
                            <th scope="row" className="py-3 pr-4 font-[inherit]">
                                {day.label}
                                {isToday && <span className="eyebrow ml-2 rounded-full bg-accent/12 px-2 py-0.5 text-[0.62rem]">{copy.branch.today}</span>}
                            </th>
                            <td className="py-3 text-right tabular">
                                {range ?? <span className="text-muted">{copy.branch.closed}</span>}
                                {range && day.lastDropoff && day.lastDropoff !== day.close && <span className="block text-sm font-normal text-muted">{copy.branch.lastDropoff(clock(day.lastDropoff))}</span>}
                            </td>
                        </tr>
                    );
                })}
            </tbody>
        </table>
    );
}

type ContactTarget = {
    phone: string | null;
    phoneHref: string | null;
    messenger: string | null;
    facebook?: string | null;
    map: BranchMap | null;
};

/**
 * Call, text, Messenger, Facebook and Directions for a branch, each only
 * when the branch has it. Without a number of its own, 826's main line;
 * without anything of its own, 826's main number and page.
 */
export function ContactButtons({ target, fallbackNote = true }: { target: ContactTarget; fallbackNote?: boolean }) {
    const small = 'min-h-11! px-4! text-[0.92rem]!';
    const has = target.phoneHref || target.messenger || target.facebook || target.map?.google || target.map?.waze;

    if (!has) {
        if (!phone && !messageUrl) {
            return null;
        }

        return (
            <div>
                {fallbackNote && <p className="text-[0.95rem] text-muted">{copy.branch.noContact}</p>}
                <div className="mt-3 flex flex-wrap gap-2">
                    {phone && (
                        <Button href={`tel:${phone.href}`} variant="outline" className={small}>
                            <Phone className="size-4" /> {copy.branch.call} {phone.label}
                        </Button>
                    )}
                    {messageUrl && (
                        <Button href={messageUrl} variant="outline" className={small}>
                            {site.settings.messenger ? <Messenger className="size-4" /> : <Facebook className="size-4" />}
                            {site.settings.messenger ? copy.branch.messenger : copy.branch.facebook}
                        </Button>
                    )}
                </div>
            </div>
        );
    }

    return (
        <div className="flex flex-wrap gap-2">
            {!target.phoneHref && phone && (
                <Button href={`tel:${phone.href}`} variant="outline" className={small}>
                    <Phone className="size-4" /> {copy.branch.callMain} <span className="tabular">{phone.label}</span>
                </Button>
            )}
            {target.phoneHref && (
                <>
                    <Button href={`tel:${target.phoneHref}`} variant="outline" className={small}>
                        <Phone className="size-4" /> {copy.branch.call}
                        {target.phone && <span className="tabular">{target.phone}</span>}
                    </Button>
                    <Button href={`sms:${target.phoneHref}`} variant="outline" className={small}>
                        <Chat className="size-4" /> {copy.branch.text}
                    </Button>
                </>
            )}
            {target.messenger && (
                <Button href={target.messenger} variant="outline" className={small}>
                    <Messenger className="size-4" /> {copy.branch.messenger}
                </Button>
            )}
            {target.facebook && (
                <Button href={target.facebook} variant="outline" className={small}>
                    <Facebook className="size-4" /> {copy.branch.facebook}
                </Button>
            )}
            {target.map?.google && (
                <Button href={target.map.google} variant="outline" className={small}>
                    <Directions className="size-4" /> {copy.branch.directions}
                </Button>
            )}
            {target.map?.waze && (
                <Button href={target.map.waze} variant="outline" className={small}>
                    <MapPin className="size-4" /> {copy.branch.waze}
                </Button>
            )}
        </div>
    );
}
