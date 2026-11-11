/*
 * Calendar dates as "YYYY-MM-DD" strings, worked out on plain local dates
 * (no time zone moves a day). Times from the server carry +08:00 and are
 * shown in Manila time.
 */

export const pad = (value: number) => String(value).padStart(2, '0');

export const isoDate = (date: Date) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;

export function parseDate(iso: string): Date {
    const [year, month, day] = iso.split('-').map(Number);

    return new Date(year, (month || 1) - 1, day || 1);
}

/** "2026-11" */
export const monthKey = (date: Date) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}`;

export const monthStart = (key: string) => parseDate(`${key}-01`);

export function addMonths(key: string, count: number): string {
    const date = monthStart(key);

    return monthKey(new Date(date.getFullYear(), date.getMonth() + count, 1));
}

export const addDays = (date: Date, days: number) => new Date(date.getFullYear(), date.getMonth(), date.getDate() + days);

export const daysInMonth = (date: Date) => new Date(date.getFullYear(), date.getMonth() + 1, 0).getDate();

/** Today in Manila, as "YYYY-MM-DD" */
export function manilaToday(): string {
    return new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Manila', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
}

/** "2026-11-14" → "Saturday, November 14" */
export const longDate = (iso: string) => parseDate(iso).toLocaleDateString('en-PH', { weekday: 'long', month: 'long', day: 'numeric' });

/** "2026-11-14" → "Sat, Nov 14" */
export const shortDate = (iso: string) => parseDate(iso).toLocaleDateString('en-PH', { weekday: 'short', month: 'short', day: 'numeric' });

/** "2026-11" → "November 2026" */
export const monthTitle = (key: string) => monthStart(key).toLocaleDateString('en-PH', { month: 'long', year: 'numeric' });

/**
 * A server timestamp as "Nov 7, 3:20 PM" (Manila time); anything else as
 * it came.
 */
export function stamp(value: string | null): string | null {
    if (!value) {
        return null;
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleString('en-PH', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit', timeZone: 'Asia/Manila' });
}

/** A server timestamp's day and time: "Sat, Nov 14" and "1:00 PM" (Manila) */
export function dayAndTime(value: string | null): { day: string; time: string } | null {
    if (!value) {
        return null;
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return null;
    }

    return {
        day: date.toLocaleDateString('en-PH', { weekday: 'short', month: 'short', day: 'numeric', timeZone: 'Asia/Manila' }),
        time: date.toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit', timeZone: 'Asia/Manila' }),
    };
}
