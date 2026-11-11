import type { TimeSlot } from '../types';

/**
 * What a customer has filled in, kept in this tab (sessionStorage) so a
 * reload or the trip to Google's sign-in and back doesn't lose it.
 */
export function loadDraft<T extends object>(key: string, empty: T): T {
    try {
        const saved = window.sessionStorage.getItem(key);

        if (!saved) {
            return empty;
        }

        const parsed = JSON.parse(saved) as Partial<T>;

        // Only the fields this version knows, each of the type it expects
        return Object.fromEntries(
            Object.entries(empty).map(([field, fallback]) => {
                const value = (parsed as Record<string, unknown>)[field];
                const sameType = value !== undefined && (fallback === null || value === null || typeof value === typeof fallback) && Array.isArray(value) === Array.isArray(fallback);

                return [field, sameType ? value : fallback];
            }),
        ) as T;
    } catch {
        return empty;
    }
}

export function saveDraft<T extends object>(key: string, draft: T): void {
    try {
        window.sessionStorage.setItem(key, JSON.stringify(draft));
    } catch {
        // Private browsing without storage: it just isn't kept
    }
}

export function clearDraft(key: string): void {
    try {
        window.sessionStorage.removeItem(key);
    } catch {
        // Nothing to clear
    }
}

/** A Philippine mobile or landline as the server takes it: 10 to 13 digits, with spaces, dashes, dots or brackets */
export function phoneProblem(value: string): 'missing' | 'format' | null {
    const trimmed = value.trim();

    if (!trimmed) {
        return 'missing';
    }

    const digits = trimmed.replace(/\D/g, '').length;

    return /^\+?[\d\s().-]+$/.test(trimmed) && digits >= 10 && digits <= 13 ? null : 'format';
}

export const emailLooksWrong = (value: string) => value.trim() !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim());

export const plateLooksWrong = (value: string) => value.trim() !== '' && (!/^[A-Za-z0-9 -]+$/.test(value.trim()) || value.trim().length > 15);

/** The reservation wizard's draft (Book), also filled in by "Book again" */
export const BOOK_DRAFT = 'app826-book-draft';

export type BookDraft = {
    branchId: number | null;
    sizeId: number | null;
    model: string;
    plate: string;
    serviceIds: number[];
    date: string | null;
    slot: TimeSlot | null;
    name: string;
    phone: string;
    email: string;
    message: string;
    consent: boolean;
};

export const emptyBookDraft = (): BookDraft => ({
    branchId: null,
    sizeId: null,
    model: '',
    plate: '',
    serviceIds: [],
    date: null,
    slot: null,
    name: '',
    phone: '',
    email: '',
    message: '',
    consent: false,
});
