export type EncryptionMode = 'aes256' | 'zipcrypto';

/**
 * Thrown when the archive API responds with HTTP 429.
 *
 * Callers can use {@link retryAfterSeconds} to drive a user-facing countdown
 * before re-enabling the submit action.
 */
export class RateLimitError extends Error {
    public readonly retryAfterSeconds: number;

    constructor(message: string, retryAfterSeconds: number) {
        super(message);
        this.name = 'RateLimitError';
        this.retryAfterSeconds = retryAfterSeconds;
    }
}

export interface CreateArchiveInput {
    files: File[];
    passwordEnabled: boolean;
    password: string;
    encryptionMode: EncryptionMode;
}

export interface ArchiveDownload {
    blob: Blob;
    filename: string;
}

/**
 * Sends selected files and password settings to the archive API.
 */
export async function createArchive(input: CreateArchiveInput): Promise<ArchiveDownload> {
    const formData = new FormData();

    for (const file of input.files) {
        formData.append('files[]', file, file.name);
    }

    formData.append('passwordEnabled', String(input.passwordEnabled));
    if (input.passwordEnabled) {
        formData.append('password', input.password);
        formData.append('encryptionMode', input.encryptionMode);
    }

    const response = await fetch('/api/archives', {
        method: 'POST',
        body: formData,
    });

    if (response.status === 429) {
        throw await rateLimitErrorFromResponse(response);
    }

    if (!response.ok) {
        throw new Error(await errorMessageFromResponse(response));
    }

    return {
        blob: await response.blob(),
        filename: filenameFromDisposition(response.headers.get('Content-Disposition')),
    };
}

/**
 * Starts a browser download for an archive blob.
 */
export function downloadArchive(download: ArchiveDownload): void {
    const url = URL.createObjectURL(download.blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = zipFilename(download.filename);
    document.body.append(anchor);
    try {
        anchor.click();
    } finally {
        anchor.remove();
        URL.revokeObjectURL(url);
    }
}

async function errorMessageFromResponse(response: Response): Promise<string> {
    const body = await safeJsonBody(response);
    if (isErrorResponse(body)) {
        return body.error.message;
    }

    return 'Could not create the archive. Please try again.';
}

async function rateLimitErrorFromResponse(response: Response): Promise<RateLimitError> {
    const body = await safeJsonBody(response);

    const message = isErrorResponse(body)
        ? body.error.message
        : 'Too many archive requests. Please wait a moment and try again.';

    // Prefer the server-provided retry hint, then the Retry-After header, then
    // a conservative fallback. Some proxies strip JSON error bodies, so the
    // header-based path keeps the countdown working in those cases too.
    const bodyRetry =
        isErrorResponse(body) && typeof body.error.retryAfterSeconds === 'number'
            ? body.error.retryAfterSeconds
            : undefined;
    const retryAfterSeconds = clampRetrySeconds(
        bodyRetry ?? parseRetryAfterHeader(response.headers.get('Retry-After')),
    );

    return new RateLimitError(message, retryAfterSeconds);
}

async function safeJsonBody(response: Response): Promise<unknown> {
    try {
        return await response.clone().json();
    } catch {
        return undefined;
    }
}

/**
 * Narrows `unknown` to the verified shape of an API error body.
 *
 * The predicate only promises what it actually checks at runtime
 * (a string `error.message`). Callers that read other optional fields
 * such as `retryAfterSeconds` are responsible for their own narrowing
 * — see `rateLimitErrorFromResponse` for the existing `typeof` guard.
 */
function isErrorResponse(
    value: unknown,
): value is { error: Record<string, unknown> & { message: string } } {
    return (
        typeof value === 'object' &&
        value !== null &&
        'error' in value &&
        typeof value.error === 'object' &&
        value.error !== null &&
        'message' in value.error &&
        typeof value.error.message === 'string'
    );
}

function parseRetryAfterHeader(value: string | null): number | undefined {
    if (!value) {
        return undefined;
    }

    const seconds = Number(value);
    return Number.isFinite(seconds) ? seconds : undefined;
}

function clampRetrySeconds(value: number | undefined): number {
    // Missing or non-finite values fall back to a conservative 60s. Finite
    // values are clamped to the documented 1-600s window so a malformed or
    // zero Retry-After never re-enables the submit button instantly and a
    // pathological large value can't lock the UI out indefinitely.
    if (typeof value !== 'number' || !Number.isFinite(value)) {
        return 60;
    }

    return Math.min(Math.max(1, Math.ceil(value)), 600);
}

function filenameFromDisposition(disposition: string | null): string {
    if (!disposition) {
        return 'archive.zip';
    }

    const match = /filename\*?=(?:UTF-8''|")?([^";]+)/i.exec(disposition);
    return zipFilename(match?.[1] ? decodeURIComponent(match[1]) : 'archive.zip');
}

function zipFilename(filename: string): string {
    const trimmedFilename = filename.trim() || 'archive.zip';

    return trimmedFilename.toLowerCase().endsWith('.zip')
        ? trimmedFilename
        : `${trimmedFilename}.zip`;
}
