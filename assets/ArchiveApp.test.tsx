import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { ArchiveApp } from './ArchiveApp';

describe('ArchiveApp', () => {
    it('renders the archive creation workflow', () => {
        render(<ArchiveApp />);

        expect(
            screen.getByRole('heading', { name: /create password-protected zip archives/i }),
        ).toBeInTheDocument();
        expect(screen.getByText(/drop files here or click to browse/i)).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /generate and download zip/i })).toBeDisabled();
    });
});
