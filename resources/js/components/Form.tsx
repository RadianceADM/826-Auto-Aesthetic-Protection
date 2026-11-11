import { type ChangeEvent, type InputHTMLAttributes, type ReactNode, useId } from 'react';

import { copy } from '../lib/copy';
import { Check, Warning } from './Icons';

const control =
    'block w-full rounded-control border bg-surface-2 px-3.5 text-[1rem] text-text outline-none transition-[border-color,box-shadow] duration-150 focus-visible:border-accent focus-visible:ring-2 focus-visible:ring-accent/35 focus-visible:outline-none disabled:opacity-60';

export function ErrorText({ id, children }: { id?: string; children: ReactNode }) {
    return (
        <p id={id} className="mt-2 flex items-start gap-1.5 text-sm font-medium text-danger">
            <Warning className="mt-0.5 size-4 shrink-0" /> <span>{children}</span>
        </p>
    );
}

type FieldProps = {
    label: string;
    value: string;
    onChange: (value: string) => void;
    error?: string | null;
    hint?: string;
    optional?: boolean;
    multiline?: boolean;
    rows?: number;
    className?: string;
} & Omit<InputHTMLAttributes<HTMLInputElement>, 'value' | 'onChange' | 'className'>;

/**
 * A labelled text box (or a text area), its hint under the label and its
 * error under the box, both read with it.
 */
export function TextField({ label, value, onChange, error, hint, optional, multiline, rows = 3, className = '', id, ...rest }: FieldProps) {
    const autoId = useId();
    const fieldId = id ?? autoId;
    const hintId = `${fieldId}-hint`;
    const errorId = `${fieldId}-error`;
    const describedBy = [hint ? hintId : null, error ? errorId : null].filter(Boolean).join(' ') || undefined;
    const shared = {
        id: fieldId,
        value,
        'aria-invalid': error ? true : undefined,
        'aria-describedby': describedBy,
        className: `${control} ${error ? 'border-danger' : 'border-field'} ${multiline ? 'min-h-28 py-3 leading-relaxed' : 'min-h-12'}`,
    };

    return (
        <div className={className}>
            <label htmlFor={fieldId} className="block font-semibold">
                {label}
                {optional && <span className="ml-1.5 text-sm font-normal text-muted">({copy.book.details.optional})</span>}
            </label>
            {hint && (
                <p id={hintId} className="mt-1 text-sm text-muted">
                    {hint}
                </p>
            )}
            <div className="mt-2">
                {multiline ? (
                    <textarea rows={rows} {...shared} onChange={(event: ChangeEvent<HTMLTextAreaElement>) => onChange(event.target.value)} maxLength={rest.maxLength} name={rest.name} placeholder={rest.placeholder} />
                ) : (
                    <input {...rest} {...shared} onChange={(event) => onChange(event.target.value)} />
                )}
            </div>
            {error && <ErrorText id={errorId}>{error}</ErrorText>}
        </div>
    );
}

type ChoiceProps = {
    type: 'radio' | 'checkbox';
    name: string;
    value: string;
    checked: boolean;
    onChange: () => void;
    disabled?: boolean;
    children: ReactNode;
    className?: string;
    describedBy?: string;
};

/**
 * A radio or checkbox drawn as a card: the whole card is the target, the
 * real input inside keeps keyboard and screen readers working.
 */
export function ChoiceCard({ type, name, value, checked, onChange, disabled, children, className = '', describedBy }: ChoiceProps) {
    return (
        <label
            className={`choice press relative flex gap-3.5 rounded-card border p-4 ${
                disabled ? 'cursor-not-allowed border-line opacity-60' : checked ? 'is-checked cursor-pointer border-accent ring-1 ring-accent' : 'cursor-pointer border-field/55 hover:border-text/55'
            } has-focus-visible:outline-2 has-focus-visible:outline-offset-3 has-focus-visible:outline-accent ${className}`}
        >
            <input type={type} name={name} value={value} checked={checked} onChange={onChange} disabled={disabled} aria-describedby={describedBy} className="peer sr-only" />
            <span
                aria-hidden="true"
                className={`mt-0.5 grid size-5 shrink-0 place-items-center border ${type === 'radio' ? 'rounded-full' : 'rounded-[0.3rem]'} ${checked ? 'border-accent bg-accent text-on-accent' : 'border-field'}`}
            >
                {checked && (type === 'radio' ? <span className="size-2 rounded-full bg-on-accent" /> : <Check className="size-3.5" />)}
            </span>
            <span className="min-w-0 flex-1">{children}</span>
        </label>
    );
}

type ChipsProps<T extends string> = {
    legend: string;
    name: string;
    options: { value: T; label: string }[];
    value: T | null;
    onChange: (value: T) => void;
    error?: string | null;
    hint?: string;
    legendClass?: string;
};

/**
 * A short choice (Yes / No / Not sure, morning or afternoon, an area) as a
 * row of radio pills.
 */
export function Chips<T extends string>({ legend, name, options, value, onChange, error, hint, legendClass = 'font-semibold' }: ChipsProps<T>) {
    const id = useId();

    return (
        <fieldset aria-describedby={[hint ? `${id}-hint` : null, error ? `${id}-error` : null].filter(Boolean).join(' ') || undefined}>
            <legend className={legendClass}>{legend}</legend>
            {hint && (
                <p id={`${id}-hint`} className="mt-1 text-sm text-muted">
                    {hint}
                </p>
            )}
            <div className="mt-2.5 flex flex-wrap gap-2">
                {options.map((option) => {
                    const checked = value === option.value;

                    return (
                        <label
                            key={option.value}
                            className={`press inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-full border px-4 font-medium has-focus-visible:outline-2 has-focus-visible:outline-offset-3 has-focus-visible:outline-accent ${
                                checked ? 'border-accent bg-accent text-on-accent' : 'border-field/70 hover:border-text/60'
                            }`}
                        >
                            <input type="radio" name={name} value={option.value} checked={checked} onChange={() => onChange(option.value)} className="sr-only" />
                            {checked && <Check className="size-4" />}
                            {option.label}
                        </label>
                    );
                })}
            </div>
            {error && <ErrorText id={`${id}-error`}>{error}</ErrorText>}
        </fieldset>
    );
}

/**
 * One checkbox with its sentence (the privacy consent).
 */
export function Checkbox({ checked, onChange, children, error, id }: { checked: boolean; onChange: (checked: boolean) => void; children: ReactNode; error?: string | null; id?: string }) {
    const autoId = useId();
    const fieldId = id ?? autoId;

    return (
        <div>
            <label htmlFor={fieldId} className="flex cursor-pointer gap-3.5 leading-relaxed">
                <input
                    id={fieldId}
                    type="checkbox"
                    checked={checked}
                    onChange={(event) => onChange(event.target.checked)}
                    aria-invalid={error ? true : undefined}
                    aria-describedby={error ? `${fieldId}-error` : undefined}
                    className="peer sr-only"
                />
                <span
                    aria-hidden="true"
                    className={`mt-0.5 grid size-6 shrink-0 place-items-center rounded-[0.35rem] border peer-focus-visible:outline-2 peer-focus-visible:outline-offset-3 peer-focus-visible:outline-accent ${
                        checked ? 'border-accent bg-accent text-on-accent' : error ? 'border-danger' : 'border-field'
                    }`}
                >
                    {checked && <Check className="size-4" />}
                </span>
                <span className="text-[0.95rem]">{children}</span>
            </label>
            {error && <ErrorText id={`${fieldId}-error`}>{error}</ErrorText>}
        </div>
    );
}

/** A notice box: info by default, a problem in the danger colour */
export function Notice({ tone = 'info', children, className = '', role }: { tone?: 'info' | 'danger'; children: ReactNode; className?: string; role?: 'alert' | 'status' | 'note' }) {
    return (
        <div role={role} className={`flex gap-3 rounded-card border px-4 py-3.5 text-[0.95rem] leading-relaxed ${tone === 'danger' ? 'border-danger/60 bg-danger/8' : 'border-line bg-surface-3/60'} ${className}`}>
            {tone === 'danger' ? <Warning className="mt-0.5 size-5 shrink-0 text-danger" /> : <InfoDot />}
            <div className="min-w-0 flex-1">{children}</div>
        </div>
    );
}

function InfoDot() {
    return <span aria-hidden="true" className="mt-2 size-2 shrink-0 rounded-full bg-accent" />;
}
