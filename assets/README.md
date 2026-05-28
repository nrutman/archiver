# Frontend architecture

The frontend is a React and TypeScript app compiled by Vite and mounted from the Symfony Twig shell.

## Entry points

- `assets/main.tsx` mounts React into `#archiver-root`.
- `assets/ArchiveApp.tsx` contains the current product shell. Later PRs will introduce the upload workflow, password controls, and archive download behavior here or in child feature components.
- `assets/styles/app.css` loads Tailwind and the shadcn-compatible design tokens.

## Component conventions

Use small, typed React components and keep browser-only behavior in the frontend. The browser owns the selected `File` objects until the user submits the archive request. Server-side code should receive a single multipart request and should not maintain a file queue between requests.

Shared UI primitives live under `assets/components/ui`. The structure is compatible with shadcn/ui, but generated components should be reviewed before committing so we keep only what the app actually uses.

## Checks

Run frontend checks with:

```bash
pnpm lint
pnpm typecheck
pnpm test
pnpm build
```
