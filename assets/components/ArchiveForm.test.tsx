import { act, fireEvent, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { ArchiveForm } from './ArchiveForm';

afterEach(() => {
    vi.restoreAllMocks();
});

describe('ArchiveForm', () => {
    it('adds files in multiple batches and removes selected files', async () => {
        const user = userEvent.setup();
        render(<ArchiveForm />);

        const input = screen.getByLabelText(/drop files here/i);
        await user.upload(input, new File(['one'], 'one.txt', { type: 'text/plain' }));
        await user.upload(input, new File(['two'], 'two.txt', { type: 'text/plain' }));

        expect(screen.getByText('one.txt')).toBeInTheDocument();
        expect(screen.getByText('two.txt')).toBeInTheDocument();
        expect(screen.getByText(/2 selected files/i)).toBeInTheDocument();

        await user.click(screen.getByRole('button', { name: /remove one.txt/i }));

        expect(screen.queryByText('one.txt')).not.toBeInTheDocument();
        expect(screen.getByText('two.txt')).toBeInTheDocument();
    });

    it('adds files when crypto.randomUUID is unavailable', async () => {
        const user = userEvent.setup();
        const originalRandomUuid = globalThis.crypto.randomUUID.bind(globalThis.crypto);
        Object.defineProperty(globalThis.crypto, 'randomUUID', {
            configurable: true,
            value: undefined,
        });

        try {
            render(<ArchiveForm />);

            await user.upload(
                screen.getByLabelText(/drop files here/i),
                new File(['one'], 'one.txt'),
            );

            expect(screen.getByText('one.txt')).toBeInTheDocument();
        } finally {
            Object.defineProperty(globalThis.crypto, 'randomUUID', {
                configurable: true,
                value: originalRandomUuid,
            });
        }
    });

    it('disables mutable controls while the archive is being created', async () => {
        const user = userEvent.setup();
        let resolveFetch: (response: Response) => void = () => undefined;
        vi.spyOn(globalThis, 'fetch').mockReturnValue(
            new Promise<Response>((resolve) => {
                resolveFetch = resolve;
            }),
        );
        Object.defineProperty(URL, 'createObjectURL', {
            configurable: true,
            value: vi.fn(() => 'blob:test'),
        });
        Object.defineProperty(URL, 'revokeObjectURL', {
            configurable: true,
            value: vi.fn(),
        });
        vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => undefined);

        render(<ArchiveForm />);

        await user.upload(
            screen.getByLabelText(/drop files here/i),
            new File(['secret'], 'secret.txt'),
        );
        await user.click(screen.getByRole('button', { name: /generate and download zip/i }));

        expect(screen.getByRole('button', { name: /creating archive/i })).toBeDisabled();
        expect(screen.getByLabelText(/protect zip with password/i)).toBeDisabled();
        expect(screen.getByLabelText(/archive password/i)).toBeDisabled();
        expect(screen.getByRole('button', { name: /copy password/i })).toBeEnabled();
        const password = (screen.getByLabelText(/archive password/i) as HTMLInputElement).value;
        await user.click(screen.getByRole('button', { name: /copy password/i }));
        expect(await navigator.clipboard.readText()).toBe(password);
        expect(screen.getByRole('button', { name: /generate a new password/i })).toBeDisabled();
        expect(screen.getByRole('radio', { name: /maximum compatibility/i })).toBeDisabled();
        expect(screen.getByRole('button', { name: /remove secret.txt/i })).toBeDisabled();

        resolveFetch(new Response('zip', { status: 200 }));
        await waitFor(() =>
            expect(
                screen.getByRole('button', { name: /generate and download zip/i }),
            ).toBeEnabled(),
        );
    });

    it('submits files and password settings to the archive API', async () => {
        const user = userEvent.setup();
        const fetchMock = vi.spyOn(globalThis, 'fetch').mockResolvedValue(
            new Response('zip', {
                headers: {
                    'Content-Disposition': 'attachment; filename=archive-test.zip',
                },
                status: 200,
            }),
        );
        Object.defineProperty(URL, 'createObjectURL', {
            configurable: true,
            value: vi.fn(() => 'blob:test'),
        });
        Object.defineProperty(URL, 'revokeObjectURL', {
            configurable: true,
            value: vi.fn(),
        });
        const clickSpy = vi
            .spyOn(HTMLAnchorElement.prototype, 'click')
            .mockImplementation(() => undefined);

        render(<ArchiveForm />);

        await user.upload(
            screen.getByLabelText(/drop files here/i),
            new File(['secret'], 'secret.txt'),
        );
        await user.clear(screen.getByDisplayValue(/-/));
        await user.type(screen.getByRole('textbox'), 'custom-password');
        // ZipCrypto is the default for maximum Windows compatibility; switch to
        // AES-256 to exercise the opt-in stronger-encryption path.
        expect(screen.getByRole('radio', { name: /maximum compatibility/i })).toBeChecked();
        fireEvent.click(screen.getByRole('radio', { name: /increased security/i }));
        await user.click(screen.getByRole('button', { name: /generate and download zip/i }));

        await waitFor(() =>
            expect(fetchMock).toHaveBeenCalledWith('/api/archives', expect.any(Object)),
        );
        const [, init] = fetchMock.mock.calls[0];
        const body = init?.body;
        expect(body).toBeInstanceOf(FormData);
        expect((body as FormData).get('passwordEnabled')).toBe('true');
        expect((body as FormData).get('password')).toBe('custom-password');
        expect((body as FormData).get('encryptionMode')).toBe('aes256');
        expect((body as FormData).getAll('files[]')).toHaveLength(1);
        expect(clickSpy).toHaveBeenCalled();
    });

    it('submits the default zipcrypto mode when the encryption choice is untouched', async () => {
        const user = userEvent.setup();
        const fetchMock = vi.spyOn(globalThis, 'fetch').mockResolvedValue(
            new Response('zip', {
                headers: {
                    'Content-Disposition': 'attachment; filename=archive-test.zip',
                },
                status: 200,
            }),
        );
        Object.defineProperty(URL, 'createObjectURL', {
            configurable: true,
            value: vi.fn(() => 'blob:test'),
        });
        Object.defineProperty(URL, 'revokeObjectURL', {
            configurable: true,
            value: vi.fn(),
        });
        vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => undefined);

        render(<ArchiveForm />);

        await user.upload(
            screen.getByLabelText(/drop files here/i),
            new File(['secret'], 'secret.txt'),
        );
        // No interaction with the encryption radios: the submitted payload must
        // carry the ZipCrypto default so Windows Explorer users get a readable
        // archive without opting into anything.
        await user.click(screen.getByRole('button', { name: /generate and download zip/i }));

        await waitFor(() =>
            expect(fetchMock).toHaveBeenCalledWith('/api/archives', expect.any(Object)),
        );
        const [, init] = fetchMock.mock.calls[0];
        const body = init?.body;
        expect(body).toBeInstanceOf(FormData);
        expect((body as FormData).get('encryptionMode')).toBe('zipcrypto');
    });

    it('shows a cooldown banner, counts down, resets the form, and recovers on a successful retry', async () => {
        // Drives the React effect with fake timers so the 3s countdown does
        // not actually elapse in real time. We use fireEvent rather than
        // userEvent here because userEvent's internal scheduling does not
        // interoperate cleanly with fake timers swapped in mid-test.
        vi.useFakeTimers();

        try {
            // First submit is rate-limited; second submit (after the
            // cooldown ends) succeeds and triggers a download.
            const fetchMock = vi
                .spyOn(globalThis, 'fetch')
                .mockResolvedValueOnce(
                    new Response(
                        JSON.stringify({
                            error: {
                                code: 'rate_limited',
                                message: 'Too many archive requests from your IP.',
                                retryAfterSeconds: 3,
                            },
                        }),
                        {
                            status: 429,
                            headers: {
                                'Content-Type': 'application/json',
                                'Retry-After': '3',
                            },
                        },
                    ),
                )
                .mockResolvedValueOnce(
                    new Response('zip', {
                        headers: {
                            'Content-Disposition': 'attachment; filename=archive-recovered.zip',
                        },
                        status: 200,
                    }),
                );

            // Stub download plumbing so the success path can complete
            // without jsdom throwing on URL.createObjectURL.
            Object.defineProperty(URL, 'createObjectURL', {
                configurable: true,
                value: vi.fn(() => 'blob:test'),
            });
            Object.defineProperty(URL, 'revokeObjectURL', {
                configurable: true,
                value: vi.fn(),
            });
            const clickSpy = vi
                .spyOn(HTMLAnchorElement.prototype, 'click')
                .mockImplementation(() => undefined);

            render(<ArchiveForm />);

            fireEvent.change(screen.getByLabelText(/drop files here/i), {
                target: { files: [new File(['secret'], 'secret.txt')] },
            });
            fireEvent.click(screen.getByRole('button', { name: /generate and download zip/i }));

            // Flush the fetch promise chain. createArchive awaits fetch,
            // rateLimitErrorFromResponse, and response.clone().json(), so a
            // single Promise.resolve() tick is not enough. Advancing fake
            // timers by 0 also drains microtasks and is robust against
            // additional awaits being inserted in the chain later.
            await act(async () => {
                await vi.advanceTimersByTimeAsync(0);
            });
            // Pin the accessibility contract directly: the role="status"
            // live region carries the message plus the initial wait
            // duration once, while the visible banner shows the ticking
            // countdown for sighted users. The sr-only region uses a
            // distinct "in about N seconds" phrasing that doesn't appear
            // in the visible banner ("in Ns."), so text queries
            // disambiguate without coupling to role/name internals.
            expect(screen.getByText(/about 3 seconds/i)).toBeInTheDocument();
            expect(
                screen.getByText(
                    /too many archive requests from your ip\. you can try again in about 3 seconds/i,
                ),
            ).toBeInTheDocument();
            expect(screen.getByText(/try again in 3s\./i)).toBeInTheDocument();
            expect(screen.getByRole('button', { name: /try again in 3s/i })).toBeDisabled();

            // Countdown ticks down once per second.
            await act(async () => {
                await vi.advanceTimersByTimeAsync(1000);
            });
            expect(screen.getByText(/try again in 2s\./i)).toBeInTheDocument();

            // After the full cooldown elapses, the banner disappears and the
            // submit button returns to its default label and enabled state.
            // Tick one second at a time so each render commits and schedules
            // the next setTimeout before we advance the clock again.
            await act(async () => {
                await vi.advanceTimersByTimeAsync(1000);
            });
            expect(screen.getByText(/try again in 1s\./i)).toBeInTheDocument();

            await act(async () => {
                await vi.advanceTimersByTimeAsync(1000);
            });
            // After cooldown the sr-only region renders an empty string and
            // the visible banner unmounts, so the message text is no longer
            // findable anywhere in the document.
            expect(
                screen.queryByText(/too many archive requests from your ip/i),
            ).not.toBeInTheDocument();
            expect(screen.queryByText(/try again in/i)).not.toBeInTheDocument();
            expect(
                screen.getByRole('button', { name: /generate and download zip/i }),
            ).toBeEnabled();

            // A fresh submit after the cooldown ends should succeed and
            // fire the download anchor, with no rate-limit residue.
            fireEvent.click(screen.getByRole('button', { name: /generate and download zip/i }));
            await act(async () => {
                await vi.advanceTimersByTimeAsync(0);
            });
            expect(fetchMock).toHaveBeenCalledTimes(2);
            expect(clickSpy).toHaveBeenCalled();
            expect(
                screen.queryByText(/too many archive requests from your ip/i),
            ).not.toBeInTheDocument();
            expect(
                screen.getByRole('button', { name: /generate and download zip/i }),
            ).toBeEnabled();
        } finally {
            vi.useRealTimers();
            // Object.defineProperty stubs with a `value` descriptor are not
            // restored by vi.restoreAllMocks(), so undo them here to keep
            // the URL.* surface clean for later tests.
            delete (URL as { createObjectURL?: unknown }).createObjectURL;
            delete (URL as { revokeObjectURL?: unknown }).revokeObjectURL;
        }
    });
});
