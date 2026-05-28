import { afterEach, describe, expect, it, vi } from 'vitest';

import { createArchive, downloadArchive } from './archiveApi';

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
