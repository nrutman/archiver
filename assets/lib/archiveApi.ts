export type EncryptionMode = 'aes256' | 'zipcrypto';

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
    try {
        const body = (await response.json()) as unknown;
        if (isErrorResponse(body)) {
            return body.error.message;
        }
    } catch {
        // Fall through to the generic message.
    }

    return 'Could not create the archive. Please try again.';
}

function isErrorResponse(value: unknown): value is { error: { message: string } } {
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
