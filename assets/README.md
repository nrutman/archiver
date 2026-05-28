# Frontend architecture

The frontend is a React and TypeScript app compiled by Vite and mounted from the Symfony Twig shell. It owns selected browser `File` objects until the user submits one multipart archive request.

## Entry points

- `assets/main.tsx` mounts React into `#archiver-root`.
- `assets/ArchiveApp.tsx` contains the current product shell and renders the archive workflow.
- `assets/components/ArchiveForm.tsx` owns selected files, password settings, API submission, and download triggering.
- `assets/styles/app.css` loads Tailwind and the shadcn-compatible design tokens.

## Component conventions

Use small, typed React components and keep browser-only behavior in the frontend. The browser owns the selected `File` objects until the user submits the archive request. Server-side code should receive a single multipart request and should not maintain a file queue between requests.

Shared UI primitives live under `assets/components/ui`. The structure is compatible with shadcn/ui, but generated components should be reviewed before committing so we keep only what the app actually uses.

## Checks

Run frontend checks and verify committed build assets with:

```bash
make frontend-check
```
