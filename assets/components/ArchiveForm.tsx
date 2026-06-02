import { Download } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

import { FileDropzone } from '@/components/FileDropzone';
import { FileList, type SelectedFile } from '@/components/FileList';
import { PasswordControls } from '@/components/PasswordControls';
import { Button } from '@/components/ui/button';
import {
    RateLimitError,
    createArchive,
    downloadArchive,
    type EncryptionMode,
} from '@/lib/archiveApi';
import { generatePassword } from '@/lib/passwordGenerator';

/**
 * Complete browser-side archive creation workflow.
 */
export function ArchiveForm() {
    const [files, setFiles] = useState<SelectedFile[]>([]);
    const [passwordEnabled, setPasswordEnabled] = useState(true);
    const [password, setPassword] = useState(() => generatePassword());
    const [encryptionMode, setEncryptionMode] = useState<EncryptionMode>('zipcrypto');
    const [error, setError] = useState<string | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [rateLimit, setRateLimit] = useState<RateLimitState | null>(null);

    useEffect(() => {
        if (!rateLimit || rateLimit.secondsRemaining <= 0) {
            return;
        }

        const timer = window.setTimeout(() => {
            setRateLimit((current) =>
                current ? { ...current, secondsRemaining: current.secondsRemaining - 1 } : current,
            );
        }, 1000);

        return () => window.clearTimeout(timer);
    }, [rateLimit]);

    const isRateLimited = rateLimit !== null && rateLimit.secondsRemaining > 0;
    const canSubmit =
        files.length > 0 &&
        (!passwordEnabled || password.trim().length > 0) &&
        !isSubmitting &&
        !isRateLimited;
    const selectedFiles = useMemo(() => files.map((selectedFile) => selectedFile.file), [files]);

    function addFiles(newFiles: File[]): void {
        setFiles((currentFiles) => [
            ...currentFiles,
            ...newFiles.map((file) => ({
                id: createSelectedFileId(file),
                file,
            })),
        ]);
        setError(null);
    }

    async function submitArchive(): Promise<void> {
        if (!canSubmit) {
            return;
        }

        setIsSubmitting(true);
        setError(null);
        // Clear any lingering rate-limit state from a prior 429 so a fresh
        // attempt starts from a clean slate rather than carrying around a
        // zeroed-out cooldown object that the UI no longer renders.
        setRateLimit(null);

        try {
            const archive = await createArchive({
                files: selectedFiles,
                passwordEnabled,
                password,
                encryptionMode,
            });
            downloadArchive(archive);
        } catch (caughtError) {
            if (caughtError instanceof RateLimitError) {
                setRateLimit({
                    message: caughtError.message,
                    secondsRemaining: caughtError.retryAfterSeconds,
                    initialSecondsRemaining: caughtError.retryAfterSeconds,
                });
            } else {
                setError(
                    caughtError instanceof Error
                        ? caughtError.message
                        : 'Could not create the archive. Please try again.',
                );
            }
        } finally {
            setIsSubmitting(false);
        }
    }

    return (
        <div className="grid gap-6 lg:grid-cols-[1.1fr_0.9fr]">
            <section className="space-y-4">
                <FileDropzone disabled={isSubmitting} onFilesAdded={addFiles} />
                <FileList
                    disabled={isSubmitting}
                    files={files}
                    onRemove={(id) => {
                        setFiles((currentFiles) =>
                            currentFiles.filter((selectedFile) => selectedFile.id !== id),
                        );
                    }}
                />
            </section>

            <aside className="space-y-4">
                <PasswordControls
                    disabled={isSubmitting}
                    encryptionMode={encryptionMode}
                    onEncryptionModeChange={setEncryptionMode}
                    onPasswordChange={setPassword}
                    onPasswordEnabledChange={setPasswordEnabled}
                    onRegeneratePassword={() => setPassword(generatePassword())}
                    password={password}
                    passwordEnabled={passwordEnabled}
                />

                {error ? (
                    <div
                        className="rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive"
                        role="alert"
                    >
                        {error}
                    </div>
                ) : null}

                {/*
                  Permanent visually-hidden live region. Screen readers see
                  the rate-limit message as a text-content change on this
                  always-mounted node, which is more reliable across
                  assistive technologies than announcing a freshly-inserted
                  role="alert" subtree. The announced text includes the
                  initial wait duration (captured once when the 429 lands)
                  so AT users get actionable timing info — the ticking
                  countdown in the visible banner is sighted-only.
                */}
                <p className="sr-only" role="status">
                    {isRateLimited && rateLimit
                        ? `${rateLimit.message} You can try again in about ${rateLimit.initialSecondsRemaining} seconds.`
                        : ''}
                </p>

                {isRateLimited && rateLimit ? (
                    <div
                        aria-hidden="true"
                        className="rounded-xl border border-amber-400/40 bg-amber-50 px-4 py-3 text-sm text-amber-900"
                    >
                        <p className="font-medium">{rateLimit.message}</p>
                        <p className="mt-1 text-amber-800">
                            You can try again in {rateLimit.secondsRemaining}s.
                        </p>
                    </div>
                ) : null}

                <Button
                    className="w-full"
                    disabled={!canSubmit}
                    onClick={() => void submitArchive()}
                    size="lg"
                    type="button"
                >
                    <Download className="mr-2 h-4 w-4" aria-hidden="true" />
                    {submitButtonLabel(isSubmitting, rateLimit)}
                </Button>
            </aside>
        </div>
    );
}

interface RateLimitState {
    message: string;
    /** Live tick value, decremented every second by the countdown effect. */
    secondsRemaining: number;
    /**
     * Wait duration captured at the moment the rate limit triggered.
     * Held stable across the cooldown so the screen-reader announcement
     * is read once on appearance rather than re-narrated every second.
     */
    initialSecondsRemaining: number;
}

function submitButtonLabel(isSubmitting: boolean, rateLimit: RateLimitState | null): string {
    if (isSubmitting) {
        return 'Creating archive…';
    }

    if (rateLimit && rateLimit.secondsRemaining > 0) {
        return `Try again in ${rateLimit.secondsRemaining}s`;
    }

    return 'Generate and download ZIP';
}

function createSelectedFileId(file: File): string {
    const randomValue =
        typeof globalThis.crypto?.randomUUID === 'function'
            ? globalThis.crypto.randomUUID()
            : `${Date.now()}-${Math.random().toString(36).slice(2)}`;

    return `${file.name}-${file.size}-${file.lastModified}-${randomValue}`;
}
