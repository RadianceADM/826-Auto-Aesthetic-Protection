import type { CustomerBooking, PaymentMethodKey, Stage } from '../types';
import { type ApiResult, getJson, postJson, query, sortResponse } from './api';
import { csrfToken } from './auth';
import { apiUrl } from './site';

/** The screenshot: what the server takes */
export const PROOF_TYPES = ['image/png', 'image/jpeg', 'image/webp'];
export const PROOF_ACCEPT = PROOF_TYPES.join(',');
export const PROOF_MAX_BYTES = 8 * 1024 * 1024;

/** Still waiting on the branch or the customer: worth a fresh look when the tab comes back */
export const LIVE_STAGES: Stage[] = ['requested', 'awaiting_payment', 'payment_review', 'confirmed', 'checked_in', 'ready'];

/** Finished, cancelled or lapsed */
export const ENDED_STAGES: Stage[] = ['completed', 'expired', 'cancelled', 'declined', 'no_show'];

const withKey = (path: string, key: string | null) => `${path}${query({ key })}`;

export const fetchBookings = () => getJson<{ bookings: CustomerBooking[] }>('/my-bookings');

/** One booking: the customer's own, or anyone's with its private key */
export const fetchBooking = (id: string | number, key: string | null) => getJson<{ booking: CustomerBooking }>(withKey(`/my-bookings/${encodeURIComponent(String(id))}`, key));

export const cancelBooking = (id: number, key: string | null, reason: string) =>
    postJson<{ booking: CustomerBooking }>(withKey(`/my-bookings/${id}/cancel`, key), reason.trim() ? { reason: reason.trim() } : {});

export const rescheduleBooking = (id: number, key: string | null, date: string, time: string) =>
    postJson<{ booking: CustomerBooking; demo?: boolean }>(withKey(`/my-bookings/${id}/reschedule`, key), { date, time });

export type UploadResult = ApiResult<{ booking: CustomerBooking; demo?: boolean }> | { kind: 'aborted' };

type UploadOptions = {
    id: number;
    key: string | null;
    method: PaymentMethodKey;
    file: File;
    /** 0…1 as it goes up */
    onProgress: (fraction: number) => void;
    signal?: AbortSignal;
};

/** Big phone screenshots are redrawn smaller before they go up (servers often cap uploads at 2 MB) */
const SHRINK_ABOVE_BYTES = 1.5 * 1024 * 1024;
const SHRINK_MAX_EDGE = 2400;

/**
 * A smaller JPEG of the screenshot when it's big, so it uploads fast on
 * mobile data and under the server's limit. Text in a receipt stays sharp
 * at 2400px. Falls back to the original if the browser can't redraw it.
 */
export async function shrinkForUpload(file: File): Promise<File> {
    try {
        const bitmap = await createImageBitmap(file);
        const scale = Math.min(1, SHRINK_MAX_EDGE / Math.max(bitmap.width, bitmap.height));

        if (file.size <= SHRINK_ABOVE_BYTES && scale === 1) {
            bitmap.close();

            return file;
        }

        const canvas = document.createElement('canvas');
        canvas.width = Math.round(bitmap.width * scale);
        canvas.height = Math.round(bitmap.height * scale);
        const context = canvas.getContext('2d');

        if (!context) {
            return file;
        }

        // White behind transparent PNGs, as JPEG has no transparency
        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
        bitmap.close();

        const blob = await new Promise<Blob | null>((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.86));

        return blob && blob.size < file.size ? new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' }) : file;
    } catch {
        return file;
    }
}

/**
 * Sends the payment screenshot. XHR, not fetch, for the upload progress.
 */
export function uploadProof(options: UploadOptions): Promise<UploadResult> {
    return shrinkForUpload(options.file).then((file) => sendProof({ ...options, file }));
}

function sendProof({ id, key, method, file, onProgress, signal }: UploadOptions): Promise<UploadResult> {
    return new Promise((resolve) => {
        const request = new XMLHttpRequest();
        const data = new FormData();
        data.set('method', method);
        data.set('proof', file, file.name);

        request.open('POST', apiUrl(withKey(`/my-bookings/${id}/payment`, key)));
        request.setRequestHeader('Accept', 'application/json');
        request.setRequestHeader('X-CSRF-TOKEN', csrfToken());
        request.withCredentials = true;
        request.responseType = 'json';

        request.upload.addEventListener('progress', (event) => {
            if (event.lengthComputable) {
                onProgress(event.loaded / event.total);
            }
        });

        request.addEventListener('load', () => {
            const body = (request.response ?? null) as Record<string, unknown> | null;
            const result = sortResponse<{ booking: CustomerBooking; demo?: boolean }>(request.status, body);

            resolve(result.kind === 'ok' && !result.data.booking ? { kind: 'failed' } : result);
        });

        request.addEventListener('error', () => resolve({ kind: 'failed' }));
        request.addEventListener('abort', () => resolve({ kind: 'aborted' }));
        signal?.addEventListener('abort', () => request.abort());

        request.send(data);
    });
}

/**
 * Whether a picked file can go up: an image of the right kind, small
 * enough. Some phones leave the type out, so the name's ending counts too.
 */
export function proofProblem(file: File): 'type' | 'size' | null {
    const typeOk = PROOF_TYPES.includes(file.type) || (file.type === '' && /\.(png|jpe?g|webp)$/i.test(file.name));

    if (!typeOk) {
        return 'type';
    }

    return file.size > PROOF_MAX_BYTES ? 'size' : null;
}

/** 2_400_000 → "2.3 MB", 48_000 → "47 KB" */
export function fileSize(bytes: number): string {
    return bytes >= 1024 * 1024 ? `${(bytes / (1024 * 1024)).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

/**
 * Copies a line (an account number) to the clipboard, with the old way
 * for browsers without the Clipboard API.
 */
export async function copyText(value: string): Promise<boolean> {
    try {
        await navigator.clipboard.writeText(value);

        return true;
    } catch {
        const field = document.createElement('textarea');
        field.value = value;
        field.setAttribute('readonly', '');
        field.style.position = 'fixed';
        field.style.opacity = '0';
        document.body.appendChild(field);
        field.select();
        const copied = document.execCommand('copy');
        field.remove();

        return copied;
    }
}
