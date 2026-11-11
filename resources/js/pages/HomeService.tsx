import { type FormEvent, type ReactNode, useEffect, useMemo, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';

import Button, { buttonClass } from '../components/Button';
import Calendar from '../components/Calendar';
import { Checkbox, ChoiceCard, Chips, ErrorText, Notice, TextField } from '../components/Form';
import { PageHeader } from '../components/Heading';
import { ParkedCar } from '../components/Studio';
import { ArrowRight, Bolt, Check, Droplet, House, Shade } from '../components/Icons';
import SignInBlock from '../components/SignInBlock';
import { type FieldErrors, postJson } from '../lib/api';
import { guest, signInHref, signInNeeded } from '../lib/auth';
import { pageCars } from '../lib/cars';
import { copy } from '../lib/copy';
import { addDays, addMonths, daysInMonth, isoDate, longDate, manilaToday, monthStart, parseDate } from '../lib/dates';
import { clearDraft, emailLooksWrong, loadDraft, phoneProblem, plateLooksWrong, saveDraft } from '../lib/draft';
import { branchById, branchBySlug, homeBranches, lengthLabel, peso, rateFor, serviceById, serviceBySlug, servicesAt, site, sizeById, sizes } from '../lib/site';
import usePageTitle from '../lib/usePageTitle';
import type { CustomerBooking, HomeWindow } from '../types';

const text = copy.homeService;
const DRAFT = 'app826-home-draft';
const OTHER = 'Other';

type Answer = '' | 'yes' | 'no' | 'unsure';

type HomeDraft = {
    branchId: number | null;
    sizeId: number | null;
    serviceIds: number[];
    date: string | null;
    window: string;
    address: string;
    area: string;
    areaOther: string;
    landmark: string;
    water: Answer;
    power: Answer;
    shade: Answer;
    model: string;
    plate: string;
    name: string;
    phone: string;
    email: string;
    message: string;
    consent: boolean;
};

const empty = (): HomeDraft => ({
    branchId: homeBranches.length === 1 ? homeBranches[0].id : null,
    sizeId: null,
    serviceIds: [],
    date: null,
    window: '',
    address: '',
    area: '',
    areaOther: '',
    landmark: '',
    water: '',
    power: '',
    shade: '',
    model: '',
    plate: '',
    name: guest?.name ?? '',
    phone: '',
    email: guest?.email ?? '',
    message: '',
    consent: false,
});

function startingDraft(params: URLSearchParams): HomeDraft {
    const saved = loadDraft(DRAFT, empty());
    const branch = branchBySlug(params.get('branch'));
    const service = serviceBySlug(params.get('service'));
    const draft = { ...saved };

    if (branch?.homeService.offered) {
        draft.branchId = branch.id;
    }

    if (service?.homeServiceOk) {
        draft.serviceIds = [service.id];
    }

    return { ...draft, name: draft.name || guest?.name || '', email: draft.email || guest?.email || '' };
}

const toBoolean = (answer: Answer) => (answer === 'yes' ? true : answer === 'no' ? false : null);

/** The last day the server takes before it says (the end of the third month, its default) */
function fallbackLast(): string {
    const month = addMonths(manilaToday().slice(0, 7), 3);

    return isoDate(new Date(monthStart(month).getFullYear(), monthStart(month).getMonth(), daysInMonth(monthStart(month))));
}

function Section({ number, title, children, id }: { number: number; title: string; children: ReactNode; id: string }) {
    return (
        <section aria-labelledby={id} className="border-t border-line pt-8 first:border-t-0 first:pt-0">
            <h2 id={id} className="flex items-center gap-3 text-xl font-bold">
                <span aria-hidden="true" className="price grid size-8 shrink-0 place-items-center rounded-full bg-accent/12 text-sm tabular">
                    {number}
                </span>
                {title}
            </h2>
            <div className="mt-5">{children}</div>
        </section>
    );
}

export default function HomeService() {
    usePageTitle(text.title);
    const [params] = useSearchParams();
    const navigate = useNavigate();
    const [draft, setDraft] = useState<HomeDraft>(() => startingDraft(params));
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [formError, setFormError] = useState<string | null>(null);
    const [sending, setSending] = useState(false);
    const [demoDone, setDemoDone] = useState(false);
    const [last, setLast] = useState<string>(fallbackLast);
    const tomorrow = isoDate(addDays(parseDate(manilaToday()), 1));
    const [month, setMonth] = useState(() => (draft.date ?? tomorrow).slice(0, 7));

    useEffect(() => saveDraft(DRAFT, draft), [draft]);

    const branch = branchById(draft.branchId);
    const size = sizeById(draft.sizeId);
    const offered = useMemo(() => servicesAt(branch, draft.sizeId, { home: true }), [branch, draft.sizeId]);
    const picked = draft.serviceIds.map(serviceById).filter((service): service is NonNullable<typeof service> => Boolean(service && size && rateFor(service, size.id)));
    const rates = picked.map((service) => rateFor(service, draft.sizeId)!);
    const total = rates.reduce((sum, rate) => sum + rate.price, 0);
    const length = lengthLabel(
        rates.reduce((sum, rate) => sum + rate.days, 0),
        rates.reduce((sum, rate) => sum + rate.minutes, 0),
    );
    const areas = branch?.homeService.areas ?? [];

    // How far ahead the branch books, from the branch itself
    useEffect(() => {
        if (branch?.lastDay) {
            setLast(branch.lastDay);
        }
    }, [branch?.lastDay]);

    const update = (change: Partial<HomeDraft>, fields: string[] = []) => {
        setDraft((current) => ({ ...current, ...change }));
        setFormError(null);

        if (fields.length) {
            setErrors((current) => Object.fromEntries(Object.entries(current).filter(([field]) => !fields.includes(field))));
        }
    };

    /** Branch or size changed: only services that still fit stay */
    const keepFitting = (branchId: number | null, sizeId: number | null) => {
        const fits = servicesAt(branchById(branchId), sizeId, { home: true }).map((service) => service.id);

        return draft.serviceIds.filter((id) => fits.includes(id));
    };

    const check = (): Record<string, string> => {
        const e = copy.book.errors;
        const problems: Record<string, string> = {};

        if (!branch) problems.branch = text.errors.branch;
        if (!size) problems.size = e.size;
        if (picked.length === 0) problems.services = e.services;
        if (!draft.date) problems.preferred_date = text.errors.day;
        if (!draft.window) problems.preferred_window = text.errors.window;
        if (!draft.address.trim()) problems.address = text.errors.address;
        if (areas.length > 0 && !draft.area) problems.area = text.errors.area;
        if (draft.area === OTHER && !draft.areaOther.trim()) problems.area_other = text.errors.areaOther;
        if (!draft.model.trim()) problems.vehicle_model = e.model;
        if (!draft.plate.trim()) problems.plate_number = e.plate;
        else if (plateLooksWrong(draft.plate)) problems.plate_number = e.plateFormat;
        if (!draft.name.trim()) problems.name = e.name;
        const phone = phoneProblem(draft.phone);
        if (phone) problems.phone = phone === 'missing' ? e.phone : e.phoneFormat;
        if (emailLooksWrong(draft.email)) problems.email = e.email;
        if (!draft.consent) problems.consent = e.consent;

        return problems;
    };

    const focusFirstError = () => requestAnimationFrame(() => document.querySelector<HTMLElement>('[aria-invalid="true"], [data-error-anchor]')?.focus());

    const submit = async (event: FormEvent) => {
        event.preventDefault();
        const problems = check();

        if (Object.keys(problems).length) {
            setErrors(problems);
            setFormError(copy.form.failedNotice);
            focusFirstError();
            return;
        }

        if (signInNeeded) {
            saveDraft(DRAFT, draft);
            window.location.assign(signInHref('/home-service'));
            return;
        }

        setSending(true);
        setFormError(null);

        const result = await postJson<{ ok: boolean; booking?: CustomerBooking; demo?: boolean }>('/home-requests', {
            branch: branch!.id,
            size: size!.id,
            services: picked.map((service) => service.id),
            preferred_date: draft.date,
            preferred_window: draft.window as HomeWindow,
            address: draft.address.trim(),
            area: areas.length > 0 ? draft.area : draft.area || null,
            area_other: draft.area === OTHER ? draft.areaOther.trim() : null,
            landmark: draft.landmark.trim() || null,
            has_water: toBoolean(draft.water),
            has_power: toBoolean(draft.power),
            has_shade: toBoolean(draft.shade),
            name: draft.name.trim(),
            phone: draft.phone.trim(),
            email: draft.email.trim() || null,
            vehicle_model: draft.model.trim(),
            plate_number: draft.plate.trim().toUpperCase(),
            message: draft.message.trim() || null,
            consent: true,
            fax_number: '',
        });

        setSending(false);

        if (result.kind === 'ok') {
            clearDraft(DRAFT);

            if (result.data.booking) {
                navigate(result.data.booking.url, { replace: true });
            } else {
                setDemoDone(true);
                window.scrollTo(0, 0);
            }

            return;
        }

        if (result.kind === 'signIn') {
            saveDraft(DRAFT, draft);
            window.location.assign(signInHref('/home-service'));
            return;
        }

        if (result.kind === 'invalid') {
            const fieldErrors: FieldErrors = result.errors;
            setErrors(Object.fromEntries(Object.entries(fieldErrors).map(([field, messages]) => [field, messages[0]])));
            setFormError(copy.form.failedNotice);
            focusFirstError();
            return;
        }

        setFormError(
            result.kind === 'conflict'
                ? (result.message ?? copy.book.errors.failed)
                : result.kind === 'tooMany'
                  ? copy.book.errors.tooMany
                  : result.kind === 'stale'
                    ? copy.book.errors.stale
                    : copy.book.errors.failed,
        );
    };

    if (demoDone) {
        return (
            <div className="mx-auto max-w-3xl px-4 py-16 sm:px-8 sm:py-24">
                <span className="grid size-12 place-items-center rounded-full bg-accent text-on-accent">
                    <Check className="size-6" />
                </span>
                <h1 className="font-display mt-6 text-[clamp(2rem,7vw,3rem)]">{text.demo.title}</h1>
                <p className="mt-4 text-lg leading-relaxed text-muted">{text.demo.body}</p>
                <Button to="/" className="mt-8">
                    {copy.nav.home}
                </Button>
            </div>
        );
    }

    if (homeBranches.length === 0) {
        return (
            <>
                <PageHeader eyebrow={copy.nav.homeService} title={text.title}>
                    <p>{text.none}</p>
                </PageHeader>
                <div className="mx-auto max-w-7xl px-4 py-12 sm:px-8 xl:px-12">
                    <Button to="/book">
                        {copy.cta.reserve} <ArrowRight className="size-4" />
                    </Button>
                </div>
            </>
        );
    }

    const answerOptions: { value: Exclude<Answer, ''>; label: string }[] = [
        { value: 'yes', label: text.answers.yes },
        { value: 'no', label: text.answers.no },
        { value: 'unsure', label: text.answers.unsure },
    ];
    const windowOptions = (Object.entries(site.booking.windows) as [HomeWindow, string][]).map(([value, label]) => ({ value, label }));

    return (
        <>
            <PageHeader
                eyebrow={copy.nav.homeService}
                title={text.title}
                carOnPhones={false}
                car={<ParkedCar car={pageCars.home.car} face={pageCars.home.face} sizes="(min-width: 1024px) 46vw, 1px" contact={pageCars.home.contact} shadow={pageCars.home.shadow} />}
            >
                <p>{text.intro}</p>
            </PageHeader>

            <div className="mx-auto grid max-w-7xl gap-10 px-4 pt-10 pb-20 sm:px-8 lg:grid-cols-[minmax(0,1fr)_22rem] lg:gap-14 xl:px-12">
                <form onSubmit={submit} noValidate className="min-w-0 space-y-10">
                    <Section number={1} id="home-branch" title={text.sections.branch}>
                        <fieldset aria-labelledby="home-branch">
                            <div className="grid gap-3 sm:grid-cols-2">
                                {homeBranches.map((option) => (
                                    <ChoiceCard
                                        key={option.id}
                                        type="radio"
                                        name="branch"
                                        value={String(option.id)}
                                        checked={draft.branchId === option.id}
                                        onChange={() => update({ branchId: option.id, serviceIds: keepFitting(option.id, draft.sizeId), area: '', areaOther: '' }, ['branch', 'area'])}
                                    >
                                        <span className="block font-semibold">{option.name}</span>
                                        {option.homeService.areas.length > 0 && <span className="mt-1 block text-sm text-muted">{option.homeService.areas.join(', ')}</span>}
                                    </ChoiceCard>
                                ))}
                            </div>
                        </fieldset>
                        {errors.branch && (
                            <span data-error-anchor tabIndex={-1}>
                                <ErrorText>{errors.branch}</ErrorText>
                            </span>
                        )}
                    </Section>

                    <Section number={2} id="home-vehicle" title={text.sections.vehicle}>
                        <fieldset>
                            <legend className="font-semibold">{copy.book.vehicle.size}</legend>
                            <div className="mt-3 grid gap-2.5 sm:grid-cols-2">
                                {sizes.map((option) => (
                                    <ChoiceCard
                                        key={option.id}
                                        type="radio"
                                        name="size"
                                        value={String(option.id)}
                                        checked={draft.sizeId === option.id}
                                        onChange={() => update({ sizeId: option.id, serviceIds: keepFitting(draft.branchId, option.id) }, ['size'])}
                                    >
                                        <span className="block font-semibold">{option.name}</span>
                                        {option.examples && <span className="mt-0.5 block text-sm text-muted">{copy.price.examples(option.examples)}</span>}
                                    </ChoiceCard>
                                ))}
                            </div>
                            {errors.size && (
                                <span data-error-anchor tabIndex={-1}>
                                    <ErrorText>{errors.size}</ErrorText>
                                </span>
                            )}
                        </fieldset>
                        <div className="mt-6 grid gap-5 sm:grid-cols-2">
                            <TextField
                                label={copy.book.vehicle.model.label}
                                hint={copy.book.vehicle.model.hint}
                                placeholder={copy.book.vehicle.model.placeholder}
                                value={draft.model}
                                onChange={(value) => update({ model: value }, ['vehicle_model'])}
                                error={errors.vehicle_model}
                                maxLength={80}
                                autoComplete="off"
                            />
                            <TextField
                                label={copy.book.vehicle.plate.label}
                                hint={copy.book.vehicle.plate.hint}
                                placeholder={copy.book.vehicle.plate.placeholder}
                                value={draft.plate}
                                onChange={(value) => update({ plate: value }, ['plate_number'])}
                                error={errors.plate_number}
                                maxLength={15}
                                autoComplete="off"
                                autoCapitalize="characters"
                                className="[&_input]:uppercase"
                            />
                        </div>
                    </Section>

                    <Section number={3} id="home-services" title={text.sections.services}>
                        {!branch || !size ? (
                            <p className="text-muted">{text.pickSizeFirst}</p>
                        ) : offered.length === 0 ? (
                            <Notice>{text.servicesNone}</Notice>
                        ) : (
                            <fieldset aria-labelledby="home-services">
                                <p className="mb-3 text-muted">{text.servicesBody}</p>
                                <div className="grid gap-2.5">
                                    {offered.map((service) => {
                                        const rate = rateFor(service, size.id)!;
                                        const checked = draft.serviceIds.includes(service.id);

                                        return (
                                            <ChoiceCard
                                                key={service.id}
                                                type="checkbox"
                                                name="services"
                                                value={String(service.id)}
                                                checked={checked}
                                                onChange={() => update({ serviceIds: checked ? draft.serviceIds.filter((id) => id !== service.id) : [...draft.serviceIds, service.id] }, ['services'])}
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
                        )}
                        {errors.services && (
                            <span data-error-anchor tabIndex={-1}>
                                <ErrorText>{errors.services}</ErrorText>
                            </span>
                        )}
                        <p className="mt-4 font-semibold" aria-live="polite">
                            {picked.length > 0 ? `${text.total}: ${peso(total)} · ${text.takes(length)}` : ''}
                        </p>
                    </Section>

                    <Section number={4} id="home-when" title={text.sections.when}>
                        <div className="max-w-xl">
                            <p className="font-semibold">{text.day}</p>
                            <p className="mt-1 mb-4 text-sm text-muted">{text.dayHint}</p>
                            <Calendar
                                month={month}
                                onMonthChange={setMonth}
                                minMonth={tomorrow.slice(0, 7)}
                                maxMonth={last.slice(0, 7)}
                                stateOf={(iso) => (iso >= tomorrow && iso <= last ? 'open' : 'closed')}
                                selected={draft.date}
                                onPick={(date) => update({ date }, ['preferred_date'])}
                                showFull={false}
                                legend={false}
                                status={
                                    <p className="text-sm font-medium" aria-live="polite">
                                        {draft.date ? longDate(draft.date) : ''}
                                    </p>
                                }
                            />
                            {errors.preferred_date && (
                                <span data-error-anchor tabIndex={-1}>
                                    <ErrorText>{errors.preferred_date}</ErrorText>
                                </span>
                            )}
                        </div>
                        <div className="mt-8">
                            <Chips legend={text.window} name="window" options={windowOptions} value={(draft.window || null) as HomeWindow | null} onChange={(value) => update({ window: value }, ['preferred_window'])} error={errors.preferred_window} />
                        </div>
                    </Section>

                    <Section number={5} id="home-where" title={text.sections.where}>
                        <div className="grid gap-6">
                            <TextField
                                label={text.address.label}
                                hint={text.address.hint}
                                value={draft.address}
                                onChange={(value) => update({ address: value }, ['address'])}
                                error={errors.address}
                                multiline
                                rows={2}
                                maxLength={500}
                                autoComplete="street-address"
                            />
                            {areas.length > 0 && (
                                <Chips
                                    legend={text.area}
                                    name="area"
                                    options={[...areas, OTHER].map((area) => ({ value: area, label: area === OTHER ? text.other : area }))}
                                    value={draft.area || null}
                                    onChange={(value) => update({ area: value }, ['area', 'area_other'])}
                                    error={errors.area}
                                />
                            )}
                            {(draft.area === OTHER || (areas.length === 0 && branch)) && (
                                <TextField
                                    label={text.areaOther.label}
                                    hint={text.areaOther.hint}
                                    value={areas.length === 0 ? draft.area : draft.areaOther}
                                    onChange={(value) => (areas.length === 0 ? update({ area: value }, ['area']) : update({ areaOther: value }, ['area_other']))}
                                    error={errors.area_other ?? (areas.length === 0 ? errors.area : null)}
                                    maxLength={120}
                                    autoComplete="address-level2"
                                    optional={areas.length === 0}
                                />
                            )}
                            <TextField label={text.landmark.label} hint={text.landmark.hint} value={draft.landmark} onChange={(value) => update({ landmark: value }, ['landmark'])} error={errors.landmark} maxLength={200} optional />
                        </div>
                    </Section>

                    <Section number={6} id="home-setup" title={text.sections.setup}>
                        <p className="-mt-2 mb-5 text-muted">{text.setupBody}</p>
                        <div className="grid gap-6">
                            {(
                                [
                                    ['water', text.water, Droplet],
                                    ['power', text.power, Bolt],
                                    ['shade', text.shade, Shade],
                                ] as const
                            ).map(([field, question, Icon]) => (
                                <div key={field} className="flex gap-3">
                                    <Icon className="mt-0.5 size-5 shrink-0 text-accent" />
                                    <div className="min-w-0 flex-1">
                                        <Chips legend={question} name={field} options={answerOptions} value={draft[field] || null} onChange={(value) => update({ [field]: value } as Partial<HomeDraft>)} legendClass="font-medium" />
                                    </div>
                                </div>
                            ))}
                        </div>
                    </Section>

                    <Section number={7} id="home-contact" title={text.sections.contact}>
                        <div className="grid gap-5 sm:grid-cols-2">
                            <TextField label={copy.book.details.name.label} value={draft.name} onChange={(value) => update({ name: value }, ['name'])} error={errors.name} autoComplete="name" maxLength={120} />
                            <TextField
                                label={copy.book.details.phone.label}
                                hint={copy.book.details.phone.hint}
                                placeholder={copy.book.details.phone.placeholder}
                                value={draft.phone}
                                onChange={(value) => update({ phone: value }, ['phone'])}
                                error={errors.phone}
                                type="tel"
                                inputMode="tel"
                                autoComplete="tel"
                                maxLength={40}
                            />
                            <TextField
                                label={copy.book.details.email.label}
                                hint={copy.book.details.email.hint}
                                value={draft.email}
                                onChange={(value) => update({ email: value }, ['email'])}
                                error={errors.email}
                                type="email"
                                inputMode="email"
                                autoComplete="email"
                                maxLength={190}
                                optional
                                className="sm:col-span-2"
                            />
                            <TextField
                                label={copy.book.details.message.label}
                                hint={copy.book.details.message.hint}
                                value={draft.message}
                                onChange={(value) => update({ message: value }, ['message'])}
                                error={errors.message}
                                multiline
                                maxLength={1000}
                                optional
                                className="sm:col-span-2"
                            />
                        </div>
                        <div aria-hidden="true" className="absolute -left-[9999px] h-px w-px overflow-hidden">
                            <label>
                                Fax number <input type="text" name="fax_number" tabIndex={-1} autoComplete="off" defaultValue="" />
                            </label>
                        </div>
                        {site.booking.policy && (
                            <div className="mt-6 panel px-4 py-4">
                                <p className="font-semibold">{copy.book.details.policy}</p>
                                <p className="mt-1.5 text-[0.95rem] leading-relaxed whitespace-pre-line text-muted">{site.booking.policy}</p>
                            </div>
                        )}
                        <div className="mt-6 panel px-4 py-4">
                            <p className="eyebrow mb-3 text-muted">{copy.form.privacyTitle}</p>
                            <Checkbox checked={draft.consent} onChange={(consent) => update({ consent }, ['consent'])} error={errors.consent}>
                                {copy.book.details.consent}
                            </Checkbox>
                        </div>
                    </Section>

                    <div className="border-t border-line pt-8">
                        {signInNeeded && (
                            <div className="mb-6">
                                <SignInBlock id="home-signin" title={text.signIn.title} body={text.signIn.body} returnPath="/home-service" onLeave={() => saveDraft(DRAFT, draft)} kept />
                            </div>
                        )}
                        {formError && (
                            <p role="alert" className="mb-5 rounded-control border border-danger/60 bg-danger/8 px-4 py-3 font-medium">
                                {formError}
                            </p>
                        )}
                        {!signInNeeded && (
                            <button type="submit" disabled={sending} className={buttonClass('accent', 'w-full text-base sm:w-auto sm:min-w-[14rem]')}>
                                {sending ? text.submitting : text.submit}
                            </button>
                        )}
                        <p className="mt-4 text-sm text-muted">{text.fine}</p>
                    </div>
                </form>

                <aside aria-labelledby="home-summary" className="hidden lg:block">
                    <div className="sticky top-28 panel p-6">
                        <h2 id="home-summary" className="flex items-center gap-2.5 text-lg font-bold">
                            <House className="size-5 text-accent" /> {copy.book.summary.title}
                        </h2>
                        <dl className="mt-4 space-y-4 text-[0.95rem]">
                            {branch && (
                                <div>
                                    <dt className="eyebrow text-[0.68rem] text-muted">{copy.book.review.branch}</dt>
                                    <dd className="mt-1 font-semibold">{branch.name}</dd>
                                </div>
                            )}
                            {size && (
                                <div>
                                    <dt className="eyebrow text-[0.68rem] text-muted">{copy.book.review.vehicle}</dt>
                                    <dd className="mt-1">{[size.name, draft.model.trim()].filter(Boolean).join(' · ')}</dd>
                                </div>
                            )}
                            {picked.length > 0 && (
                                <div>
                                    <dt className="eyebrow text-[0.68rem] text-muted">{copy.book.review.services}</dt>
                                    <dd className="mt-1">
                                        <ul className="space-y-1">
                                            {picked.map((service) => (
                                                <li key={service.id} className="flex justify-between gap-3">
                                                    <span>{service.name}</span>
                                                    <span className="price">{peso(rateFor(service, draft.sizeId)!.price)}</span>
                                                </li>
                                            ))}
                                        </ul>
                                    </dd>
                                </div>
                            )}
                            {draft.date && (
                                <div>
                                    <dt className="eyebrow text-[0.68rem] text-muted">{copy.booking.preferred}</dt>
                                    <dd className="mt-1">
                                        {longDate(draft.date)}
                                        {draft.window && <span className="text-muted"> · {site.booking.windows[draft.window as HomeWindow]}</span>}
                                    </dd>
                                </div>
                            )}
                        </dl>
                        {picked.length > 0 ? (
                            <div className="mt-5 flex items-end justify-between gap-3 border-t border-line pt-4">
                                <p>
                                    <span className="block font-semibold">{text.total}</span>
                                    <span className="text-sm text-muted">{text.takes(length)}</span>
                                </p>
                                <p className="price text-xl">{peso(total)}</p>
                            </div>
                        ) : (
                            !branch && <p className="mt-4 text-muted">{copy.book.summary.empty}</p>
                        )}
                    </div>
                </aside>
            </div>
        </>
    );
}
