import { ArchiveForm } from '@/components/ArchiveForm';

/**
 * Top-level product shell for the Archiver frontend.
 */
export function ArchiveApp() {
    return (
        <main className="mx-auto min-h-screen max-w-6xl px-6 py-10 sm:py-14">
            <section className="mb-8 space-y-3 text-center">
                <h1 className="text-4xl font-bold tracking-tight sm:text-6xl">
                    Create password-protected ZIP archives.
                </h1>
                <p className="mx-auto max-w-3xl text-lg leading-8 text-muted-foreground">
                    Drop files, choose a password, and generate a ZIP file!
                </p>
            </section>

            <ArchiveForm />
        </main>
    );
}
