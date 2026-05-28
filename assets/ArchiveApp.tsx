import { ArchiveForm } from '@/components/ArchiveForm';

/**
 * Top-level product shell for the Archiver frontend.
 */
export function ArchiveApp() {
    return (
        <main className="mx-auto min-h-screen max-w-6xl px-6 py-10 sm:py-14">
            <section className="mb-8 space-y-4 text-center">
                <div className="mx-auto inline-flex items-center rounded-full border bg-card px-3 py-1 text-sm text-muted-foreground shadow-sm">
                    Temporary, browser-driven archive creation
                </div>
                <div className="space-y-3">
                    <h1 className="text-4xl font-bold tracking-tight sm:text-6xl">
                        Create password-protected ZIP archives.
                    </h1>
                    <p className="mx-auto max-w-3xl text-lg leading-8 text-muted-foreground">
                        Drop files, choose strong AES-256 or Windows Explorer-compatible encryption,
                        and download a short-lived ZIP file.
                    </p>
                </div>
            </section>

            <ArchiveForm />
        </main>
    );
}
