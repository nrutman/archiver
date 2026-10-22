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

    it.each([
        { name: 'protection is disabled', passwordEnabled: false, password: 'secret' },
        { name: 'password is empty', passwordEnabled: true, password: '' },
    ])('disables the copy button when $name', ({ passwordEnabled, password }) => {
        render(
            <PasswordControls
                encryptionMode="aes256"
                onEncryptionModeChange={vi.fn()}
                onPasswordChange={vi.fn()}
                onPasswordEnabledChange={vi.fn()}
                onRegeneratePassword={vi.fn()}
                password={password}
                passwordEnabled={passwordEnabled}
            />,
        );

        expect(screen.getByRole('button', { name: /copy password/i })).toBeDisabled();
    });
});
