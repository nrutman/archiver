import { afterEach, describe, expect, it, vi } from 'vitest';

import { RateLimitError, createArchive, downloadArchive } from './archiveApi';

afterEach(() => {
    vi.restoreAllMocks();
});

describe('archiveApi', () => {
    it('normalizes downloaded archive filenames to zip files', async () => {
        vi.spyOn(globalThis, 'fetch').mockResolvedValue(
            new Response('zip', {
                headers: {
                    'Content-Disposition': 'attachment; filename=archive-test',
                },
                status: 200,
            }),
        );

        const archive = await createArchive({
            encryptionMode: 'aes256',
            files: [new File(['secret'], 'secret.txt')],
            password: 'password',
            passwordEnabled: true,
        });

        expect(archive.filename).toBe('archive-test.zip');
    });

    it('throws the server-provided error message on a 400 response', async () => {
        vi.spyOn(globalThis, 'fetch').mockResolvedValue(
            new Response(JSON.stringify({ error: { message: 'Add at least one file.' } }), {
                status: 400,
                headers: { 'Content-Type': 'application/json' },
            }),
        );

        await expect(
            createArchive({
                encryptionMode: 'aes256',
                files: [new File(['secret'], 'secret.txt')],
                password: 'password',
                passwordEnabled: true,
            }),
        ).rejects.toThrow('Add at least one file.');
    });

    it('falls back to a generic message when a 400 body is missing or malformed', async () => {
        vi.spyOn(globalThis, 'fetch').mockResolvedValue(
            new Response('<html>oops</html>', { status: 400 }),
        );

        await expect(
            createArchive({
                encryptionMode: 'aes256',
                files: [new File(['secret'], 'secret.txt')],
                password: 'password',
                passwordEnabled: true,
            }),
        ).rejects.toThrow('Could not create the archive. Please try again.');
    });

    it('throws a RateLimitError with the server-provided retry hint on HTTP 429', async () => {
        vi.spyOn(globalThis, 'fetch').mockResolvedValue(
            new Response(
                JSON.stringify({
                    error: {
                        code: 'rate_limited',
                        message: 'Slow down please.',
                        retryAfterSeconds: 37,
                    },
                }),
                {
                    status: 429,
                    headers: {
                        'Content-Type': 'application/json',
                        'Retry-After': '37',
                    },
                },
            ),
        );

        await expect(
            createArchive({
                encryptionMode: 'aes256',
                files: [new File(['secret'], 'secret.txt')],
                password: 'password',
                passwordEnabled: true,
            }),
        ).rejects.toMatchObject({
            name: 'RateLimitError',
            message: 'Slow down please.',
            retryAfterSeconds: 37,
        });
    });

    it.each([
        {
            name: 'no body, no Retry-After header',
            response: new Response('', { status: 429 }),
            expected: 60,
        },
        {
            name: 'Retry-After above the 600s ceiling',
            response: new Response('', { status: 429, headers: { 'Retry-After': '99999' } }),
            expected: 600,
        },
        {
            name: 'Retry-After as an HTTP-date (unsupported — falls back)',
            response: new Response('', {
                status: 429,
                headers: { 'Retry-After': 'Wed, 21 Oct 2026 07:28:00 GMT' },
            }),
            expected: 60,
        },
        {
            name: 'Retry-After of zero (clamped up to the 1s floor)',
            response: new Response('', { status: 429, headers: { 'Retry-After': '0' } }),
            expected: 1,
        },
    ])('clamps retryAfterSeconds on a 429: $name', async ({ response, expected }) => {
        vi.spyOn(globalThis, 'fetch').mockResolvedValue(response);

        let captured: unknown;
        try {
            await createArchive({
                encryptionMode: 'aes256',
                files: [new File(['secret'], 'secret.txt')],
                password: 'password',
                passwordEnabled: true,
            });
        } catch (error) {
            captured = error;
        }

        expect(captured).toBeInstanceOf(RateLimitError);
        expect((captured as RateLimitError).retryAfterSeconds).toBe(expected);
    });

    it('falls back to the Retry-After header when the 429 body is missing', async () => {
        vi.spyOn(globalThis, 'fetch').mockResolvedValue(
            new Response('', {
                status: 429,
                headers: { 'Retry-After': '12' },
            }),
        );

        let captured: unknown;
        try {
            await createArchive({
                encryptionMode: 'aes256',
                files: [new File(['secret'], 'secret.txt')],
                password: 'password',
                passwordEnabled: true,
            });
        } catch (error) {
            captured = error;
        }

        expect(captured).toBeInstanceOf(RateLimitError);
        expect((captured as RateLimitError).retryAfterSeconds).toBe(12);
    });

    it('uses a zip filename for browser downloads', () => {
        Object.defineProperty(URL, 'createObjectURL', {
            configurable: true,
            value: vi.fn(() => 'blob:https://archiver.example.test/archive'),
        });
        Object.defineProperty(URL, 'revokeObjectURL', {
            configurable: true,
            value: vi.fn(),
        });
        const clickedAnchors: HTMLAnchorElement[] = [];
        const clickSpy = vi
            .spyOn(HTMLAnchorElement.prototype, 'click')
            .mockImplementation(function (this: HTMLAnchorElement) {
                clickedAnchors.push(this);
            });

        downloadArchive({
            blob: new Blob(['zip'], { type: 'application/zip' }),
            filename: 'archive-test',
        });

        expect(clickedAnchors[0]?.download).toBe('archive-test.zip');
        expect(clickedAnchors[0]?.href).toBe('blob:https://archiver.example.test/archive');
        expect(document.querySelector('a')).not.toBeInTheDocument();
        expect(clickSpy).toHaveBeenCalled();
    });
});
