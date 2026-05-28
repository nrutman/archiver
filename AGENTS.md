# Agent instructions

## Read first

Before changing files in a tree, read all `README.md` and `CONTRIBUTING.md` files in that tree and its parent directories. Treat them as local architecture and workflow instructions.

## Development cycle

1. Identify a clear unit of work.
2. Create a branch for the unit of work. Never commit directly to `main`.
3. Do development.
4. Run 2-3 cycles of the `review-pr-with-sub-agents` skill before asking for human review. Be very careful about scope creep: only address findings that are relevant to the current unit of work, and prioritize p0-p2 items.
5. Make sure CI checks pass.
6. Ask a human for review, highlighting the core changes and offering to squash-merge when they are ready.

All PRs should be squash-merged into `main`.

## Current architecture

- Symfony 7.4 / PHP 8.3+ backend.
- React / TypeScript / Vite frontend.
- Tailwind CSS with shadcn-compatible UI primitives.
- Built frontend assets under `public/build` are committed so production hosts do not need Node.js.
- No database is planned for the core flow.
- Files are temporary. Uploaded files should stay in PHP request temp storage where possible; generated ZIP files should live in short-lived app workspaces and be cleaned up aggressively.

## Commands

```bash
make setup          # install PHP and frontend dependencies
make dev-backend    # run Symfony through PHP's local server
make dev-frontend   # run Vite
make check          # run PHP checks and verify committed public/build files without requiring the frontend toolchain
composer check      # PHP validation, linting, formatting, static analysis, tests
make frontend-check # frontend lint, formatting, type-check, tests, build, and committed asset verification; requires the frontend toolchain
make check-built-assets # verify public/build matches the current frontend build
make build          # rebuild frontend assets and warm Symfony cache; use only on machines with the frontend toolchain
make production-update    # production update path using committed public/build assets; does not require the frontend toolchain
make production-update-with-node    # production-style update that also installs/rebuilds frontend assets locally
php bin/console app:doctor    # verify runtime extensions, ZIP support, PHP limits, and temp storage
php bin/console app:env:generate-local --app-env=prod    # generate an untracked .env.local with a fresh APP_SECRET
```

## Testing guidance

Whenever you do a unit of work, review the test suite and ask:

1. Are there any high-value test cases I should write?
2. Are there any ways the tests can be consolidated or simplified?
3. Are there any low-value tests I can remove?

High-value tests for this app include upload validation, password generation rules, ZIP encryption mode selection, safe archive entry naming, download behavior, and temporary-file cleanup.

## Deployment notes

Deployment targets include Apache with PHP-FPM and LiteSpeed Enterprise with LSAPI. Prefer cPanel MultiPHP INI Editor for domain-level PHP upload/time/memory values because it writes the correct handler-specific configuration for both platforms.

Do not leave public `phpinfo()` files deployed after diagnostics.
