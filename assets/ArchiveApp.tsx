import { Archive, Files, ShieldCheck, Sparkles } from 'lucide-react';

import { Button } from '@/components/ui/button';

/**
 * Initial product shell for the Archiver frontend.
 *
 * Later feature PRs will replace the placeholder card with the upload workflow,
 * password controls, and archive download behavior.
 */
export function ArchiveApp() {
    return (
        <main className="mx-auto flex min-h-screen max-w-5xl flex-col px-6 py-10 sm:py-16">
            <section className="grid flex-1 items-center gap-8 lg:grid-cols-[1.1fr_0.9fr]">
                <div className="space-y-6">
                    <div className="inline-flex items-center gap-2 rounded-full border bg-card px-3 py-1 text-sm text-muted-foreground shadow-sm">
                        <Sparkles className="h-4 w-4 text-primary" aria-hidden="true" />
                        Temporary, browser-driven archive creation
                    </div>

                    <div className="space-y-4">
                        <h1 className="text-4xl font-bold tracking-tight sm:text-6xl">
                            Create password-protected ZIP archives.
                        </h1>
                        <p className="max-w-2xl text-lg leading-8 text-muted-foreground">
                            Archiver will let users drop multiple files, choose strong AES-256 or
                            Windows Explorer-compatible encryption, and download a short-lived ZIP
                            file.
                        </p>
                    </div>

                    <div className="flex flex-col gap-3 sm:flex-row">
                        <Button type="button">Upload workflow coming next</Button>
                        <Button type="button" variant="outline">
                            Read the docs
                        </Button>
                    </div>
                </div>

                <div className="rounded-2xl border bg-card p-6 shadow-sm">
                    <div className="mb-6 flex items-center gap-3">
                        <div className="rounded-xl bg-primary/10 p-3 text-primary">
                            <Archive className="h-6 w-6" aria-hidden="true" />
                        </div>
                        <div>
                            <h2 className="font-semibold">PR 1 scaffold</h2>
                            <p className="text-sm text-muted-foreground">
                                Backend, frontend, CI, and docs baseline
                            </p>
                        </div>
                    </div>

                    <ul className="space-y-4 text-sm">
                        <li className="flex gap-3">
                            <Files className="mt-0.5 h-5 w-5 text-primary" aria-hidden="true" />
                            <span>
                                Symfony serves the app shell and future archive API endpoints.
                            </span>
                        </li>
                        <li className="flex gap-3">
                            <ShieldCheck
                                className="mt-0.5 h-5 w-5 text-primary"
                                aria-hidden="true"
                            />
                            <span>
                                React, TypeScript, Vite, Tailwind, and shadcn-compatible components
                                are wired.
                            </span>
                        </li>
                    </ul>
                </div>
            </section>
        </main>
    );
}
