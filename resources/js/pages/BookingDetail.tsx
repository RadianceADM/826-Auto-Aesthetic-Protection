import { useCallback, useEffect, useRef, useState } from 'react';
import { Link, useLocation, useNavigate, useParams, useSearchParams } from 'react-router-dom';

import { AvailabilityCalendar, TimeSlots } from '../components/Availability';
import { HoldCountdown, StageTimeline } from '../components/BookingStatus';
import { Mark } from '../components/Brand';
import { ContactButtons } from '../components/BranchParts';
import { buttonClass } from '../components/Button';
import { Notice, TextField } from '../components/Form';
import { ArrowLeft, ArrowRight, Calendar, Check, Clock, Close, House, MapPin, Warning } from '../components/Icons';
import Modal from '../components/Modal';
import PaymentPanel from '../components/PaymentPanel';
import SignInBlock from '../components/SignInBlock';
import VehicleSilhouette from '../components/VehicleSilhouette';
import { guest } from '../lib/auth';
import { forget, type JobQuery } from '../lib/availability';
import { cancelBooking, fetchBooking, LIVE_STAGES, rescheduleBooking } from '../lib/bookings';
import { copy } from '../lib/copy';
import { dayAndTime, longDate, stamp } from '../lib/dates';
import { BOOK_DRAFT, emptyBookDraft, saveDraft } from '../lib/draft';
import { branchById, peso, rateFor, serviceById, site } from '../lib/site';
import usePageTitle from '../lib/usePageTitle';
import type { CustomerBooking, TimeSlot } from '../types';

const text = copy.booking;

type Load = { kind: 'loading' } | { kind: 'ok'; booking: CustomerBooking } | { kind: 'notFound' } | { kind: 'failed' };

/** Why an action didn't go through, in words */
function failure(kind: string, message?: string | null): string {
    if (message) return message;
    if (kind === 'tooMany') return copy.book.errors.tooMany;
    if (kind === 'stale') return copy.book.errors.stale;

    return copy.book.errors.failed;
}

function CancelDialog({ booking, bookingKey, onClose, onDone }: { booking: CustomerBooking; bookingKey: string | null; onClose: () => void; onDone: (booking: CustomerBooking) => void }) {
    const [reason, setReason] = useState('');
    const [working, setWorking] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const keep = useRef<HTMLButtonElement>(null);

    const confirm = async () => {
        setWorking(true);
        setError(null);
        const result = await cancelBooking(booking.id, bookingKey, reason);
        setWorking(false);

        if (result.kind === 'ok') {
            onDone(result.data.booking);
            return;
        }

        setError(failure(result.kind, result.kind === 'conflict' ? result.message : null));
    };

    return (
        <Modal title={text.cancel.title} description={text.cancel.body(booking.when)} onClose={onClose} initialFocus={keep} className="sm:max-w-lg">
            <TextField label={text.cancel.reason.label} hint={text.cancel.reason.hint} value={reason} onChange={setReason} multiline rows={3} maxLength={250} optional />
            {error && (
                <p role="alert" className="mt-4 flex items-start gap-2 font-medium text-danger">
                    <Warning className="mt-0.5 size-4 shrink-0" /> {error}
                </p>
            )}
            <div className="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <button ref={keep} type="button" onClick={onClose} className={buttonClass('outline')}>
                    {text.cancel.keep}
                </button>
                <button type="button" onClick={confirm} disabled={working} className={buttonClass('accent', 'bg-danger! text-surface! hover:opacity-90')}>
                    {working ? text.cancel.working : text.cancel.confirm}
                </button>
            </div>
        </Modal>
    );
}

function MoveDialog({ booking, bookingKey, onClose, onDone }: { booking: CustomerBooking; bookingKey: string | null; onClose: () => void; onDone: (booking: CustomerBooking, demo: boolean) => void }) {
    const job: JobQuery = { kind: 'move', booking: booking.id, key: bookingKey };
    const [date, setDate] = useState<string | null>(null);
    const [slot, setSlot] = useState<TimeSlot | null>(null);
    const [working, setWorking] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [refresh, setRefresh] = useState(0);
    const { rescheduleCutoffHours, maxReschedules } = site.booking;

    const confirm = async () => {
        if (!date || !slot) {
            setError(date ? copy.book.errors.time : text.move.pickDay);
            return;
        }

        setWorking(true);
        setError(null);
        const result = await rescheduleBooking(booking.id, bookingKey, date, slot.time);
        setWorking(false);

        if (result.kind === 'ok') {
            forget(job);
            onDone(result.data.booking, result.data.demo === true);
            return;
        }

        if (result.kind === 'invalid') {
            forget(job);
            setSlot(null);
            setRefresh((count) => count + 1);
            setError(Object.values(result.errors)[0]?.[0] ?? copy.book.errors.failed);
            return;
        }

        setError(failure(result.kind, result.kind === 'conflict' ? result.message : null));
    };

    return (
        <Modal title={text.move.title} description={text.move.body(booking.length.label)} onClose={onClose} className="sm:max-w-3xl">
            <div className="grid gap-8 md:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)]">
                <AvailabilityCalendar
                    job={job}
                    selected={date}
                    onPick={(picked) => {
                        setDate(picked);
                        setSlot(null);
                        setError(null);
                    }}
                />
                <div>
                    {date ? (
                        <>
                            <p className="mb-3 font-semibold">{longDate(date)}</p>
                            <TimeSlots job={job} date={date} selected={slot?.time ?? null} onPick={setSlot} refresh={refresh} />
                        </>
                    ) : (
                        <p className="text-muted">{text.move.pickDay}</p>
                    )}
                </div>
            </div>
            <p className="mt-6 text-sm text-muted">{text.move.rule(rescheduleCutoffHours, maxReschedules)}</p>
            {error && (
                <p role="alert" className="mt-4 flex items-start gap-2 font-medium text-danger">
                    <Warning className="mt-0.5 size-4 shrink-0" /> {error}
                </p>
            )}
            <div className="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <button type="button" onClick={onClose} className={buttonClass('outline')}>
                    {text.move.keep}
                </button>
                <button type="button" onClick={confirm} disabled={working || !slot} className={buttonClass('accent')}>
                    {working ? text.move.working : text.move.confirm}
                </button>
            </div>
        </Modal>
    );
}

/** The booking's facts as a service ticket: when, where, the car, the work and the price */
function Details({ booking }: { booking: CustomerBooking }) {
    const start = dayAndTime(booking.startsAt);
    const ready = dayAndTime(booking.readyBy);
    const home = booking.kind === 'home';

    return (
        <section aria-labelledby="details-title" className="ticket">
            <h2 id="details-title" className="sr-only">
                {copy.book.summary.title}
            </h2>
            <div className="flex items-center justify-between gap-4 px-5 pt-5 sm:px-6">
                <Mark className="h-5 w-auto" />
                <p className="eyebrow text-[0.68rem] text-muted">{copy.book.summary.title}</p>
            </div>
            <span aria-hidden="true" className="gold-rule mx-5 mt-4 block sm:mx-6" />

            {/* The car: its size drawn, the model and the plate */}
            <div className="flex items-center gap-5 px-5 pt-5 sm:px-6">
                <span className="block w-28 shrink-0 text-text/75 sm:w-40">
                    <VehicleSilhouette name={booking.vehicle.size} className="w-full" />
                </span>
                <div className="min-w-0 flex-1">
                    <p className="eyebrow text-[0.68rem] text-muted">{text.vehicle}</p>
                    <p className="mt-1.5 font-semibold">{booking.vehicle.model ?? booking.vehicle.size}</p>
                    {booking.vehicle.model && <p className="text-sm text-muted">{booking.vehicle.size}</p>}
                </div>
                {booking.vehicle.plate && <span className="plate shrink-0 max-sm:hidden">{booking.vehicle.plate}</span>}
            </div>
            {booking.vehicle.plate && (
                <p className="px-5 pt-3 sm:hidden">
                    <span className="plate">{booking.vehicle.plate}</span>
                </p>
            )}

            <div aria-hidden="true" className="ticket-tear" />
            <div className="px-5 sm:px-6">
                <dl className="grid content-start gap-5 sm:grid-cols-2">
                    {start ? (
                        <>
                            <div>
                                <dt className="eyebrow flex items-center gap-1.5 text-[0.68rem] text-muted">
                                    <Calendar className="size-3.5" /> {text.when}
                                </dt>
                                <dd className="mt-1.5 font-semibold">
                                    {start.day}, {start.time}
                                </dd>
                            </div>
                            {ready && (
                                <div>
                                    <dt className="eyebrow flex items-center gap-1.5 text-[0.68rem] text-muted">
                                        <Clock className="size-3.5" /> {text.ready}
                                    </dt>
                                    <dd className="mt-1.5 font-semibold">
                                        {ready.day === start.day ? ready.time : `${ready.day}, ${ready.time}`}
                                    </dd>
                                </div>
                            )}
                        </>
                    ) : (
                        <div className="sm:col-span-2">
                            <dt className="eyebrow flex items-center gap-1.5 text-[0.68rem] text-muted">
                                <Calendar className="size-3.5" /> {text.preferred}
                            </dt>
                            <dd className="mt-1.5 font-semibold">
                                {booking.preferredDate ? (
                                    <>
                                        {longDate(booking.preferredDate)}
                                        {booking.preferredWindow && <span className="font-normal text-muted"> · {site.booking.windows[booking.preferredWindow] ?? booking.preferredWindow}</span>}
                                    </>
                                ) : (
                                    booking.when
                                )}
                            </dd>
                        </div>
                    )}
                    {booking.branch && (
                        <div>
                            <dt className="eyebrow text-[0.68rem] text-muted">{text.branch}</dt>
                            <dd className="mt-1.5">
                                <Link to={`/branches/${booking.branch.slug}`} className="font-semibold hover:text-link">
                                    {booking.branch.name}
                                </Link>
                                {booking.branch.address && <span className="block text-sm text-muted">{booking.branch.address}</span>}
                            </dd>
                        </div>
                    )}
                    {home && booking.address && (
                        <div className="sm:col-span-2">
                            <dt className="eyebrow flex items-center gap-1.5 text-[0.68rem] text-muted">
                                <MapPin className="size-3.5" /> {text.address}
                            </dt>
                            <dd className="mt-1.5 whitespace-pre-line">
                                {booking.address}
                                {booking.area && <span className="block text-sm text-muted">{booking.area}</span>}
                            </dd>
                        </div>
                    )}
                </dl>
            </div>

            <div aria-hidden="true" className="ticket-tear" />
            <div className="px-5 sm:px-6">
                <p className="eyebrow text-[0.68rem] text-muted">{text.services}</p>
                <ul className="mt-3 space-y-2.5">
                    {booking.items.map((item, index) => (
                        <li key={`${item.name}-${index}`} className="flex justify-between gap-4">
                            <span>
                                {item.name}
                                {item.duration && <span className="block text-sm text-muted">{item.duration}</span>}
                            </span>
                            {item.price !== null && <span className="price shrink-0">{peso(item.price)}</span>}
                        </li>
                    ))}
                </ul>
            </div>
            {booking.totalPrice !== null && (
                <>
                    <div aria-hidden="true" className="ticket-tear" />
                    <div className="flex items-end justify-between gap-4 px-5 sm:px-6">
                        <p>
                            <span className="eyebrow block text-[0.68rem] text-muted">{text.total}</span>
                            {booking.length.label && (
                                <span className="mt-1.5 block text-sm text-muted">
                                    {home ? text.work : text.takes}: {booking.length.label}
                                </span>
                            )}
                        </p>
                        <p className="price text-[1.75rem] leading-none">{peso(booking.totalPrice)}</p>
                    </div>
                </>
            )}
            {booking.amountDue !== null && booking.payment.paidAt && (
                <dl className="mx-5 mt-5 space-y-1.5 border-t border-line pt-4 text-[0.95rem] sm:mx-6">
                    <div className="flex justify-between gap-4">
                        <dt>
                            {text.fee}
                            <span className="block text-sm text-muted">{text.paidAt(stamp(booking.payment.paidAt) ?? '')}</span>
                        </dt>
                        <dd className="price">{peso(booking.amountDue)}</dd>
                    </div>
                    {booking.balance !== null && (
                        <div className="flex justify-between gap-4 font-semibold">
                            <dt>{text.balance}</dt>
                            <dd className="price">{peso(booking.balance)}</dd>
                        </div>
                    )}
                </dl>
            )}
            <div className="pb-5 sm:pb-6" />
        </section>
    );
}

export default function BookingDetail() {
    usePageTitle(text.pageTitle);
    const { id = '' } = useParams();
    const [params] = useSearchParams();
    const key = params.get('key');
    const location = useLocation();
    const navigate = useNavigate();
    const [load, setLoad] = useState<Load>({ kind: 'loading' });
    const [dialog, setDialog] = useState<'cancel' | 'move' | null>(null);
    const [notice, setNotice] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);
    const busyRef = useRef(busy);
    busyRef.current = busy;

    const refresh = useCallback(async () => {
        const result = await fetchBooking(id, key);

        if (result.kind === 'ok') {
            setLoad({ kind: 'ok', booking: result.data.booking });
        } else if (result.kind === 'notFound' || result.kind === 'signIn') {
            setLoad({ kind: 'notFound' });
        } else {
            setLoad((current) => (current.kind === 'ok' ? current : { kind: 'failed' }));
        }
    }, [id, key]);

    useEffect(() => {
        setLoad({ kind: 'loading' });
        refresh();
    }, [refresh]);

    // Back to the tab after paying in GCash or Maya: a fresh look, unless a screenshot is going up
    useEffect(() => {
        const onVisible = () => {
            if (document.visibilityState === 'visible' && load.kind === 'ok' && LIVE_STAGES.includes(load.booking.stage) && !busyRef.current) {
                refresh();
            }
        };

        document.addEventListener('visibilitychange', onVisible);

        return () => document.removeEventListener('visibilitychange', onVisible);
    }, [load, refresh]);

    const holdEnded = useCallback(() => {
        window.setTimeout(refresh, 1500);
    }, [refresh]);

    if (load.kind === 'loading') {
        return (
            <div className="mx-auto max-w-5xl px-4 py-16 sm:px-8">
                <p className="text-muted" role="status">
                    {text.loading}
                </p>
            </div>
        );
    }

    if (load.kind === 'notFound' || load.kind === 'failed') {
        return (
            <div className="mx-auto max-w-3xl px-4 py-14 sm:px-8 sm:py-20">
                <h1 className="font-display text-[clamp(1.8rem,6vw,2.6rem)]">{text.notFound.title}</h1>
                <p className="mt-4 text-lg leading-relaxed text-muted">{load.kind === 'failed' ? text.failed : text.notFound.body}</p>
                {load.kind === 'failed' ? (
                    <button type="button" onClick={refresh} className={buttonClass('accent', 'mt-6')}>
                        {text.retry}
                    </button>
                ) : (
                    !guest && (
                        <div className="mt-8">
                            <SignInBlock id="booking-signin" title={copy.bookings.signIn.title} body={copy.bookings.signIn.body} returnPath={`${location.pathname}${location.search}`} />
                        </div>
                    )
                )}
            </div>
        );
    }

    const booking = load.booking;
    const stage = copy.stages[booking.stage];
    const branch = booking.branch ? branchById(booking.branch.id) : undefined;
    const contact = booking.branch ? { ...booking.branch, facebook: branch?.facebook ?? null } : null;

    const bookAgain = () => {
        const fits = (serviceId: number) => {
            const service = serviceById(serviceId);

            return Boolean(service && branch?.serviceIds.includes(serviceId) && rateFor(service, booking.vehicle.sizeId));
        };

        saveDraft(BOOK_DRAFT, {
            ...emptyBookDraft(),
            branchId: branch?.bookable ? branch.id : null,
            sizeId: booking.vehicle.sizeId,
            model: booking.vehicle.model ?? '',
            plate: booking.vehicle.plate ?? '',
            serviceIds: booking.serviceIds.filter(fits),
            name: guest?.name ?? '',
            email: guest?.email ?? '',
        });
        navigate('/book');
    };

    const replace = (next: CustomerBooking, message: string | null) => {
        setDialog(null);
        setLoad({ kind: 'ok', booking: next });
        setNotice(message);
        requestAnimationFrame(() => document.getElementById('booking-notice')?.focus());
    };

    return (
        <div className="mx-auto max-w-6xl px-4 pt-8 pb-20 sm:px-8 sm:pt-10">
            {guest && (
                <Link to="/bookings" className="press -ml-2 inline-flex min-h-11 items-center gap-2 rounded-control px-2 text-[0.95rem] font-medium text-muted hover:text-text">
                    <ArrowLeft className="size-4" /> {text.back}
                </Link>
            )}

            <header className="mt-4">
                <div className="flex flex-wrap items-center gap-2.5">
                    {booking.kind === 'home' && (
                        <span className="inline-flex items-center gap-1.5 rounded-full bg-surface-3 px-2.5 py-1 text-[0.8125rem] font-semibold">
                            <House className="size-3.5" /> {copy.bookings.home}
                        </span>
                    )}
                    <span className="text-sm text-muted tabular">
                        {text.reference} <span className="font-semibold text-text">{booking.reference}</span>
                    </span>
                </div>
                <h1 className="font-display mt-5 text-[clamp(1.9rem,6.5vw,3rem)]">{stage.label}</h1>
                <p className="mt-3 max-w-2xl text-lg leading-relaxed text-pretty text-muted">{booking.stage === 'payment_review' ? text.review.body(booking.methods.find((method) => method.key === booking.payment.method)?.label ?? null) : stage.body}</p>
            </header>

            {notice && (
                <p id="booking-notice" tabIndex={-1} role="status" className="mt-6 flex items-start gap-2.5 rounded-control border border-accent/50 bg-accent/8 px-4 py-3 font-medium outline-none">
                    <Check className="mt-0.5 size-4 shrink-0 text-accent" /> {notice}
                </p>
            )}

            <div className="mt-8 panel p-5 sm:p-6 empty:hidden">
                <StageTimeline booking={booking} />
            </div>

            <div className="mt-8 grid gap-8 lg:grid-cols-[minmax(0,1.35fr)_minmax(0,1fr)] lg:gap-10">
                <div className="min-w-0 space-y-6">
                    {booking.stage === 'awaiting_payment' && (
                        <PaymentPanel
                            booking={booking}
                            bookingKey={key}
                            onUploaded={(next, demo) => replace(next, demo ? copy.pay.upload.demo : null)}
                            onConflict={(message) => {
                                setNotice(message);
                                refresh();
                            }}
                            onBusy={setBusy}
                            countdown={booking.holdExpiresAt ? <HoldCountdown until={booking.holdExpiresAt} onEnd={holdEnded} /> : null}
                        />
                    )}

                    {booking.stage === 'payment_review' && booking.payment.proofUrl && (
                        <Notice>
                            <a href={booking.payment.proofUrl} target="_blank" rel="noopener noreferrer" className="link font-semibold">
                                {text.review.proof}
                            </a>
                        </Notice>
                    )}

                    {booking.stage === 'expired' && (
                        <section aria-labelledby="expired-title" className="panel p-5 sm:p-6">
                            <h2 id="expired-title" className="text-lg font-bold">
                                {text.expired.title}
                            </h2>
                            <p className="mt-2 leading-relaxed text-muted">{text.expired.body}</p>
                            <button type="button" onClick={bookAgain} className={buttonClass('accent', 'mt-5')}>
                                {text.expired.again} <ArrowRight className="size-4" />
                            </button>
                        </section>
                    )}

                    <Details booking={booking} />

                    {(booking.canReschedule || booking.canCancel) && (
                        <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                            {booking.canReschedule && (
                                <button type="button" onClick={() => setDialog('move')} className={buttonClass('outline')}>
                                    <Calendar className="size-4" /> {text.move.button}
                                </button>
                            )}
                            {booking.canCancel && (
                                <button type="button" onClick={() => setDialog('cancel')} className={buttonClass('outline', 'border-danger/60! text-danger! hover:border-danger! hover:bg-danger/8!')}>
                                    <Close className="size-4" /> {text.cancel.button}
                                </button>
                            )}
                        </div>
                    )}
                    {booking.canCancel && booking.startsAt && <p className="-mt-2 text-sm text-muted">{text.cancel.rule(site.booking.cancelCutoffHours)}</p>}
                </div>

                <aside className="space-y-6">
                    {contact && (
                        <section aria-labelledby="contact-title" className="panel p-5 sm:p-6">
                            <h2 id="contact-title" className="text-lg font-bold">
                                {text.contact.title}
                            </h2>
                            <p className="mt-1 text-muted">{text.contact.body(contact.name)}</p>
                            <div className="mt-4">
                                <ContactButtons target={contact} />
                            </div>
                        </section>
                    )}
                    {key && !guest && <p className="text-sm leading-relaxed text-muted">{text.keepLink}</p>}
                    {booking.createdAt && <p className="text-sm text-muted">{text.created(stamp(booking.createdAt) ?? '')}</p>}
                </aside>
            </div>

            {dialog === 'cancel' && <CancelDialog booking={booking} bookingKey={key} onClose={() => setDialog(null)} onDone={(next) => replace(next, text.cancel.done)} />}
            {dialog === 'move' && (
                <MoveDialog
                    booking={booking}
                    bookingKey={key}
                    onClose={() => setDialog(null)}
                    onDone={(next, demo) => replace(next, demo ? text.move.demo : text.move.done(next.when))}
                />
            )}
        </div>
    );
}
