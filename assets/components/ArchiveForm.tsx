import { Download } from 'lucide-react';
import { useMemo, useState } from 'react';

import { FileDropzone } from '@/components/FileDropzone';
import { FileList, type SelectedFile } from '@/components/FileList';
import { PasswordControls } from '@/components/PasswordControls';
import { Button } from '@/components/ui/button';
import { createArchive, downloadArchive, type EncryptionMode } from '@/lib/archiveApi';
import { generatePassword } from '@/lib/passwordGenerator';

/**
 * Complete browser-side archive creation workflow.
 */
export function ArchiveForm() {
    const [files, setFiles] = useState<SelectedFile[]>([]);
    const [passwordEnabled, setPasswordEnabled] = useState(true);
    const [password, setPassword] = useState(() => generatePassword());
    const [encryptionMode, setEncryptionMode] = useState<EncryptionMode>('aes256');
    const [error, setError] = useState<string | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const canSubmit =
        files.length > 0 && (!passwordEnabled || password.trim().length > 0) && !isSubmitting;
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

        try {
            const archive = await createArchive({
                files: selectedFiles,
                passwordEnabled,
                password,
                encryptionMode,
            });
            downloadArchive(archive);
        } catch (caughtError) {
            setError(
                caughtError instanceof Error
                    ? caughtError.message
                    : 'Could not create the archive. Please try again.',
            );
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

                <Button
                    className="w-full"
                    disabled={!canSubmit}
                    onClick={() => void submitArchive()}
                    size="lg"
                    type="button"
                >
                    <Download className="mr-2 h-4 w-4" aria-hidden="true" />
                    {isSubmitting ? 'Creating archive…' : 'Generate and download ZIP'}
                </Button>
            </aside>
        </div>
    );
}

function createSelectedFileId(file: File): string {
    const randomValue =
        typeof globalThis.crypto?.randomUUID === 'function'
            ? globalThis.crypto.randomUUID()
            : `${Date.now()}-${Math.random().toString(36).slice(2)}`;

    return `${file.name}-${file.size}-${file.lastModified}-${randomValue}`;
}
