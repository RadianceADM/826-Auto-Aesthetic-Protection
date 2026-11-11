import { useEffect, useRef, useState } from 'react';

import { ENDED_STAGES } from '../lib/bookings';
import { copy } from '../lib/copy';
import type { CustomerBooking, Stage } from '../types';
import { Check } from './Icons';

/** Stages where the customer has something to do */
const ACTION: Stage[] = ['awaiting_payment'];

/** Stages still under way: on the line they're ringed, not ticked */
const IN_PROGRESS: Stage[] = ['awaiting_payment', 'payment_review'];

export function StageBadge({ stage, className = '' }: { stage: Stage; className?: string }) {
    const ended = ENDED_STAGES.includes(stage);
    const action = ACTION.includes(stage);

    return (
        <span
            className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[0.8125rem] font-semibold ${
                action ? 'bg-accent text-on-accent' : ended ? 'bg-surface-3 text-muted' : 'bg-accent/12 text-text'
            } ${className}`}
        >
            {!ended && !action && <span aria-hidden="true" className="size-1.5 rounded-full bg-accent" />}
            {copy.stages[stage].label}
        </span>
    );
}

/**
 * The steps this booking goes through, in order: a home request starts as
 * a request; the fee steps only when there's a fee; the shop steps only
 * for a car at the shop.
 */
export function stageSteps(booking: CustomerBooking): Stage[] {
    const paying = booking.amountDue !== null || booking.stage === 'awaiting_payment' || booking.stage === 'payment_review' || booking.payment.paidAt !== null;
    const onsite = booking.kind === 'onsite';
    const shop = onsite || booking.stage === 'checked_in' || booking.stage === 'ready';

    return [
        ...(booking.kind === 'home' ? (['requested'] as Stage[]) : []),
        ...(paying ? (['awaiting_payment', 'payment_review'] as Stage[]) : []),
        'confirmed',
        ...(shop ? (['checked_in', 'ready'] as Stage[]) : []),
        'completed',
    ];
}

/**
 * Where the booking is: done steps ticked, the current one marked, the
 * rest ahead. A booking that left the line (cancelled, expired, declined,
 * missed) shows no line.
 */
export function StageTimeline({ booking }: { booking: CustomerBooking }) {
    if (ENDED_STAGES.includes(booking.stage) && booking.stage !== 'completed') {
        return null;
    }

    const steps = stageSteps(booking);
    const current = steps.indexOf(booking.stage);

    return (
        <ol className="grid gap-0 sm:flex sm:gap-2" aria-label={copy.booking.progress}>
            {steps.map((step, index) => {
                // Reached steps are ticked. The one it's at now is ringed, and ticked too when it's a state
                // reached (Reserved, In the shop) rather than one still going (paying, checking the payment)
                const now = index === current;
                const done = index < current || booking.stage === 'completed' || (now && !IN_PROGRESS.includes(step));

                return (
                    <li key={step} aria-current={now ? 'step' : undefined} className="relative flex min-w-0 gap-3 pb-5 last:pb-0 sm:flex-1 sm:flex-col sm:gap-2.5 sm:pb-0">
                        {/* The line between steps: down on phones, across from sm */}
                        {index < steps.length - 1 && (
                            <span aria-hidden="true" className={`absolute top-7 bottom-0 left-[0.8125rem] w-px sm:top-[0.8125rem] sm:right-[-0.5rem] sm:bottom-auto sm:left-9 sm:h-px sm:w-auto ${index < current || booking.stage === 'completed' ? 'bg-accent' : 'bg-line'}`} />
                        )}
                        <span
                            aria-hidden="true"
                            className={`relative grid size-[1.625rem] shrink-0 place-items-center rounded-full border text-xs font-bold ${
                                done ? `border-accent bg-accent text-on-accent ${now ? 'ring-4 ring-accent/20' : ''}` : now ? 'border-accent bg-surface text-accent ring-4 ring-accent/15' : 'border-field/60 bg-surface text-muted'
                            }`}
                        >
                            {done ? <Check className="size-3.5" /> : index + 1}
                        </span>
                        <span className={`pt-0.5 text-[0.95rem] leading-snug sm:pt-0 sm:text-sm ${now ? 'font-bold' : done ? 'font-medium' : 'text-muted'}`}>
                            {copy.stages[step].label}
                            <span className="sr-only">{now ? ', now' : done ? ', done' : ''}</span>
                        </span>
                    </li>
                );
            })}
        </ol>
    );
}

/**
 * The time left on the hold, ticking each second. Screen readers hear it
 * once a minute, not every second. At zero, `onEnd` (the page looks again).
 */
export function HoldCountdown({ until, onEnd }: { until: string; onEnd: () => void }) {
    const end = new Date(until).getTime();
    const [now, setNow] = useState(() => Date.now());
    const ended = useRef(false);
    const left = Math.max(0, end - now);
    const hours = Math.floor(left / 3600000);
    const minutes = Math.floor(left / 60000);
    const seconds = Math.floor((left % 60000) / 1000);
    const clockText = hours > 0 ? `${hours}:${String(minutes % 60).padStart(2, '0')}:${String(seconds).padStart(2, '0')}` : `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    const [spoken, setSpoken] = useState(() => copy.booking.countdown.live(Math.ceil(left / 60000)));

    useEffect(() => {
        const timer = window.setInterval(() => setNow(Date.now()), 1000);

        return () => window.clearInterval(timer);
    }, []);

    useEffect(() => {
        if (left === 0 && !ended.current) {
            ended.current = true;
            setSpoken(copy.booking.countdown.ended);
            onEnd();
        } else if (left > 0 && seconds === 0) {
            setSpoken(copy.booking.countdown.live(minutes));
        }
    }, [left, minutes, seconds, onEnd]);

    return (
        <div className="mt-5 flex flex-wrap items-center justify-between gap-3 rounded-control bg-surface-3/70 px-4 py-3">
            <p className="font-semibold">{copy.booking.countdown.label}</p>
            <p className="price text-2xl tabular" aria-hidden="true">
                {clockText}
            </p>
            <p className="sr-only" aria-live="polite">
                {spoken}
            </p>
        </div>
    );
}
