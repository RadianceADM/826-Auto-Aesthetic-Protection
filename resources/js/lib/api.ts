import { csrfToken } from './auth';
import { apiUrl } from './site';

/** Laravel's validation errors: field → messages */
export type FieldErrors = Record<string, string[]>;

/**
 * What a request to the API came back with, sorted by what the page does
 * next.
 */
export type ApiResult<T> =
    | { kind: 'ok'; data: T }
    /** 401 with signIn: sign in, then try again */
    | { kind: 'signIn' }
    /** 422: show each message by its field */
    | { kind: 'invalid'; errors: FieldErrors; message: string | null }
    /** 409: busy, the hold ran out, can't cancel anymore */
    | { kind: 'conflict'; message: string | null }
    | { kind: 'notFound' }
    | { kind: 'tooMany' }
    /** 419: the page has been open too long; reload it */
    | { kind: 'stale' }
    | { kind: 'failed' };

type Body = Record<string, unknown> & { message?: string; errors?: FieldErrors; signIn?: boolean };

/** Sorts a response (or its absence) into an ApiResult */
export function sortResponse<T>(status: number, body: Body | null): ApiResult<T> {
    if (status >= 200 && status < 300 && body) {
        return { kind: 'ok', data: body as T };
    }

    if (status === 401) {
        return { kind: 'signIn' };
    }

    // The page's security token ran out (open for hours): a reload brings a new one
    if (status === 419) {
        return { kind: 'stale' };
    }

    if (status === 422) {
        return { kind: 'invalid', errors: body?.errors ?? {}, message: body?.message ?? null };
    }

    if (status === 409) {
        return { kind: 'conflict', message: body?.message ?? null };
    }

    if (status === 404 || status === 403) {
        return { kind: 'notFound' };
    }

    if (status === 429) {
        return { kind: 'tooMany' };
    }

    return { kind: 'failed' };
}

/** "?a=1&b[]=2&b[]=3" from a flat object; empty values are left out */
export function query(params: Record<string, string | number | null | undefined | (string | number)[]>): string {
    const search = new URLSearchParams();

    for (const [key, value] of Object.entries(params)) {
        if (Array.isArray(value)) {
            value.forEach((item) => search.append(`${key}[]`, String(item)));
        } else if (value !== null && value !== undefined && value !== '') {
            search.set(key, String(value));
        }
    }

    const text = search.toString();

    return text ? `?${text}` : '';
}

async function send<T>(method: 'GET' | 'POST', path: string, payload?: unknown, signal?: AbortSignal): Promise<ApiResult<T>> {
    try {
        const response = await fetch(apiUrl(path), {
            method,
            headers: {
                Accept: 'application/json',
                ...(method === 'POST' ? { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() } : {}),
            },
            body: payload === undefined ? undefined : JSON.stringify(payload),
            credentials: 'same-origin',
            signal,
        });

        let body: Body | null = null;

        try {
            body = (await response.json()) as Body;
        } catch {
            body = null;
        }

        return sortResponse<T>(response.status, body);
    } catch (error) {
        if (error instanceof DOMException && error.name === 'AbortError') {
            throw error;
        }

        return { kind: 'failed' };
    }
}

export const getJson = <T>(path: string, signal?: AbortSignal) => send<T>('GET', path, undefined, signal);

export const postJson = <T>(path: string, payload: unknown) => send<T>('POST', path, payload);
