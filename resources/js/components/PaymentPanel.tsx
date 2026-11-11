import { type DragEvent, type FormEvent, type ReactNode, useEffect, useId, useRef, useState } from 'react';

import { copyText, fileSize, PROOF_ACCEPT, proofProblem, uploadProof } from '../lib/bookings';
import { copy } from '../lib/copy';
import { branchById, peso } from '../lib/site';
import type { CustomerBooking, PaymentMethod, PaymentMethodKey } from '../types';
import { ContactButtons } from './BranchParts';
import { buttonClass } from './Button';
import { Check, Copy, Download, Expand, ImagePlus, Info, Trash, Warning } from './Icons';
import Modal from './Modal';

const text = copy.pay;

const smallButton =
    'press inline-flex min-h-11 items-center gap-1.5 rounded-control border border-field/70 px-3 text-sm font-semibold hover:border-text hover:bg-text/5 disabled:opacity-50';

/**
 * The reservation fee, and the whole job's price it counts toward.
 */
function AmountDue({ booking }: { booking: CustomerBooking }) {
    if (booking.amountDue === null) {
        return null;
    }

    return (
        <div className="mt-6 panel p-5 sm:p-6">
            <p className="text-sm font-semibold text-muted">{text.amount.label}</p>
            <p className="price mt-1 text-[2.25rem] leading-none">{peso(booking.amountDue)}</p>
            {booking.totalPrice !== null && booking.totalPrice > booking.amountDue && (
                <p className="mt-4 border-t border-line pt-4 text-[0.95rem] text-muted">{text.amount.total(peso(booking.totalPrice))}</p>
            )}
        </div>
    );
}

/**
 * GCash or Maya, as radio cards (text labels only, no app logos).
 */
function MethodChoice({ methods, value, onChange }: { methods: PaymentMethod[]; value: PaymentMethodKey; onChange: (key: PaymentMethodKey) => void }) {
    const name = useId();

    return (
        <fieldset>
            <legend className="sr-only">{text.methods}</legend>
            <div className="grid grid-cols-2 gap-3">
                {methods.map((method) => (
                    <label
                        key={method.key}
                        className="press relative flex min-h-14 cursor-pointer items-center gap-3 rounded-control border border-field/70 bg-surface-2 px-4 hover:border-text/60 has-checked:border-accent has-checked:bg-accent/8 has-checked:ring-1 has-checked:ring-accent has-focus-visible:outline-2 has-focus-visible:outline-offset-3 has-focus-visible:outline-accent"
                    >
                        <input type="radio" name={name} value={method.key} checked={value === method.key} onChange={() => onChange(method.key)} className="peer sr-only" />
                        <span aria-hidden="true" className="grid size-5 shrink-0 place-items-center rounded-full border border-field peer-checked:border-accent peer-checked:bg-accent">
                            {value === method.key && <span className="size-2 rounded-full bg-on-accent" />}
                        </span>
                        <span className="text-[1.05rem] font-semibold">{method.label}</span>
                    </label>
                ))}
            </div>
        </fieldset>
    );
}

/**
 * One line of the account (name or number) with its copy button; the
 * number is copied without its spaces.
 */
function AccountLine({ label, value, copyValue, mono = false, onCopied }: { label: string; value: string; copyValue: string; mono?: boolean; onCopied: (what: string) => void }) {
    const [copied, setCopied] = useState(false);

    useEffect(() => {
        if (!copied) {
            return;
        }

        const timer = window.setTimeout(() => setCopied(false), 2200);

        return () => window.clearTimeout(timer);
    }, [copied]);

    return (
        <div className="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
            <div className="min-w-0">
                <dt className="text-sm text-muted">{label}</dt>
                <dd className={`mt-0.5 font-semibold break-words ${mono ? 'price text-[1.2rem] tracking-[0.03em]' : ''}`}>{value}</dd>
            </div>
            <button
                type="button"
                onClick={async () => {
                    if (await copyText(copyValue)) {
                        setCopied(true);
                        onCopied(label);
                    }
                }}
                className={`${smallButton} shrink-0`}
            >
                {copied ? <Check className="size-4 text-accent" /> : <Copy className="size-4" />}
                {copied ? text.account.copied : text.account.copy}
                <span className="sr-only"> {label.toLowerCase()}</span>
            </button>
        </div>
    );
}

/**
 * The method's QR (tap for it large), its account and the owner's note.
 */
function PayWith({ method, instructions }: { method: PaymentMethod; instructions: string | null }) {
    const [enlarged, setEnlarged] = useState(false);
    const [announcement, setAnnouncement] = useState('');
    const hasAccount = method.accountName || method.accountNumber;

    return (
        <div className="grid gap-6 sm:grid-cols-[minmax(0,16rem)_minmax(0,1fr)] sm:items-start sm:gap-8">
            <div className={method.qr ? 'mx-auto w-full max-w-[18rem] sm:mx-0' : 'sm:col-span-2'}>
                {method.qr ? (
                    <>
                        <button
                            type="button"
                            onClick={() => setEnlarged(true)}
                            aria-haspopup="dialog"
                            aria-label={text.qr.enlargeLabel(method.label)}
                            className="press group block w-full overflow-hidden rounded-card border border-line bg-white p-3"
                        >
                            <img src={method.qr} alt={text.qr.alt(method.label)} className="aspect-square w-full object-contain" />
                        </button>
                        <div className="mt-2 flex flex-wrap items-center justify-center gap-x-1 sm:justify-start">
                            <button type="button" onClick={() => setEnlarged(true)} aria-haspopup="dialog" className="press inline-flex min-h-11 items-center gap-1.5 rounded-control px-2 text-sm font-semibold text-link hover:underline hover:underline-offset-4">
                                <Expand className="size-4" /> {text.qr.enlarge}
                            </button>
                            <a href={method.qr} download={`826-${method.key}-qr`} className="press inline-flex min-h-11 items-center gap-1.5 rounded-control px-2 text-sm font-semibold text-link hover:underline hover:underline-offset-4">
                                <Download className="size-4" /> {text.qr.save}
                            </a>
                        </div>
                    </>
                ) : (
                    <p className="flex gap-2.5 rounded-card bg-surface-3/60 px-4 py-4 text-[0.95rem] leading-relaxed">
                        <Info className="mt-0.5 size-5 shrink-0 text-accent" />
                        {text.qr.missing(method.label)}
                    </p>
                )}
            </div>

            <div className="min-w-0">
                {method.qr && <p className="mb-4 hidden text-sm leading-relaxed text-muted pointer-coarse:block">{text.qr.samePhone}</p>}
                {hasAccount && (
                    <dl className="divide-y divide-line panel px-4 py-4 sm:px-5">
                        {method.accountName && <AccountLine label={text.account.name} value={method.accountName} copyValue={method.accountName} onCopied={(what) => setAnnouncement(text.account.copiedLive(what))} />}
                        {method.accountNumber && (
                            <AccountLine
                                label={text.account.number}
                                value={method.accountNumber}
                                copyValue={method.accountNumber.replace(/\s+/g, '')}
                                mono
                                onCopied={(what) => setAnnouncement(text.account.copiedLive(what))}
                            />
                        )}
                    </dl>
                )}
                <p className="sr-only" aria-live="polite">
                    {announcement}
                </p>
                {instructions && (
                    <div className={`rounded-card bg-surface-3/60 px-4 py-4 text-[0.95rem] leading-relaxed ${hasAccount ? 'mt-4' : ''}`}>
                        <p className="eyebrow text-muted">{text.instructions}</p>
                        <p className="mt-2 whitespace-pre-line">{instructions}</p>
                    </div>
                )}
            </div>

            {enlarged && method.qr && (
                <Modal title={text.qr.modalTitle(method.label)} description={text.qr.modalBody(method.label)} onClose={() => setEnlarged(false)} className="sm:max-w-lg">
                    <div className="mx-auto max-w-[26rem] rounded-card border border-line bg-white p-4">
                        <img src={method.qr} alt={text.qr.alt(method.label)} className="aspect-square w-full object-contain" />
                    </div>
                    {method.accountNumber && (
                        <p className="mt-5 text-center">
                            <span className="block text-sm text-muted">{method.accountName ?? text.account.number}</span>
                            <span className="price text-xl tracking-[0.03em]">{method.accountNumber}</span>
                        </p>
                    )}
                    <div className="mt-5 flex justify-center">
                        <a href={method.qr} download={`826-${method.key}-qr`} className={buttonClass('outline', 'min-h-11! px-5!')}>
                            <Download className="size-4" /> {text.qr.save}
                        </a>
                    </div>
                </Modal>
            )}
        </div>
    );
}

type UploaderProps = {
    booking: CustomerBooking;
    bookingKey: string | null;
    method: PaymentMethod;
    /** More than one method: say which one it goes up as */
    showMethod: boolean;
    onUploaded: (booking: CustomerBooking, demo: boolean) => void;
    /** 409: the booking moved on (the hold ran out, or it was cancelled) */
    onConflict: (message: string) => void;
    onBusy: (busy: boolean) => void;
};

/**
 * The screenshot: picked from the phone's photos (or dropped), checked
 * here first, previewed, then sent with its progress showing.
 */
function ProofUploader({ booking, bookingKey, method, showMethod, onUploaded, onConflict, onBusy }: UploaderProps) {
    const inputId = useId();
    const hintId = useId();
    const errorId = useId();
    const [file, setFile] = useState<File | null>(null);
    const [preview, setPreview] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [status, setStatus] = useState('');
    const [progress, setProgress] = useState<number | null>(null);
    const [dragging, setDragging] = useState(false);
    const input = useRef<HTMLInputElement>(null);
    const submitButton = useRef<HTMLButtonElement>(null);
    const abort = useRef<AbortController | null>(null);
    const uploading = progress !== null;
    const errors = text.upload.errors;

    useEffect(() => () => abort.current?.abort(), []);

    useEffect(() => {
        if (!file) {
            setPreview(null);
            return;
        }

        const url = URL.createObjectURL(file);
        setPreview(url);

        return () => URL.revokeObjectURL(url);
    }, [file]);

    const pick = (picked: File | undefined | null) => {
        if (!picked) {
            return;
        }

        const problem = proofProblem(picked);

        if (problem) {
            setError(problem === 'type' ? errors.type : errors.size(fileSize(picked.size)));
            setStatus('');
            return;
        }

        setError(null);
        setFile(picked);
        setStatus(text.upload.added(picked.name));
        // Next: send it
        requestAnimationFrame(() => submitButton.current?.focus({ preventScroll: true }));
    };

    const remove = () => {
        setFile(null);
        setError(null);
        setStatus(text.upload.removed);

        if (input.current) {
            input.current.value = '';
        }

        requestAnimationFrame(() => input.current?.focus());
    };

    const onDrop = (event: DragEvent<HTMLElement>) => {
        event.preventDefault();
        setDragging(false);
        pick(event.dataTransfer.files?.[0]);
    };

    const submit = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!file) {
            setError(errors.missing);
            input.current?.focus();
            return;
        }

        setError(null);
        setProgress(0);
        setStatus(text.upload.uploading(0));
        onBusy(true);
        abort.current = new AbortController();

        const result = await uploadProof({ id: booking.id, key: bookingKey, method: method.key, file, onProgress: setProgress, signal: abort.current.signal });

        onBusy(false);

        if (result.kind === 'aborted') {
            return;
        }

        setProgress(null);

        if (result.kind === 'ok') {
            onUploaded(result.data.booking, result.data.demo === true);
            return;
        }

        if (result.kind === 'conflict') {
            onConflict(result.message ?? errors.conflict);
            return;
        }

        const message =
            result.kind === 'invalid'
                ? (Object.values(result.errors)[0]?.[0] ?? result.message ?? errors.failed)
                : result.kind === 'tooMany'
                  ? errors.tooMany
                  : result.kind === 'stale'
                    ? copy.book.errors.stale
                    : errors.failed;
        setError(message);
        setStatus('');
        requestAnimationFrame(() => submitButton.current?.focus({ preventScroll: true }));
    };

    const percent = Math.round((progress ?? 0) * 100);
    const describedBy = [hintId, error ? errorId : null].filter(Boolean).join(' ');

    return (
        <form onSubmit={submit} noValidate>
            <input
                ref={input}
                id={inputId}
                type="file"
                name="proof"
                accept={PROOF_ACCEPT}
                tabIndex={file ? -1 : 0}
                disabled={uploading}
                onChange={(event) => pick(event.target.files?.[0])}
                className="peer sr-only"
                aria-describedby={describedBy}
                aria-invalid={Boolean(error)}
            />

            {file && preview ? (
                <div className="flex gap-4 panel p-3 sm:p-4" onDragOver={(event) => event.preventDefault()} onDrop={onDrop}>
                    <img src={preview} alt={text.upload.preview(file.name)} className="h-36 w-24 shrink-0 rounded-control border border-line bg-surface-3 object-cover object-top sm:h-40 sm:w-28" />
                    <div className="flex min-w-0 flex-1 flex-col">
                        <p className="truncate font-semibold">{file.name}</p>
                        <p className="text-sm text-muted">{fileSize(file.size)}</p>
                        <div className="mt-auto flex flex-wrap gap-2 pt-3">
                            <button type="button" onClick={() => input.current?.click()} disabled={uploading} className={smallButton}>
                                <ImagePlus className="size-4" /> {text.upload.replace}
                            </button>
                            <button type="button" onClick={remove} disabled={uploading} className={smallButton}>
                                <Trash className="size-4" /> {text.upload.remove}
                            </button>
                        </div>
                    </div>
                </div>
            ) : (
                <label
                    htmlFor={inputId}
                    onDragOver={(event) => {
                        event.preventDefault();
                        setDragging(true);
                    }}
                    onDragLeave={() => setDragging(false)}
                    onDrop={onDrop}
                    className={`press flex min-h-44 cursor-pointer flex-col items-center justify-center gap-2 rounded-card border-2 border-dashed px-5 py-8 text-center peer-focus-visible:outline-2 peer-focus-visible:outline-offset-3 peer-focus-visible:outline-accent ${
                        dragging ? 'border-accent bg-accent/8' : error ? 'border-danger bg-surface-2' : 'border-field bg-surface-2 hover:border-text/60'
                    }`}
                >
                    <span className="grid size-12 place-items-center rounded-full bg-accent/10 text-accent">
                        <ImagePlus className="size-6" />
                    </span>
                    <span className="mt-1 font-semibold">{text.upload.label}</span>
                    <span className="text-sm text-muted">
                        <span className="underline underline-offset-4">{text.upload.choose}</span>
                        <span className="hidden pointer-fine:inline"> {text.upload.drop}</span>
                    </span>
                </label>
            )}

            <p id={hintId} className="mt-2 text-sm text-muted">
                {text.upload.hint}
            </p>

            {error && (
                <p id={errorId} role="alert" className="mt-3 flex items-start gap-1.5 text-sm font-medium text-danger">
                    <Warning className="mt-0.5 size-4 shrink-0" /> {error}
                </p>
            )}

            {uploading && (
                <div className="mt-5">
                    <div
                        role="progressbar"
                        aria-label={text.upload.label}
                        aria-valuemin={0}
                        aria-valuemax={100}
                        aria-valuenow={percent}
                        className="h-2 overflow-hidden rounded-full bg-surface-3"
                    >
                        <div className="h-full rounded-full bg-accent transition-[width] duration-200 motion-reduce:transition-none" style={{ width: `${percent}%` }} />
                    </div>
                    <p className="mt-2 text-sm text-muted tabular" aria-hidden="true">
                        {percent < 100 ? text.upload.uploading(percent) : text.upload.finishing}
                    </p>
                </div>
            )}

            <p className="sr-only" aria-live="polite">
                {status}
            </p>

            {showMethod && <p className="mt-5 text-sm text-muted">{text.upload.paidWith(method.label)}</p>}
            <button ref={submitButton} type="submit" disabled={uploading} className={buttonClass('accent', `w-full text-base disabled:opacity-60 sm:w-auto sm:px-8 ${showMethod ? 'mt-2' : 'mt-5'}`)}>
                {uploading ? text.upload.uploading(percent) : text.upload.submit}
            </button>
        </form>
    );
}

function Step({ number, title, children }: { number: number | null; title: string; children: ReactNode }) {
    return (
        <li className="border-t border-line pt-6">
            <h3 className="flex items-center gap-3 text-[1.05rem] font-bold">
                {number !== null && (
                    <span aria-hidden="true" className="grid size-7 shrink-0 place-items-center rounded-full bg-accent/12 text-sm font-bold tabular">
                        {number}
                    </span>
                )}
                {title}
            </h3>
            <div className="mt-4">{children}</div>
        </li>
    );
}

type Props = {
    booking: CustomerBooking;
    bookingKey: string | null;
    onUploaded: (booking: CustomerBooking, demo: boolean) => void;
    onConflict: (message: string) => void;
    onBusy: (busy: boolean) => void;
    /** Above it all: the countdown to the end of the hold */
    countdown?: ReactNode;
};

/**
 * "Pay the reservation fee": the amount, then pick GCash or Maya, pay by
 * its QR or number, and send the screenshot. After a screenshot was turned
 * down, why, above it all. No methods set up yet: call or message the
 * branch.
 */
export default function PaymentPanel({ booking, bookingKey, onUploaded, onConflict, onBusy, countdown }: Props) {
    const methods = booking.methods;
    const [chosen, setChosen] = useState<PaymentMethodKey | null>(() => methods.find((method) => method.key === booking.payment.method)?.key ?? methods[0]?.key ?? null);
    const method = methods.find((option) => option.key === chosen) ?? methods[0] ?? null;
    const rejected = booking.payment.rejectedReason;
    let step = 0;

    return (
        <section aria-labelledby="pay-title" className="rounded-card border border-accent/50 bg-surface-2/60 p-5 sm:p-7">
            <h2 id="pay-title" className="font-display text-[clamp(1.3rem,4vw,1.7rem)]">
                {text.title}
            </h2>
            {countdown}

            {rejected && (
                <div role="note" className="mt-5 flex gap-3 rounded-card border border-danger/50 bg-surface-2 px-4 py-4 sm:px-5">
                    <Warning className="mt-0.5 size-5 shrink-0 text-danger" />
                    <div className="min-w-0">
                        <p className="font-semibold">{text.rejected.title}</p>
                        <p className="mt-1 leading-relaxed whitespace-pre-line">{rejected}</p>
                        <p className="mt-1 text-sm text-muted">{text.rejected.body}</p>
                    </div>
                </div>
            )}

            <AmountDue booking={booking} />

            {!method ? (
                <div className="mt-6 space-y-4">
                    <div className="panel px-5 py-5">
                        <p className="font-semibold">{text.noMethods.title}</p>
                        <p className="mt-1 leading-relaxed text-muted">{text.noMethods.body}</p>
                    </div>
                    {booking.branch && <ContactButtons target={{ ...booking.branch, facebook: branchById(booking.branch.id)?.facebook ?? null }} fallbackNote={false} />}
                </div>
            ) : (
                <ol className="mt-8 space-y-8">
                    {methods.length > 1 && (
                        <Step number={++step} title={text.steps.method}>
                            <MethodChoice methods={methods} value={method.key} onChange={setChosen} />
                        </Step>
                    )}
                    <Step number={++step} title={text.steps.scan(method.label)}>
                        <PayWith key={method.key} method={method} instructions={booking.instructions} />
                    </Step>
                    <Step number={++step} title={text.steps.upload}>
                        <ProofUploader booking={booking} bookingKey={bookingKey} method={method} showMethod={methods.length > 1} onUploaded={onUploaded} onConflict={onConflict} onBusy={onBusy} />
                    </Step>
                </ol>
            )}
        </section>
    );
}
