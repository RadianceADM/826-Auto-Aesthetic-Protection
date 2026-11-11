import { type KeyboardEvent, type ReactNode, useEffect, useMemo, useRef, useState } from 'react';

import { copy } from '../lib/copy';
import { addDays, addMonths, daysInMonth, isoDate, longDate, monthKey, monthStart, monthTitle, parseDate } from '../lib/dates';
import type { DayState } from '../types';
import { ChevronLeft, ChevronRight } from './Icons';

const WEEKDAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

export type CellState = DayState | 'loading';

type Props = {
    /** The month on screen ("2026-11") */
    month: string;
    onMonthChange: (month: string) => void;
    /** The months it can move between */
    minMonth: string;
    maxMonth: string;
    stateOf: (iso: string) => CellState;
    selected: string | null;
    onPick: (iso: string) => void;
    /** Under the grid: the loading line, an error with its retry */
    status?: ReactNode;
    legend?: boolean;
    /** The full state ("fully booked") only means something with availability */
    showFull?: boolean;
};

/**
 * A month of days: open ones can be picked, full and closed ones can't.
 * One day is the Tab stop; the arrow keys, Home/End and Page Up/Down move
 * between days, across months.
 */
export default function Calendar({ month, onMonthChange, minMonth, maxMonth, stateOf, selected, onPick, status, legend = true, showFull = true }: Props) {
    const [active, setActive] = useState<string | null>(null);
    const focusNext = useRef<string | null>(null);
    const root = useRef<HTMLDivElement>(null);

    const weeks = useMemo(() => {
        const first = monthStart(month);
        const gridStart = addDays(first, -first.getDay());
        const count = Math.ceil((first.getDay() + daysInMonth(first)) / 7);

        return Array.from({ length: count }, (_, week) =>
            Array.from({ length: 7 }, (_, day) => {
                const date = addDays(gridStart, week * 7 + day);

                return { iso: isoDate(date), day: date.getDate(), inMonth: date.getMonth() === first.getMonth() };
            }),
        );
    }, [month]);

    const inMonth = (iso: string) => iso.startsWith(month);

    // After an arrow key: focus the day it moved to, once its month is drawn
    useEffect(() => {
        if (focusNext.current && inMonth(focusNext.current)) {
            root.current?.querySelector<HTMLElement>(`[data-iso="${focusNext.current}"]`)?.focus();
            focusNext.current = null;
        }
    });

    // The Tab stop: where the keys last moved, the picked day, the first open day, else the 1st
    const tabStop = (() => {
        if (active && inMonth(active)) return active;
        if (selected && inMonth(selected)) return selected;

        for (const week of weeks) {
            for (const cell of week) {
                if (cell.inMonth && stateOf(cell.iso) === 'open') {
                    return cell.iso;
                }
            }
        }

        return `${month}-01`;
    })();

    const onKey = (from: string, event: KeyboardEvent<HTMLButtonElement>) => {
        const date = parseDate(from);
        const shiftMonths = (count: number) => {
            const target = new Date(date.getFullYear(), date.getMonth() + count, 1);

            return new Date(target.getFullYear(), target.getMonth(), Math.min(date.getDate(), daysInMonth(target)));
        };
        const moves: Record<string, () => Date> = {
            ArrowLeft: () => addDays(date, -1),
            ArrowRight: () => addDays(date, 1),
            ArrowUp: () => addDays(date, -7),
            ArrowDown: () => addDays(date, 7),
            Home: () => addDays(date, -date.getDay()),
            End: () => addDays(date, 6 - date.getDay()),
            PageUp: () => shiftMonths(-1),
            PageDown: () => shiftMonths(1),
        };

        if (!moves[event.key]) {
            return;
        }

        event.preventDefault();

        const lowest = monthStart(minMonth);
        const highest = addDays(monthStart(addMonths(maxMonth, 1)), -1);
        const moved = moves[event.key]();
        const next = moved < lowest ? lowest : moved > highest ? highest : moved;
        const iso = isoDate(next);

        setActive(iso);
        focusNext.current = iso;

        if (!inMonth(iso)) {
            onMonthChange(monthKey(next));
        }
    };

    const canGoBack = month > minMonth;
    const canGoOn = month < maxMonth;
    const step = (by: number) => {
        setActive(null);
        onMonthChange(addMonths(month, by));
    };
    const navButton = 'press grid size-11 place-items-center rounded-full border border-field/60 text-text hover:border-accent hover:text-accent disabled:cursor-not-allowed disabled:opacity-35 disabled:hover:border-field/60 disabled:hover:text-text';

    return (
        <div ref={root}>
            <div className="flex items-center justify-between gap-4">
                <h3 className="text-lg font-bold" aria-live="polite">
                    {monthTitle(month)}
                </h3>
                <div className="flex gap-2">
                    <button type="button" onClick={() => step(-1)} disabled={!canGoBack} className={navButton} aria-label={copy.calendar.previous}>
                        <ChevronLeft />
                    </button>
                    <button type="button" onClick={() => step(1)} disabled={!canGoOn} className={navButton} aria-label={copy.calendar.next}>
                        <ChevronRight />
                    </button>
                </div>
            </div>

            <div role="grid" aria-label={monthTitle(month)} className="mt-4 grid gap-1.5 text-center">
                <div role="row" className="grid grid-cols-7 gap-1.5">
                    {WEEKDAYS.map((weekday) => (
                        <div key={weekday} role="columnheader" className="pb-1 text-xs font-semibold tracking-[0.06em] text-muted uppercase">
                            <span aria-hidden="true">{weekday.slice(0, 3)}</span>
                            <span className="sr-only">{weekday}</span>
                        </div>
                    ))}
                </div>
                {weeks.map((week) => (
                    <div key={week[0].iso} role="row" className="grid grid-cols-7 gap-1.5">
                        {week.map((cell) => {
                            if (!cell.inMonth) {
                                return <div key={cell.iso} role="gridcell" aria-hidden="true" />;
                            }

                            const state = stateOf(cell.iso);
                            const isSelected = cell.iso === selected;
                            const pickable = state === 'open';
                            const label = [longDate(cell.iso), state === 'loading' ? copy.calendar.checking : copy.calendar.state[state], isSelected ? copy.calendar.state.picked : null].filter(Boolean).join(', ');

                            return (
                                <button
                                    key={cell.iso}
                                    type="button"
                                    role="gridcell"
                                    data-iso={cell.iso}
                                    tabIndex={cell.iso === tabStop ? 0 : -1}
                                    aria-selected={isSelected}
                                    aria-disabled={!pickable}
                                    aria-label={label}
                                    onClick={() => {
                                        setActive(cell.iso);

                                        if (pickable) {
                                            onPick(cell.iso);
                                        }
                                    }}
                                    onKeyDown={(event) => onKey(cell.iso, event)}
                                    className={`press relative flex aspect-square min-h-11 flex-col items-center justify-center rounded-control text-[0.95rem] font-semibold tabular sm:aspect-[5/4] ${
                                        isSelected
                                            ? 'bg-accent text-on-accent'
                                            : state === 'open'
                                              ? 'border border-field/70 bg-surface-2 hover:border-accent'
                                              : state === 'loading'
                                                ? 'cursor-progress border border-line bg-surface-2 text-muted'
                                                : state === 'full' && showFull
                                                  ? 'cursor-not-allowed bg-surface-3/70 text-muted line-through decoration-1'
                                                  : 'cursor-not-allowed text-muted/55'
                                    }`}
                                >
                                    {cell.day}
                                    {state === 'open' && !isSelected && <span aria-hidden="true" className="absolute bottom-1.5 size-1 rounded-full bg-accent" />}
                                </button>
                            );
                        })}
                    </div>
                ))}
            </div>

            {status && <div className="mt-3">{status}</div>}

            {legend && (
                <ul className="mt-4 flex flex-wrap gap-x-5 gap-y-2 text-sm text-muted">
                    <li className="flex items-center gap-2">
                        <span aria-hidden="true" className="relative grid size-5 place-items-center rounded-[0.3rem] border border-field/70">
                            <span className="absolute bottom-0.5 size-1 rounded-full bg-accent" />
                        </span>
                        {copy.calendar.legend.open}
                    </li>
                    {showFull && (
                        <li className="flex items-center gap-2">
                            <span aria-hidden="true" className="grid size-5 place-items-center rounded-[0.3rem] bg-surface-3 text-[0.65rem] line-through">
                                9
                            </span>
                            {copy.calendar.legend.full}
                        </li>
                    )}
                    <li className="flex items-center gap-2">
                        <span aria-hidden="true" className="grid size-5 place-items-center text-[0.7rem] text-muted/55">
                            9
                        </span>
                        {copy.calendar.legend.closed}
                    </li>
                </ul>
            )}
        </div>
    );
}
