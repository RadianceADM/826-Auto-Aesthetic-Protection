import type { DayAvailability, MonthAvailability } from '../types';
import { type ApiResult, getJson, query } from './api';

/**
 * Whose job the times are for: a new booking's branch, size and services,
 * or an existing booking being moved (its own key opens it).
 */
export type JobQuery = { kind: 'new'; branch: number; size: number; services: number[] } | { kind: 'move'; booking: number; key: string | null };

function jobParams(job: JobQuery) {
    return job.kind === 'new'
        ? { branch: job.branch, size: job.size, services: [...job.services].sort((a, b) => a - b) }
        : { booking: job.booking, key: job.key };
}

/** The same job asks once a minute at most: the calendar and the price step share an answer */
const FRESH_FOR_MS = 60_000;
const cache = new Map<string, { at: number; promise: Promise<ApiResult<unknown>> }>();

function cached<T>(path: string): Promise<ApiResult<T>> {
    const hit = cache.get(path);

    if (hit && Date.now() - hit.at < FRESH_FOR_MS) {
        return hit.promise as Promise<ApiResult<T>>;
    }

    const promise = getJson<T>(path).then((result) => {
        // Only good answers are kept; a failure asks again next time
        if (result.kind !== 'ok') {
            cache.delete(path);
        }

        return result;
    });

    cache.set(path, { at: Date.now(), promise });

    return promise;
}

export const jobKey = (job: JobQuery) => query(jobParams(job));

/**
 * Open, full and closed days for a month ("2026-11"); without a month,
 * the server's current one.
 */
export const fetchMonth = (job: JobQuery, month?: string) => cached<MonthAvailability>(`/availability${query({ ...jobParams(job), month })}`);

/** The drop-off times with room on one day */
export const fetchDay = (job: JobQuery, date: string) => cached<DayAvailability>(`/availability/day${query({ ...jobParams(job), date })}`);

/**
 * After a time turned out to be taken (or a booking moved): everything for
 * that job is asked again next time.
 */
export function forget(job: JobQuery): void {
    const key = jobKey(job).slice(1);

    for (const path of [...cache.keys()]) {
        if (path.includes(key)) {
            cache.delete(path);
        }
    }
}
