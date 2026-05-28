import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { PasswordControls } from './PasswordControls';

afterEach(() => {
    vi.restoreAllMocks();
});

describe('PasswordControls', () => {
    it('copies the current password to the clipboard', async () => {
        const user = userEvent.setup();
        const writeText = vi.fn().mockResolvedValue(undefined);
        Object.defineProperty(navigator, 'clipboard', {
            configurable: true,
            value: { writeText },
        });

        render(
            <PasswordControls
                encryptionMode="aes256"
                onEncryptionModeChange={vi.fn()}
                onPasswordChange={vi.fn()}
                onPasswordEnabledChange={vi.fn()}
                onRegeneratePassword={vi.fn()}
                password="giant-cheetah-284-upstairs-199"
                passwordEnabled
            />,
        );

        await user.click(screen.getByRole('button', { name: /copy password/i }));

        expect(writeText).toHaveBeenCalledWith('giant-cheetah-284-upstairs-199');
        await waitFor(() =>
            expect(screen.getByText(/password copied to clipboard/i)).toBeInTheDocument(),
        );
    });

    it('disables the copy button when password protection is disabled', () => {
        render(
            <PasswordControls
                encryptionMode="aes256"
                onEncryptionModeChange={vi.fn()}
                onPasswordChange={vi.fn()}
                onPasswordEnabledChange={vi.fn()}
                onRegeneratePassword={vi.fn()}
                password="giant-cheetah-284-upstairs-199"
                passwordEnabled={false}
            />,
        );

        expect(screen.getByRole('button', { name: /copy password/i })).toBeDisabled();
    });
});
