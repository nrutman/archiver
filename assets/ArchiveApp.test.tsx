import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { ArchiveApp } from './ArchiveApp';

describe('ArchiveApp', () => {
    it('renders the scaffolded product shell', () => {
        render(<ArchiveApp />);

        expect(
            screen.getByRole('heading', { name: /create password-protected zip archives/i }),
        ).toBeInTheDocument();
        expect(screen.getByText(/Symfony serves the app shell/i)).toBeInTheDocument();
    });
});
