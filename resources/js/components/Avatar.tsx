import { useState } from 'react';

import { initials } from '../lib/auth';
import type { Guest } from '../types';

type Props = {
    guest: Guest;
    className?: string;
};

/**
 * The customer's Google picture, or their initials on the accent when there's
 * none (or it won't load). Decorative: whatever holds it says who it is.
 */
export default function Avatar({ guest, className = 'size-10 text-sm' }: Props) {
    const [broken, setBroken] = useState(false);

    if (guest.avatar && !broken) {
        return (
            <img
                src={guest.avatar}
                alt=""
                width={96}
                height={96}
                referrerPolicy="no-referrer"
                onError={() => setBroken(true)}
                className={`shrink-0 rounded-full bg-surface-3 object-cover ${className}`}
            />
        );
    }

    return (
        <span aria-hidden="true" className={`grid shrink-0 place-items-center rounded-full bg-accent font-semibold tracking-[0.02em] text-on-accent ${className}`}>
            {initials(guest.name)}
        </span>
    );
}
