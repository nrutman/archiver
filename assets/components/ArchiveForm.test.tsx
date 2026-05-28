import { fireEvent, render, screen, waitFor } from '@testing-library/react';
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
        expect(screen.getByRole('button', { name: /copy password/i })).toBeDisabled();
        expect(screen.getByRole('button', { name: /generate a new password/i })).toBeDisabled();
        expect(screen.getByRole('radio', { name: /strong encryption/i })).toBeDisabled();
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
        fireEvent.click(screen.getByRole('radio', { name: /windows explorer compatible/i }));
        await user.click(screen.getByRole('button', { name: /generate and download zip/i }));

        await waitFor(() =>
            expect(fetchMock).toHaveBeenCalledWith('/api/archives', expect.any(Object)),
        );
        const [, init] = fetchMock.mock.calls[0];
        const body = init?.body;
        expect(body).toBeInstanceOf(FormData);
        expect((body as FormData).get('passwordEnabled')).toBe('true');
        expect((body as FormData).get('password')).toBe('custom-password');
        expect((body as FormData).get('encryptionMode')).toBe('zipcrypto');
        expect((body as FormData).getAll('files[]')).toHaveLength(1);
        expect(clickSpy).toHaveBeenCalled();
    });
});
