import { type ReactNode, type RefObject, useCallback, useEffect, useRef, useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';

import { AvailabilityCalendar, TimeSlots } from '../components/Availability';
import { ContactButtons } from '../components/BranchParts';
import { Mark } from '../components/Brand';
import Button, { buttonClass } from '../components/Button';
import CategoryIcon from '../components/CategoryIcon';
import { Checkbox, ChoiceCard, ErrorText, Notice, TextField } from '../components/Form';
import { ArrowLeft, ArrowRight, Check, House, MapPin } from '../components/Icons';
import SignInBlock from '../components/SignInBlock';
import VehicleSilhouette from '../components/VehicleSilhouette';
import { type FieldErrors, postJson } from '../lib/api';
import { guest, signInHref, signInNeeded } from '../lib/auth';
import { fetchMonth, forget, type JobQuery, jobKey } from '../lib/availability';
import { copy } from '../lib/copy';
import { longDate, shortDate } from '../lib/dates';
import { BOOK_DRAFT, type BookDraft, clearDraft, emailLooksWrong, emptyBookDraft, loadDraft, phoneProblem, plateLooksWrong, saveDraft } from '../lib/draft';
import { bookableBranches, branchById, branchBySlug, categories, clock, hoursRange, hoursToday, lengthLabel, peso, rateFor, serviceById, serviceBySlug, servicesAt, site, sizeById, sizes } from '../lib/site';
import usePageTitle from '../lib/usePageTitle';
import type { CustomerBooking, JobLength } from '../types';

const text = copy.book;

const STEPS = ['branch', 'vehicle', 'services', 'date', 'time', 'details', 'review'] as const;
type StepKey = (typeof STEPS)[number];

/** Which step shows a server error on each field */
const FIELD_STEP: Record<string, StepKey> = {
    branch: 'branch',
    size: 'vehicle',
    vehicle_model: 'vehicle',
    plate_number: 'vehicle',
    services: 'services',
    date: 'date',
    time: 'time',
    name: 'details',
    phone: 'details',
    email: 'details',
    message: 'details',
    consent: 'details',
};

type Problems = Partial<Record<string, string>>;

/** What's missing or wrong on a step, as the server would say it */
function problemsOn(step: StepKey, draft: BookDraft): Problems {
    const errors = text.errors;
    const branch = branchById(draft.branchId);
    const problems: Problems = {};

    if (step === 'branch' && !branch?.bookable) {
        problems.branch = errors.branch;
    }

    if (step === 'vehicle') {
        if (!sizeById(draft.sizeId)) problems.size = errors.size;
        if (!draft.model.trim()) problems.vehicle_model = errors.model;
        if (!draft.plate.trim()) problems.plate_number = errors.plate;
        else if (plateLooksWrong(draft.plate)) problems.plate_number = errors.plateFormat;
    }

    if (step === 'services') {
        const offered = servicesAt(branch, draft.sizeId).map((service) => service.id);

        if (draft.serviceIds.length === 0 || draft.serviceIds.some((id) => !offered.includes(id))) {
            problems.services = errors.services;
        }
    }

    if (step === 'date' && !draft.date) {
        problems.date = errors.date;
    }

    if (step === 'time' && !draft.slot) {
        problems.time = errors.time;
    }

    if (step === 'details') {
        if (!draft.name.trim()) problems.name = errors.name;
        const phone = phoneProblem(draft.phone);
        if (phone) problems.phone = phone === 'missing' ? errors.phone : errors.phoneFormat;
        if (emailLooksWrong(draft.email)) problems.email = errors.email;
        if (!draft.consent) problems.consent = errors.consent;
    }

    return problems;
}

const firstIncomplete = (draft: BookDraft): number => {
    const index = STEPS.findIndex((step) => step !== 'review' && Object.keys(problemsOn(step, draft)).length > 0);

    return index === -1 ? STEPS.length - 1 : index;
};

/** The job's length and total from the picked rates, the same sum the server makes */
function quoteOf(draft: BookDraft) {
    let days = 0;
    let minutes = 0;
    let total = 0;
    const lines: { id: number; name: string; price: number; duration: string }[] = [];

    for (const id of draft.serviceIds) {
        const service = serviceById(id);
        const rate = service ? rateFor(service, draft.sizeId) : undefined;

        if (service && rate) {
            days += rate.days;
            minutes += rate.minutes;
            total += rate.price;
            lines.push({ id, name: service.name, price: rate.price, duration: rate.duration });
        }
    }

    return { lines, total, days, minutes, label: lengthLabel(days, minutes) };
}

/**
 * The wizard's draft: from this tab's storage, or fresh with the link's
 * branch and service when it came with them. Name and email start from
 * the Google account.
 */
function startingDraft(params: URLSearchParams): BookDraft {
    const saved = loadDraft(BOOK_DRAFT, emptyBookDraft());
    const presetBranch = branchBySlug(params.get('branch'));
    const presetService = serviceBySlug(params.get('service'));
    const draft: BookDraft =
        presetBranch || presetService
            ? {
                  ...emptyBookDraft(),
                  // Who they are and their car stay; the job starts over
                  sizeId: saved.sizeId,
                  model: saved.model,
                  plate: saved.plate,
                  name: saved.name,
                  phone: saved.phone,
                  email: saved.email,
                  branchId: presetBranch?.bookable ? presetBranch.id : bookableBranches.length === 1 ? bookableBranches[0].id : null,
                  serviceIds: presetService ? [presetService.id] : [],
              }
            : saved;

    if (!draft.branchId && bookableBranches.length === 1) {
        draft.branchId = bookableBranches[0].id;
    }

    return {
        ...draft,
        name: draft.name || guest?.name || '',
        email: draft.email || guest?.email || '',
    };
}

function StepHeading({ id, title, children, headingRef }: { id: string; title: string; children?: ReactNode; headingRef: RefObject<HTMLHeadingElement | null> }) {
    return (
        <div className="mb-6">
            <h2 id={id} ref={headingRef} tabIndex={-1} className="font-display text-[clamp(1.45rem,5vw,2.1rem)] outline-none">
                {title}
            </h2>
            {children && <div className="mt-3 max-w-2xl leading-relaxed text-muted">{children}</div>}
        </div>
    );
}

/**
 * The running summary, drawn as a service ticket: the right column on wide
 * screens, the review step everywhere. The chosen size's outline and the
 * car's plate up top, then the branch, the work and the drop-off, and the
 * total under a perforated line.
 */
function Summary({ draft, length, onEdit, compact = false }: { draft: BookDraft; length: string; onEdit?: (step: StepKey) => void; compact?: boolean }) {
    const branch = branchById(draft.branchId);
    const size = sizeById(draft.sizeId);
    const quote = quoteOf(draft);
    const review = text.review;
    const nothing = !branch && !size && quote.lines.length === 0;
    const model = draft.model.trim();
    const plate = draft.plate.trim().toUpperCase();

    const edit = (label: string, step: StepKey) =>
        onEdit && (
            <button type="button" onClick={() => onEdit(step)} className="press -my-2.5 -mr-2 inline-flex min-h-11 shrink-0 items-center rounded-control px-2 text-sm font-semibold text-link hover:underline hover:underline-offset-4">
                {text.edit}
                <span className="sr-only"> {label.toLowerCase()}</span>
            </button>
        );

    const row = (label: string, step: StepKey, body: ReactNode) => (
        <div className="border-b border-line py-4 first:pt-0 last:border-b-0 last:pb-0">
            <div className="flex min-h-6 items-center justify-between gap-4">
                <dt className="eyebrow text-[0.68rem] text-muted">{label}</dt>
                {edit(label, step)}
            </div>
            <dd className="mt-1.5">{body}</dd>
        </div>
    );

    return (
        <div className="ticket">
            <div className="flex items-center justify-between gap-4 px-5 pt-5 sm:px-6">
                <Mark className="h-5 w-auto" />
                <p className="eyebrow text-[0.68rem] text-muted">{text.summary.title}</p>
            </div>
            <span aria-hidden="true" className="gold-rule mx-5 mt-4 block sm:mx-6" />

            {/* The car: its size drawn, the size, the model and the plate */}
            <div className="px-5 pt-5 sm:px-6">
                <div className={`mx-auto max-w-[16rem] ${size ? 'text-text/80' : 'text-text/15'}`}>
                    <VehicleSilhouette name={size?.name} className="ticket-car w-full" />
                </div>
                {size ? (
                    <div className="mt-4 flex items-start justify-between gap-4">
                        <div className="min-w-0">
                            <p className="eyebrow text-[0.68rem] text-muted">{review.vehicle}</p>
                            <p className="mt-1.5 font-semibold">{size.name}</p>
                            {model && <p className="text-muted">{model}</p>}
                        </div>
                        <div className="flex shrink-0 flex-col items-end gap-2">
                            {edit(review.vehicle, 'vehicle')}
                            {plate && <span className="plate">{plate}</span>}
                        </div>
                    </div>
                ) : (
                    <p className="mt-3 text-center text-muted">{text.summary.empty}</p>
                )}
            </div>

            {!nothing && (
                <>
                    <div aria-hidden="true" className="ticket-tear" />
                    <dl className="px-5 sm:px-6">
                        {branch && row(review.branch, 'branch', <p className="font-semibold">{branch.name}</p>)}
                        {quote.lines.length > 0 &&
                            row(
                                review.services,
                                'services',
                                <ul className="space-y-2">
                                    {quote.lines.map((line) => (
                                        <li key={line.id} className="flex justify-between gap-4">
                                            <span>
                                                {line.name}
                                                <span className="block text-sm text-muted">{line.duration}</span>
                                            </span>
                                            <span className="price shrink-0">{peso(line.price)}</span>
                                        </li>
                                    ))}
                                </ul>,
                            )}
                        {draft.date &&
                            row(
                                review.when,
                                draft.slot ? 'time' : 'date',
                                <p>
                                    <span className="font-semibold">{longDate(draft.date)}</span>
                                    {draft.slot && (
                                        <span className="block">
                                            {draft.slot.label} <span className="text-muted">· {draft.slot.readyLabel}</span>
                                        </span>
                                    )}
                                </p>,
                            )}
                        {!compact &&
                            draft.name &&
                            row(
                                review.contact,
                                'details',
                                <p>
                                    <span className="font-semibold">{draft.name}</span>
                                    <span className="block text-muted">{[draft.phone, draft.email].filter(Boolean).join(' · ')}</span>
                                </p>,
                            )}
                        {!compact && draft.message.trim() && row(review.note, 'details', <p className="whitespace-pre-line text-muted">{draft.message.trim()}</p>)}
                    </dl>
                </>
            )}

            {quote.lines.length > 0 ? (
                <>
                    <div aria-hidden="true" className="ticket-tear" />
                    <div className="flex items-end justify-between gap-4 px-5 pb-5 sm:px-6 sm:pb-6">
                        <div>
                            <p className="eyebrow text-[0.68rem] text-muted">{review.total}</p>
                            <p className="mt-1.5 text-sm text-muted">{text.services.takes(length)}</p>
                        </div>
                        <p className="price text-[1.75rem] leading-none">{peso(quote.total)}</p>
                    </div>
                </>
            ) : (
                <div className="pb-5 sm:pb-6" />
            )}
        </div>
    );
}

export default function Book() {
    usePageTitle(text.pageTitle);
    const [params, setParams] = useSearchParams();
    const navigate = useNavigate();
    const [draft, setDraft] = useState<BookDraft>(() => startingDraft(params));
    const [shown, setShown] = useState<Problems>({});
    const [server, setServer] = useState<FieldErrors>({});
    const [formError, setFormError] = useState<string | null>(null);
    const [submitting, setSubmitting] = useState(false);
    const [demoDone, setDemoDone] = useState<{ readyLabel: string | null } | null>(null);
    const [apiLength, setApiLength] = useState<{ key: string; length: JobLength } | null>(null);
    const [timesRefresh, setTimesRefresh] = useState(0);
    const [presetMissing, setPresetMissing] = useState<string | null>(null);
    const heading = useRef<HTMLHeadingElement>(null);
    const moved = useRef(false);

    // Keep the draft in this tab as it changes
    useEffect(() => saveDraft(BOOK_DRAFT, draft), [draft]);

    // Without a step in the address: the first one left to do when the page opened (never a jump as they fill it in)
    const [landing] = useState(() => firstIncomplete(draft));
    const requested = Number(params.get('step')) || 0;
    const reachable = firstIncomplete(draft);
    const stepIndex = Math.max(0, Math.min(requested ? requested - 1 : landing, reachable));
    const step = STEPS[stepIndex];
    const branch = branchById(draft.branchId);
    const size = sizeById(draft.sizeId);
    const quote = quoteOf(draft);
    const job: JobQuery | null = branch && size && draft.serviceIds.length ? { kind: 'new', branch: branch.id, size: size.id, services: draft.serviceIds } : null;
    const jobId = job ? jobKey(job) : '';
    const length = apiLength && apiLength.key === jobId ? apiLength.length.label : quote.label;

    // The link's branch and service are in the draft now: off the address, onto the first step left to do
    useEffect(() => {
        if (params.has('branch') || params.has('service')) {
            const service = serviceBySlug(params.get('service'));

            if (service && draft.sizeId && !rateFor(service, draft.sizeId)) {
                setPresetMissing(service.name);
            }

            setParams({ step: String(firstIncomplete(draft) + 1) }, { replace: true });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    // No step in the address, or one whose earlier steps aren't done: the address says where they really are
    useEffect(() => {
        if (requested - 1 !== stepIndex && !params.has('branch') && !params.has('service')) {
            setParams({ step: String(stepIndex + 1) }, { replace: true });
        }
    }, [requested, stepIndex, params, setParams]);

    // A new step: its heading gets focus (not on the first screen), and the top of the wizard comes into view
    useEffect(() => {
        if (!moved.current) {
            moved.current = true;
            return;
        }

        heading.current?.focus({ preventScroll: true });
        document.getElementById('wizard-top')?.scrollIntoView({ block: 'start' });
    }, [step]);

    const update = useCallback((change: Partial<BookDraft>, fields: string[] = []) => {
        setDraft((current) => ({ ...current, ...change }));
        setFormError(null);

        if (fields.length) {
            setShown((current) => Object.fromEntries(Object.entries(current).filter(([field]) => !fields.includes(field))));
            setServer((current) => Object.fromEntries(Object.entries(current).filter(([field]) => !fields.includes(field))));
        }
    }, []);

    /** A new branch or size: services that don't fit go, and so does the time */
    const reshape = (branchId: number | null, sizeId: number | null) => {
        const offered = servicesAt(branchById(branchId), sizeId).map((service) => service.id);
        const kept = draft.serviceIds.filter((id) => offered.includes(id));
        const missing = draft.serviceIds.find((id) => !offered.includes(id));
        setPresetMissing(missing && sizeId ? (serviceById(missing)?.name ?? null) : null);

        return { serviceIds: kept, date: null, slot: null };
    };

    const goTo = (index: number, push = true) => {
        setShown({});
        navigate({ search: `?step=${index + 1}` }, { replace: !push });
    };

    const next = () => {
        const problems = problemsOn(step, draft);

        if (Object.keys(problems).length) {
            setShown(problems);
            requestAnimationFrame(() => document.querySelector<HTMLElement>('[aria-invalid="true"], [data-error-anchor]')?.focus());
            return;
        }

        goTo(stepIndex + 1);
    };

    const back = () => {
        if (stepIndex > 0) {
            goTo(stepIndex - 1);
        }
    };

    const errorFor = (field: string): string | null => shown[field] ?? server[field]?.[0] ?? null;

    const reserve = async () => {
        if (!job || !branch || !size || !draft.date || !draft.slot) {
            return;
        }

        if (signInNeeded) {
            saveDraft(BOOK_DRAFT, draft);
            window.location.assign(signInHref(`/book?step=${STEPS.length}`));
            return;
        }

        setSubmitting(true);
        setFormError(null);

        const result = await postJson<{ ok: boolean; booking?: CustomerBooking; demo?: boolean; readyLabel?: string }>('/bookings', {
            branch: branch.id,
            size: size.id,
            services: draft.serviceIds,
            date: draft.date,
            time: draft.slot.time,
            name: draft.name.trim(),
            phone: draft.phone.trim(),
            email: draft.email.trim() || null,
            vehicle_model: draft.model.trim(),
            plate_number: draft.plate.trim().toUpperCase(),
            message: draft.message.trim() || null,
            consent: true,
            fax_number: '',
        });

        setSubmitting(false);

        if (result.kind === 'ok') {
            if (result.data.demo) {
                clearDraft(BOOK_DRAFT);
                setDemoDone({ readyLabel: result.data.readyLabel ?? null });
                window.scrollTo(0, 0);
                return;
            }

            if (result.data.booking) {
                forget(job);
                clearDraft(BOOK_DRAFT);
                navigate(result.data.booking.url, { replace: true });
                return;
            }
        }

        if (result.kind === 'signIn') {
            saveDraft(BOOK_DRAFT, draft);
            window.location.assign(signInHref(`/book?step=${STEPS.length}`));
            return;
        }

        if (result.kind === 'invalid') {
            const errors = result.errors;
            setServer(errors);

            if (errors.time) {
                // That time went to someone else (or isn't valid anymore): pick again from fresh times
                forget(job);
                setTimesRefresh((count) => count + 1);
                setDraft((current) => ({ ...current, slot: null }));
            }

            if (errors.date) {
                forget(job);
                setDraft((current) => ({ ...current, date: null, slot: null }));
            }

            const target = STEPS.findIndex((key) => Object.keys(errors).some((field) => FIELD_STEP[field] === key));

            if (target !== -1 && target !== stepIndex) {
                goTo(target);
            } else {
                setFormError(Object.values(errors)[0]?.[0] ?? text.errors.summary);
            }

            return;
        }

        setFormError(
            result.kind === 'conflict'
                ? (result.message ?? text.errors.failed)
                : result.kind === 'tooMany'
                  ? text.errors.tooMany
                  : result.kind === 'stale'
                    ? text.errors.stale
                    : text.errors.failed,
        );
    };

    const onMonth = useCallback((month: { length: JobLength }) => setApiLength({ key: jobId, length: month.length }), [jobId]);

    // The length from the server for the picked services, as soon as they change (it also warms the calendar)
    useEffect(() => {
        if (!job || step !== 'services') {
            return;
        }

        const timer = window.setTimeout(() => {
            fetchMonth(job).then((result) => {
                if (result.kind === 'ok') {
                    setApiLength({ key: jobId, length: result.data.length });
                }
            });
        }, 250);

        return () => window.clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [jobId, step]);

    if (demoDone) {
        return (
            <div className="mx-auto max-w-3xl px-4 py-16 sm:px-8 sm:py-24">
                <span className="grid size-12 place-items-center rounded-full bg-accent text-on-accent">
                    <Check className="size-6" />
                </span>
                <h1 className="font-display mt-6 text-[clamp(2rem,7vw,3rem)]">{text.demo.title}</h1>
                <p className="mt-4 text-lg leading-relaxed text-muted">{text.demo.body(demoDone.readyLabel)}</p>
                <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                    <Button to="/">{copy.nav.home}</Button>
                    <button
                        type="button"
                        className={buttonClass('outline')}
                        onClick={() => {
                            setDemoDone(null);
                            setDraft({ ...emptyBookDraft(), name: draft.name, phone: draft.phone, email: draft.email });
                            setParams({ step: '1' });
                        }}
                    >
                        {text.demo.again}
                    </button>
                </div>
            </div>
        );
    }

    const titleId = `step-${step}`;
    const multiday = quote.days > 0 && branch?.multidayDropoffBy ? text.services.multiday(clock(branch.multidayDropoffBy)) : null;
    const isReview = step === 'review';

    let body: ReactNode = null;

    if (step === 'branch') {
        body = (
            <>
                <StepHeading id={titleId} title={text.branch.title} headingRef={heading}>
                    {text.branch.body}
                </StepHeading>
                {bookableBranches.length === 0 && <Notice className="mb-5">{text.branch.noneOpen}</Notice>}
                <fieldset aria-labelledby={titleId} aria-describedby={errorFor('branch') ? 'branch-error' : undefined}>
                    <div className="grid gap-3 sm:grid-cols-2">
                        {site.branches.map((option) => {
                            const range = hoursRange(hoursToday(option));

                            return (
                                <ChoiceCard
                                    key={option.id}
                                    type="radio"
                                    name="branch"
                                    value={String(option.id)}
                                    checked={draft.branchId === option.id}
                                    disabled={!option.bookable}
                                    onChange={() => update({ branchId: option.id, ...(option.id !== draft.branchId ? reshape(option.id, draft.sizeId) : {}) }, ['branch'])}
                                >
                                    <span className="block font-semibold">{option.name}</span>
                                    {(option.address || option.city) && (
                                        <span className="mt-1 flex items-start gap-1.5 text-sm text-muted">
                                            <MapPin className="mt-0.5 size-4 shrink-0" /> {option.address ?? option.city}
                                        </span>
                                    )}
                                    <span className="mt-2 block text-sm">
                                        {option.status !== 'open'
                                            ? text.branch.comingSoon
                                            : !option.bookable
                                              ? text.branch.notBookable
                                              : range
                                                ? copy.branch.openToday(range)
                                                : copy.branch.closedToday}
                                    </span>
                                    {option.homeService.offered && (
                                        <span className="mt-2 inline-flex items-center gap-1.5 text-sm text-muted">
                                            <House className="size-4" /> {copy.branch.homeService}
                                        </span>
                                    )}
                                </ChoiceCard>
                            );
                        })}
                    </div>
                </fieldset>
                {errorFor('branch') && <ErrorText id="branch-error">{errorFor('branch')}</ErrorText>}
                {branch?.homeService.offered && (
                    <p className="mt-5">
                        <Link to={`/home-service?branch=${branch.slug}`} className="press inline-flex min-h-11 items-center gap-2 font-semibold text-link hover:underline hover:underline-offset-4">
                            <House className="size-4" /> {text.branch.homeLink(branch.name)}
                        </Link>
                    </p>
                )}
            </>
        );
    }

    if (step === 'vehicle') {
        body = (
            <>
                <StepHeading id={titleId} title={text.vehicle.title} headingRef={heading}>
                    {text.vehicle.body}
                </StepHeading>
                <fieldset aria-describedby={errorFor('size') ? 'size-error' : undefined}>
                    <legend className="font-semibold">{text.vehicle.size}</legend>
                    <div className="mt-3 grid gap-2.5 sm:grid-cols-3 xl:grid-cols-5">
                        {sizes.map((option) => {
                            const checked = draft.sizeId === option.id;

                            return (
                                <ChoiceCard
                                    key={option.id}
                                    type="radio"
                                    name="size"
                                    value={String(option.id)}
                                    checked={checked}
                                    onChange={() => update({ sizeId: option.id, ...(option.id !== draft.sizeId ? reshape(draft.branchId, option.id) : {}) }, ['size'])}
                                    className="sm:min-h-36 sm:flex-col sm:gap-3"
                                >
                                    <span className="flex items-center gap-4 sm:flex-col-reverse sm:items-start sm:gap-3">
                                        <span className="min-w-0 flex-1">
                                            <span className="block leading-snug font-semibold">{option.name}</span>
                                            {option.examples && <span className="mt-0.5 block text-sm text-muted">{copy.price.examples(option.examples)}</span>}
                                        </span>
                                        <span className={`block w-24 shrink-0 sm:w-full ${checked ? 'text-accent' : 'text-text/45'}`}>
                                            <VehicleSilhouette name={option.name} className="w-full" />
                                        </span>
                                    </span>
                                </ChoiceCard>
                            );
                        })}
                    </div>
                    {errorFor('size') && (
                        <span data-error-anchor tabIndex={-1}>
                            <ErrorText id="size-error">{errorFor('size')}</ErrorText>
                        </span>
                    )}
                </fieldset>
                <div className="mt-8 grid gap-5 sm:grid-cols-2">
                    <TextField
                        label={text.vehicle.model.label}
                        hint={text.vehicle.model.hint}
                        placeholder={text.vehicle.model.placeholder}
                        value={draft.model}
                        onChange={(value) => update({ model: value }, ['vehicle_model'])}
                        error={errorFor('vehicle_model')}
                        autoComplete="off"
                        maxLength={80}
                        name="vehicle_model"
                    />
                    <TextField
                        label={text.vehicle.plate.label}
                        hint={text.vehicle.plate.hint}
                        placeholder={text.vehicle.plate.placeholder}
                        value={draft.plate}
                        onChange={(value) => update({ plate: value }, ['plate_number'])}
                        error={errorFor('plate_number')}
                        autoComplete="off"
                        autoCapitalize="characters"
                        maxLength={15}
                        name="plate_number"
                        className="[&_input]:uppercase"
                    />
                </div>
            </>
        );
    }

    if (step === 'services' && branch && size) {
        const offered = servicesAt(branch, size.id);
        const groups = categories.map((category) => ({ category, services: offered.filter((service) => service.category.id === category.id) })).filter((group) => group.services.length);

        body = (
            <>
                <StepHeading id={titleId} title={text.services.title} headingRef={heading}>
                    {text.services.body(size.name, branch.name)}
                </StepHeading>
                {presetMissing && <Notice className="mb-5">{text.services.notOffered(presetMissing, size.name)}</Notice>}
                {offered.length === 0 ? (
                    <Notice>{text.services.none(size.name, branch.name)}</Notice>
                ) : (
                    <div className="space-y-8">
                        {groups.map(({ category, services }) => (
                            <fieldset key={category.id}>
                                <legend className="eyebrow flex items-center gap-2 text-muted">
                                    <CategoryIcon slug={category.slug} className="size-4 text-accent" /> {category.name}
                                </legend>
                                <div className="mt-3 grid gap-2.5">
                                    {services.map((service) => {
                                        const rate = rateFor(service, size.id)!;
                                        const checked = draft.serviceIds.includes(service.id);

                                        return (
                                            <ChoiceCard
                                                key={service.id}
                                                type="checkbox"
                                                name="services"
                                                value={String(service.id)}
                                                checked={checked}
                                                onChange={() =>
                                                    update(
                                                        { serviceIds: checked ? draft.serviceIds.filter((id) => id !== service.id) : [...draft.serviceIds, service.id], date: null, slot: null },
                                                        ['services'],
                                                    )
                                                }
                                            >
                                                <span className="flex flex-wrap items-start justify-between gap-x-4 gap-y-1">
                                                    <span className="font-semibold">{service.name}</span>
                                                    <span className="price">{peso(rate.price)}</span>
                                                </span>
                                                {service.summary && <span className="mt-1 block text-sm leading-relaxed text-muted">{service.summary}</span>}
                                                <span className="mt-1.5 block text-sm">{rate.duration}</span>
                                            </ChoiceCard>
                                        );
                                    })}
                                </div>
                            </fieldset>
                        ))}
                    </div>
                )}
                {errorFor('services') && (
                    <span data-error-anchor tabIndex={-1}>
                        <ErrorText>{errorFor('services')}</ErrorText>
                    </span>
                )}
                {multiday && <Notice className="mt-6">{multiday}</Notice>}
            </>
        );
    }

    if (step === 'date' && job) {
        body = (
            <>
                <StepHeading id={titleId} title={text.date.title} headingRef={heading}>
                    {text.date.body(length)}
                </StepHeading>
                {multiday && <Notice className="mb-5">{multiday}</Notice>}
                <div className="max-w-xl">
                    <AvailabilityCalendar job={job} selected={draft.date} onPick={(date) => update({ date, slot: draft.date === date ? draft.slot : null }, ['date', 'time'])} onMonth={onMonth} />
                </div>
                {errorFor('date') && (
                    <span data-error-anchor tabIndex={-1}>
                        <ErrorText>{errorFor('date')}</ErrorText>
                    </span>
                )}
            </>
        );
    }

    if (step === 'time' && job && draft.date) {
        body = (
            <>
                <StepHeading id={titleId} title={text.time.title} headingRef={heading}>
                    {text.time.body(longDate(draft.date))}
                </StepHeading>
                <TimeSlots
                    job={job}
                    date={draft.date}
                    selected={draft.slot?.time ?? null}
                    onPick={(slot) => update({ slot }, ['time'])}
                    refresh={timesRefresh}
                    error={server.time?.[0] ? `${server.time[0]} ${text.errors.timeTaken}` : null}
                />
                {shown.time && (
                    <span data-error-anchor tabIndex={-1}>
                        <ErrorText>{shown.time}</ErrorText>
                    </span>
                )}
                <button type="button" onClick={() => goTo(STEPS.indexOf('date'))} className="press mt-5 -ml-2 inline-flex min-h-11 items-center gap-2 rounded-control px-2 font-semibold text-link hover:underline hover:underline-offset-4">
                    <ArrowLeft className="size-4" /> {text.time.changeDay}
                </button>
            </>
        );
    }

    if (step === 'details') {
        const { fee, policy } = site.booking;

        body = (
            <>
                <StepHeading id={titleId} title={text.details.title} headingRef={heading}>
                    {text.details.body}
                </StepHeading>
                <div className="grid gap-5 sm:grid-cols-2">
                    <TextField label={text.details.name.label} value={draft.name} onChange={(value) => update({ name: value }, ['name'])} error={errorFor('name')} autoComplete="name" maxLength={120} name="name" />
                    <TextField
                        label={text.details.phone.label}
                        hint={text.details.phone.hint}
                        placeholder={text.details.phone.placeholder}
                        value={draft.phone}
                        onChange={(value) => update({ phone: value }, ['phone'])}
                        error={errorFor('phone')}
                        type="tel"
                        inputMode="tel"
                        autoComplete="tel"
                        maxLength={40}
                        name="phone"
                    />
                    <TextField
                        label={text.details.email.label}
                        hint={text.details.email.hint}
                        value={draft.email}
                        onChange={(value) => update({ email: value }, ['email'])}
                        error={errorFor('email')}
                        type="email"
                        inputMode="email"
                        autoComplete="email"
                        maxLength={190}
                        name="email"
                        optional
                        className="sm:col-span-2"
                    />
                    <TextField
                        label={text.details.message.label}
                        hint={text.details.message.hint}
                        value={draft.message}
                        onChange={(value) => update({ message: value }, ['message'])}
                        error={errorFor('message')}
                        multiline
                        maxLength={1000}
                        name="message"
                        optional
                        className="sm:col-span-2"
                    />
                </div>
                {/* People never see this; bots fill it in */}
                <div aria-hidden="true" className="absolute -left-[9999px] h-px w-px overflow-hidden">
                    <label>
                        Fax number <input type="text" name="fax_number" tabIndex={-1} autoComplete="off" defaultValue="" />
                    </label>
                </div>
                {fee.enabled && (
                    <Notice className="mt-8">
                        <p className="font-semibold">{text.fee.title}</p>
                        <p className="mt-1">{text.fee.body(fee.label, fee.holdMinutes)}</p>
                    </Notice>
                )}
                {policy && (
                    <div className="mt-6 panel px-4 py-4">
                        <p className="font-semibold">{text.details.policy}</p>
                        <p className="mt-1.5 text-[0.95rem] leading-relaxed whitespace-pre-line text-muted">{policy}</p>
                    </div>
                )}
                <div className="mt-6 panel px-4 py-4">
                    <p className="eyebrow mb-3 text-muted">{copy.form.privacyTitle}</p>
                    <Checkbox checked={draft.consent} onChange={(consent) => update({ consent }, ['consent'])} error={errorFor('consent')}>
                        {text.details.consent}
                    </Checkbox>
                </div>
            </>
        );
    }

    const canSubmit = isReview && !signInNeeded;
    const primary = isReview ? (
        canSubmit ? (
            <button type="button" onClick={reserve} disabled={submitting} className={buttonClass('accent', 'w-full text-base sm:w-auto sm:min-w-[14rem]')}>
                {submitting ? text.review.submitting : text.review.submit}
            </button>
        ) : null
    ) : (
        <button type="button" onClick={next} className={buttonClass('accent', 'w-full text-base sm:w-auto sm:min-w-[10rem]')}>
            {text.next} <ArrowRight className="size-4" />
        </button>
    );
    const backButton =
        stepIndex > 0 ? (
            <button type="button" onClick={back} className={buttonClass('outline', 'shrink-0 px-4!')} aria-label={text.back}>
                <ArrowLeft className="size-4" /> <span className="max-sm:sr-only">{text.back}</span>
            </button>
        ) : null;

    if (isReview) {
        const { fee } = site.booking;

        body = (
            <>
                <StepHeading id={titleId} title={text.review.title} headingRef={heading}>
                    {text.review.body}
                </StepHeading>
                <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_22rem] lg:gap-10 xl:grid-cols-[minmax(0,1fr)_24rem]">
                    <Summary draft={draft} length={length} onEdit={(target) => goTo(STEPS.indexOf(target))} />
                    <div className="space-y-5 lg:sticky lg:top-28">
                        {fee.enabled && (
                            <Notice>
                                <p className="font-semibold">{text.fee.title}</p>
                                <p className="mt-1">{text.fee.body(fee.label, fee.holdMinutes)}</p>
                            </Notice>
                        )}
                        {signInNeeded && <SignInBlock id="signin-title" title={text.signIn.title} body={text.signIn.body} returnPath={`/book?step=${STEPS.length}`} onLeave={() => saveDraft(BOOK_DRAFT, draft)} kept />}
                        {signInNeeded && branch && <ContactButtons target={branch} />}
                        {formError && (
                            <p role="alert" className="flex items-start gap-2 rounded-control border border-danger/60 bg-danger/8 px-4 py-3 font-medium">
                                {formError}
                            </p>
                        )}
                        {canSubmit && (
                            <div className="panel hidden p-5 lg:block">
                                <div className="flex items-center gap-3 [&>button:last-child]:flex-1">
                                    {backButton}
                                    {primary}
                                </div>
                                <p className="mt-4 text-sm leading-relaxed text-muted">
                                    {text.review.fine} {site.booking.fee.enabled && text.review.fineFee}
                                </p>
                            </div>
                        )}
                        {canSubmit && (
                            <p className="text-sm text-muted lg:hidden">
                                {text.review.fine} {site.booking.fee.enabled && text.review.fineFee}
                            </p>
                        )}
                        {!canSubmit && <div className="hidden lg:block">{backButton}</div>}
                    </div>
                </div>
            </>
        );
    }

    return (
        <div className="mx-auto max-w-7xl px-4 pt-8 pb-36 sm:px-8 sm:pt-10 lg:pb-20 xl:px-12">
            <div id="wizard-top" className="flex scroll-mt-24 items-center gap-4">
                <h1 className="eyebrow text-muted">{text.title}</h1>
                <span aria-hidden="true" className="gold-rule block w-14" />
            </div>

            {/* Where they are: a bar on phones, every step from md up (earlier ones open again) */}
            <nav aria-label={text.title} className="mt-6">
                <p className="text-sm font-semibold md:hidden">
                    {text.stepOf(stepIndex + 1, STEPS.length)} · <span className="text-muted">{text.steps[step]}</span>
                </p>
                <div className="mt-2 h-1 overflow-hidden rounded-full bg-surface-3 md:hidden" aria-hidden="true">
                    <div className="h-full rounded-full bg-accent" style={{ width: `${((stepIndex + 1) / STEPS.length) * 100}%` }} />
                </div>
                <ol className="hidden gap-1 md:flex">
                    {STEPS.map((key, index) => {
                        const done = index < stepIndex;
                        const current = index === stepIndex;
                        const open = index <= reachable && !current;

                        return (
                            <li key={key} className="min-w-0 flex-1">
                                <button
                                    type="button"
                                    disabled={!open}
                                    onClick={() => goTo(index)}
                                    aria-current={current ? 'step' : undefined}
                                    className={`press flex min-h-14 w-full min-w-0 flex-col items-start gap-1.5 rounded-none border-t-2 pt-2.5 pr-2 text-left text-sm font-semibold disabled:cursor-default ${
                                        current ? 'border-accent text-text' : done ? 'border-text/35 text-text/80 hover:border-text/60 hover:text-text' : 'border-line text-muted'
                                    }`}
                                >
                                    <span className="font-display flex items-center gap-1.5 text-[0.8rem] tabular">
                                        <span className={current ? 'text-accent' : ''}>{String(index + 1).padStart(2, '0')}</span>
                                        {done && <Check className="size-3.5 text-accent" />}
                                    </span>
                                    <span className="truncate">{text.steps[key]}</span>
                                </button>
                            </li>
                        );
                    })}
                </ol>
            </nav>

            <div className={`mt-8 grid gap-10 md:mt-10 ${isReview ? '' : 'lg:grid-cols-[minmax(0,1fr)_22rem] lg:gap-14 xl:grid-cols-[minmax(0,1fr)_24rem]'}`}>
                <section aria-labelledby={titleId} className="min-w-0">
                    {body}

                    {!isReview && formError && (
                        <p role="alert" className="mt-6 flex items-start gap-2 rounded-control border border-danger/60 bg-danger/8 px-4 py-3 font-medium">
                            {formError}
                        </p>
                    )}

                    {/* Wide screens: the buttons under the step (on the review, beside the ticket) */}
                    {!isReview && (
                        <div className="mt-8 hidden items-center gap-3 lg:flex">
                            {backButton}
                            {primary}
                        </div>
                    )}
                </section>

                <aside aria-labelledby="summary-title" className={isReview ? 'hidden' : 'hidden lg:block'}>
                    <div className="sticky top-28">
                        <h2 id="summary-title" className="sr-only">
                            {text.summary.title}
                        </h2>
                        <Summary draft={draft} length={length} compact />
                    </div>
                </aside>
            </div>

            {/* Phones and tablets: the total and the next move, pinned to the bottom */}
            <div className="fixed inset-x-0 bottom-0 z-40 border-t border-line bg-surface-2/97 shadow-bar backdrop-blur-md lg:hidden">
                <div className="mx-auto flex max-w-3xl items-center gap-3 px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] sm:px-8">
                    <div className="min-w-0 flex-1">
                        {quote.lines.length > 0 ? (
                            <>
                                <p className="price text-lg leading-tight">{peso(quote.total)}</p>
                                <p className="truncate text-sm text-muted">
                                    {text.services.picked(quote.lines.length)} · {length}
                                </p>
                            </>
                        ) : (
                            <p className="truncate text-sm text-muted">{text.services.nothingYet}</p>
                        )}
                    </div>
                    {backButton}
                    {primary && <div className="shrink-0 [&>button]:min-w-0! [&>button]:px-5">{primary}</div>}
                </div>
            </div>

            {/* Wide screens: the total is in the summary; screen readers hear it change */}
            <p className="sr-only" aria-live="polite">
                {quote.lines.length > 0 ? `${text.review.total} ${peso(quote.total)}. ${text.services.takes(length)}.` : ''}
            </p>
            {draft.date && step === 'date' && (
                <p className="sr-only" aria-live="polite">
                    {shortDate(draft.date)}
                </p>
            )}
        </div>
    );
}
