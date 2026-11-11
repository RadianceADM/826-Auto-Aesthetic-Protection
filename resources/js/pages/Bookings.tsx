import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';

import { StageBadge } from '../components/BookingStatus';
import Button from '../components/Button';
import { PageHeader } from '../components/Heading';
import { ArrowRight, House, Warning } from '../components/Icons';
import SignInBlock from '../components/SignInBlock';
import VehicleSilhouette from '../components/VehicleSilhouette';
import { guest } from '../lib/auth';
import { fetchBookings, LIVE_STAGES } from '../lib/bookings';
import { copy } from '../lib/copy';
import { peso } from '../lib/site';
import usePageTitle from '../lib/usePageTitle';
import type { CustomerBooking } from '../types';

const text = copy.bookings;

function BookingCard({ booking }: { booking: CustomerBooking }) {
    return (
        <article className="relative panel p-5 transition-colors hover:border-field sm:p-6">
            <div className="flex flex-wrap items-center gap-2">
                <StageBadge stage={booking.stage} />
                {booking.kind === 'home' && (
                    <span className="inline-flex items-center gap-1.5 rounded-full bg-surface-3 px-2.5 py-1 text-[0.8125rem] font-semibold">
                        <House className="size-3.5" /> {text.home}
                    </span>
                )}
                <span className="ml-auto text-sm text-muted tabular">{text.reference(booking.reference)}</span>
            </div>
            <h3 className="mt-4 text-lg leading-snug font-bold">
                <Link to={booking.url} className="after:absolute after:inset-0 after:rounded-card focus-visible:outline-none after:focus-visible:outline-2 after:focus-visible:outline-offset-3 after:focus-visible:outline-accent">
                    {booking.when}
                </Link>
            </h3>
            <div className="mt-1 flex items-end justify-between gap-4">
                <p className="text-muted">{[booking.branch?.name, [booking.vehicle.size, booking.vehicle.model].filter(Boolean).join(' · ')].filter(Boolean).join(' — ')}</p>
                <span className="hidden w-24 shrink-0 text-text/40 sm:block">
                    <VehicleSilhouette name={booking.vehicle.size} className="w-full [--silhouette-cut:var(--panel-top)]" />
                </span>
            </div>
            <div className="mt-4 flex flex-wrap items-end justify-between gap-3 border-t border-line pt-4">
                <p className="min-w-0 text-[0.95rem]">{booking.items.map((item) => item.name).join(', ')}</p>
                {booking.totalPrice !== null && <p className="price shrink-0 text-lg">{peso(booking.totalPrice)}</p>}
            </div>
        </article>
    );
}

export default function Bookings() {
    usePageTitle(text.title);
    const [bookings, setBookings] = useState<CustomerBooking[] | null>(null);
    const [state, setState] = useState<'loading' | 'ok' | 'failed' | 'signedOut'>(guest ? 'loading' : 'signedOut');
    const [attempt, setAttempt] = useState(0);

    useEffect(() => {
        if (!guest) {
            return;
        }

        let cancelled = false;
        setState('loading');

        fetchBookings().then((result) => {
            if (cancelled) {
                return;
            }

            if (result.kind === 'ok') {
                setBookings(result.data.bookings);
                setState('ok');
            } else {
                setState(result.kind === 'signIn' ? 'signedOut' : 'failed');
            }
        });

        return () => {
            cancelled = true;
        };
    }, [attempt]);

    const upcoming = (bookings ?? []).filter((booking) => LIVE_STAGES.includes(booking.stage));
    const past = (bookings ?? []).filter((booking) => !LIVE_STAGES.includes(booking.stage));

    return (
        <>
            <PageHeader eyebrow={text.eyebrow} title={text.title}>
                <p>{text.intro}</p>
            </PageHeader>
            <div className="mx-auto max-w-7xl px-4 py-10 sm:px-8 sm:py-14 xl:px-12">
                <div className="max-w-3xl">
                {state === 'signedOut' && <SignInBlock id="bookings-signin" title={text.signIn.title} body={text.signIn.body} returnPath="/bookings" />}

                {state === 'loading' && (
                    <p className="text-muted" role="status">
                        {text.loading}
                    </p>
                )}

                {state === 'failed' && (
                    <p role="alert" className="flex flex-wrap items-center gap-3 font-medium text-danger">
                        <Warning className="size-5" /> {text.failed}
                        <button type="button" onClick={() => setAttempt((count) => count + 1)} className="press min-h-11 rounded-control px-2 font-semibold text-text underline underline-offset-4">
                            {text.retry}
                        </button>
                    </p>
                )}

                {state === 'ok' && bookings?.length === 0 && (
                    <div className="panel p-6">
                        <p className="text-lg">{text.empty}</p>
                        <Button to="/book" className="mt-5">
                            {text.emptyCta} <ArrowRight className="size-4" />
                        </Button>
                    </div>
                )}

                {state === 'ok' && upcoming.length > 0 && (
                    <section aria-labelledby="upcoming-title">
                        <h2 id="upcoming-title" className="eyebrow text-muted">
                            {text.upcoming}
                        </h2>
                        <ul className="mt-4 grid gap-3">
                            {upcoming.map((booking) => (
                                <li key={booking.id}>
                                    <BookingCard booking={booking} />
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                {state === 'ok' && past.length > 0 && (
                    <section aria-labelledby="past-title" className={upcoming.length > 0 ? 'mt-12' : ''}>
                        <h2 id="past-title" className="eyebrow text-muted">
                            {text.past}
                        </h2>
                        <ul className="mt-4 grid gap-3">
                            {past.map((booking) => (
                                <li key={booking.id}>
                                    <BookingCard booking={booking} />
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
                </div>
            </div>
        </>
    );
}
