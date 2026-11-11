import { useEffect, useId, useRef, useState } from 'react';

import { fetchDay, fetchMonth, type JobQuery, jobKey } from '../lib/availability';
import { copy } from '../lib/copy';
import { addMonths, manilaToday, shortDate } from '../lib/dates';
import type { DayAvailability, MonthAvailability, TimeSlot } from '../types';
import Calendar from './Calendar';
import { Check, Warning } from './Icons';

function RetryLine({ message, onRetry }: { message: string; onRetry: () => void }) {
    return (
        <p role="alert" className="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm font-medium text-danger">
            <Warning className="size-4 shrink-0" /> {message}
            <button type="button" onClick={onRetry} className="press -mx-2 inline-flex min-h-11 items-center rounded-control px-2 font-semibold text-text underline underline-offset-4 hover:bg-text/6">
                {copy.calendar.retry}
            </button>
        </p>
    );
}

type CalendarProps = {
    job: JobQuery;
    selected: string | null;
    onPick: (date: string) => void;
    /** Each month that comes in: the job's length and total, for the page around it */
    onMonth?: (month: MonthAvailability) => void;
};

/**
 * The calendar for a job: which days have a drop-off time with room for
 * it (open), have times but every one is taken (full), or have none
 * (closed), a month at a time, between the first and last bookable days.
 * Another job (other services, another booking) starts it over.
 */
export function AvailabilityCalendar(props: CalendarProps) {
    return <JobCalendar key={jobKey(props.job)} {...props} />;
}

function JobCalendar({ job, selected, onPick, onMonth }: CalendarProps) {
    const id = jobKey(job);
    const [month, setMonth] = useState<string | null>(selected ? selected.slice(0, 7) : null);
    const [months, setMonths] = useState<Record<string, MonthAvailability>>({});
    const [failed, setFailed] = useState(false);
    const [attempt, setAttempt] = useState(0);
    const advanced = useRef(false);
    const reported = useRef(onMonth);
    const loaded = useRef(months);
    loaded.current = months;

    useEffect(() => {
        reported.current = onMonth;
    }, [onMonth]);

    useEffect(() => {
        if (month && loaded.current[month]) {
            return;
        }

        let cancelled = false;
        setFailed(false);

        fetchMonth(job, month ?? undefined).then((result) => {
            if (cancelled) {
                return;
            }

            if (result.kind !== 'ok') {
                setFailed(true);
                return;
            }

            const data = result.data;
            setMonths((current) => ({ ...current, [data.month]: data }));
            reported.current?.(data);

            // The first look: when this month has no open day left, the next one
            if (!month && !advanced.current && !Object.values(data.days).includes('open') && data.month < data.last.slice(0, 7)) {
                advanced.current = true;
                setMonth(addMonths(data.month, 1));
                return;
            }

            if (month !== data.month) {
                setMonth(data.month);
            }
        });

        return () => {
            cancelled = true;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [id, month, attempt]);

    const shown = month ?? manilaToday().slice(0, 7);
    const data = months[shown];
    const any = Object.values(months)[0];
    const minMonth = any ? any.first.slice(0, 7) : shown;
    const maxMonth = any ? any.last.slice(0, 7) : shown;
    const noneOpen = data && !Object.values(data.days).includes('open');

    return (
        <Calendar
            month={shown}
            onMonthChange={setMonth}
            minMonth={minMonth < shown ? minMonth : shown}
            maxMonth={maxMonth > shown ? maxMonth : shown}
            stateOf={(iso) => (data ? (data.days[iso] ?? 'closed') : failed ? 'closed' : 'loading')}
            selected={selected}
            onPick={onPick}
            status={
                <div aria-live="polite">
                    {failed ? (
                        <RetryLine message={copy.calendar.failed} onRetry={() => setAttempt((count) => count + 1)} />
                    ) : !data ? (
                        <p className="text-sm text-muted">{copy.calendar.checking}</p>
                    ) : noneOpen ? (
                        <p className="text-sm text-muted">{copy.calendar.noneThisMonth}</p>
                    ) : null}
                </div>
            }
        />
    );
}

type SlotsProps = {
    job: JobQuery;
    date: string;
    selected: string | null;
    onPick: (slot: TimeSlot) => void;
    /** Bumped to ask the server again (a time turned out to be taken) */
    refresh?: number;
    error?: string | null;
};

/**
 * The drop-off times with room on one day, each with when the car will be
 * ready, as a list of radio cards.
 */
export function TimeSlots({ job, date, selected, onPick, refresh = 0, error }: SlotsProps) {
    const id = jobKey(job);
    const name = useId();
    const [day, setDay] = useState<DayAvailability | null>(null);
    const [failed, setFailed] = useState(false);
    const [attempt, setAttempt] = useState(0);

    useEffect(() => {
        let cancelled = false;
        setDay(null);
        setFailed(false);

        fetchDay(job, date).then((result) => {
            if (cancelled) {
                return;
            }

            if (result.kind === 'ok') {
                setDay(result.data);
            } else {
                setFailed(true);
            }
        });

        return () => {
            cancelled = true;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [id, date, attempt, refresh]);

    const slots = day?.slots ?? [];

    return (
        <div>
            <p className="sr-only" aria-live="polite">
                {day ? (slots.length ? copy.times.count(slots.length, shortDate(date)) : copy.times.none) : ''}
            </p>
            {error && (
                <p role="alert" className="mb-4 flex items-start gap-2 rounded-control border border-danger/60 bg-danger/8 px-4 py-3 text-[0.95rem] font-medium">
                    <Warning className="mt-0.5 size-4 shrink-0 text-danger" /> {error}
                </p>
            )}
            {failed ? (
                <RetryLine message={copy.times.failed} onRetry={() => setAttempt((count) => count + 1)} />
            ) : !day ? (
                <p className="text-muted">{copy.times.checking}</p>
            ) : slots.length === 0 ? (
                <p className="panel px-4 py-4">{copy.times.none}</p>
            ) : (
                <fieldset className="@container">
                    <legend className="sr-only">{copy.book.time.title}</legend>
                    <div className="grid grid-cols-2 gap-2.5 @lg:grid-cols-3">
                        {slots.map((slot) => {
                            const checked = slot.time === selected;

                            return (
                                <label
                                    key={slot.time}
                                    className={`press relative flex min-h-16 cursor-pointer flex-col justify-center rounded-control border px-3.5 py-2.5 has-focus-visible:outline-2 has-focus-visible:outline-offset-3 has-focus-visible:outline-accent ${
                                        checked ? 'border-accent bg-accent text-on-accent' : 'border-field/70 bg-surface-2 hover:border-accent'
                                    }`}
                                >
                                    <input type="radio" name={name} value={slot.time} checked={checked} onChange={() => onPick(slot)} className="sr-only" />
                                    <span className="flex items-center gap-1.5 text-[1.05rem] font-bold tabular">
                                        {checked && <Check className="size-4" />}
                                        {slot.label}
                                    </span>
                                    <span className={`text-sm ${checked ? 'text-on-accent/85' : 'text-muted'}`}>{slot.readyLabel}</span>
                                </label>
                            );
                        })}
                    </div>
                </fieldset>
            )}
        </div>
    );
}
