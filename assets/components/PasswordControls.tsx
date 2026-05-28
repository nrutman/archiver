import { Check, Copy, RefreshCw, ShieldCheck } from 'lucide-react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import type { EncryptionMode } from '@/lib/archiveApi';

interface PasswordControlsProps {
    disabled?: boolean;
    encryptionMode: EncryptionMode;
    password: string;
    passwordEnabled: boolean;
    onEncryptionModeChange: (mode: EncryptionMode) => void;
    onPasswordChange: (password: string) => void;
    onPasswordEnabledChange: (enabled: boolean) => void;
    onRegeneratePassword: () => void;
}

/**
 * Controls password protection, generated password editing, and encryption compatibility mode.
 */
export function PasswordControls({
    disabled = false,
    encryptionMode,
    password,
    passwordEnabled,
    onEncryptionModeChange,
    onPasswordChange,
    onPasswordEnabledChange,
    onRegeneratePassword,
}: PasswordControlsProps) {
    const [copyStatus, setCopyStatus] = useState<'idle' | 'copied' | 'failed'>('idle');

    async function copyPassword(): Promise<void> {
        try {
            await navigator.clipboard.writeText(password);
            setCopyStatus('copied');
        } catch {
            setCopyStatus('failed');
        }
    }

    function updatePassword(value: string): void {
        setCopyStatus('idle');
        onPasswordChange(value);
    }

    return (
        <section
            className="space-y-4 rounded-2xl border bg-card p-5 shadow-sm"
            aria-labelledby="password-heading"
        >
            <div className="flex items-start gap-3">
                <ShieldCheck className="mt-1 h-5 w-5 text-primary" aria-hidden="true" />
                <div>
                    <h2 className="font-semibold" id="password-heading">
                        Password protection
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        Protect archives by default with a generated memorable password.
                    </p>
                </div>
            </div>

            <label className="flex items-center gap-2 text-sm font-medium">
                <input
                    checked={passwordEnabled}
                    className="h-4 w-4 rounded border-input"
                    disabled={disabled}
                    onChange={(event) => onPasswordEnabledChange(event.currentTarget.checked)}
                    type="checkbox"
                />
                Protect ZIP with password
            </label>

            <div className="space-y-2">
                <label className="block text-sm font-medium" htmlFor="archive-password">
                    Archive password
                </label>
                <div className="flex">
                    <input
                        className="h-10 min-w-0 flex-1 rounded-md rounded-r-none border border-input bg-background px-3 py-2 font-mono text-sm shadow-sm disabled:cursor-not-allowed disabled:opacity-50"
                        disabled={disabled || !passwordEnabled}
                        id="archive-password"
                        onChange={(event) => updatePassword(event.currentTarget.value)}
                        type="text"
                        value={password}
                    />
                    <Button
                        aria-label="Copy password"
                        className="h-10 rounded-none border-l-0 px-3"
                        disabled={disabled || !passwordEnabled || password.length === 0}
                        onClick={() => void copyPassword()}
                        title="Copy password"
                        type="button"
                        variant="outline"
                    >
                        {copyStatus === 'copied' ? (
                            <Check className="h-4 w-4" aria-hidden="true" />
                        ) : (
                            <Copy className="h-4 w-4" aria-hidden="true" />
                        )}
                    </Button>
                    <Button
                        aria-label="Generate a new password"
                        className="h-10 rounded-l-none border-l-0 px-3"
                        disabled={disabled || !passwordEnabled}
                        onClick={() => {
                            setCopyStatus('idle');
                            onRegeneratePassword();
                        }}
                        title="Generate a new password"
                        type="button"
                        variant="outline"
                    >
                        <RefreshCw className="h-4 w-4" aria-hidden="true" />
                    </Button>
                </div>
                <p className="min-h-5 text-xs text-muted-foreground" role="status">
                    {copyStatus === 'copied' ? 'Password copied to clipboard.' : null}
                    {copyStatus === 'failed'
                        ? 'Could not copy password. Select the text and copy it manually.'
                        : null}
                </p>
            </div>

            {passwordEnabled ? (
                <fieldset className="space-y-3">
                    <legend className="text-sm font-medium">Encryption</legend>
                    <label className="flex cursor-pointer gap-3 rounded-xl border p-3 text-sm has-[:disabled]:cursor-not-allowed has-[:disabled]:opacity-60">
                        <input
                            checked={encryptionMode === 'aes256'}
                            className="mt-1"
                            disabled={disabled}
                            name="encryptionMode"
                            onChange={() => onEncryptionModeChange('aes256')}
                            type="radio"
                        />
                        <span>
                            <span className="block font-medium">Strong encryption</span>
                            <span className="block text-muted-foreground">
                                AES-256, requires 7-Zip, WinZip, Keka, or another modern ZIP tool.
                            </span>
                        </span>
                    </label>
                    <label className="flex cursor-pointer gap-3 rounded-xl border p-3 text-sm has-[:disabled]:cursor-not-allowed has-[:disabled]:opacity-60">
                        <input
                            checked={encryptionMode === 'zipcrypto'}
                            className="mt-1"
                            disabled={disabled}
                            name="encryptionMode"
                            onChange={() => onEncryptionModeChange('zipcrypto')}
                            type="radio"
                        />
                        <span>
                            <span className="block font-medium">Windows Explorer compatible</span>
                            <span className="block text-muted-foreground">
                                Opens natively in Windows Explorer, but uses weaker ZipCrypto
                                protection.
                            </span>
                        </span>
                    </label>
                </fieldset>
            ) : null}
        </section>
    );
}
