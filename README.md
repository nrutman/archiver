# Archiver

Archiver is a small Symfony and React application for creating short-lived ZIP archives from files selected in the browser. Users can drop multiple files, choose password protection, and download a generated archive without keeping uploads or ZIP files longer than necessary.

## Architecture

- **Backend:** Symfony 7.4 on PHP 8.3+.
- **Frontend:** React, TypeScript, Vite, Tailwind CSS, and shadcn-compatible UI primitives.
- **Persistence:** no database. Uploads are handled in a single request, and generated archives will be written to app-owned temporary workspaces before being deleted after download.
- **Deployment targets:** Apache with PHP-FPM and LiteSpeed Enterprise with LSAPI.

The current app includes the browser upload workflow, archive creation API, and temporary workspace cleanup.

## Requirements

Production servers only need the PHP runtime because built frontend assets are committed under `public/build`:

- PHP 8.3+
- Composer 2+
- PHP extensions: `ctype`, `fileinfo`, `iconv`, `intl`, `mbstring`, `openssl`, and `zip`

Development machines and CI also need Node.js 22+ and pnpm 10+ to run, test, and rebuild the frontend.

## Local setup

```bash
make setup
```

Run the backend and frontend dev servers in separate terminals:

```bash
make dev-backend
make dev-frontend
```

The backend runs at `http://127.0.0.1:8000` and Vite runs at `http://127.0.0.1:5173`.

## Checks

Run all checks with:

```bash
make check
```

Useful targeted commands:

```bash
composer check
pnpm lint
pnpm format:check
pnpm typecheck
pnpm test
pnpm build
php bin/console app:doctor
```

## Production build

Before serving the app, configure production environment values through the host, an untracked `.env.local`, or Symfony's optimized environment dump. On a fresh checkout, install Composer dependencies first so Symfony's console is available:

```bash
composer install --no-dev --optimize-autoloader
php bin/console app:env:generate-local --app-env=prod --default-uri=https://your-domain.example
```

The command generates a fresh `APP_SECRET` and refuses to overwrite an existing `.env.local` unless you pass `--force`.

Then use the committed `public/build` assets from git and warm the PHP side:

```bash
make production-update
```

After pulling future changes on production, run the same no-Node update command:

```bash
git pull origin main
make production-update
```

`make production-update` intentionally does not run `pnpm`. If you are on a machine with Node.js and want to rebuild assets directly, use:

```bash
make production-update-with-node
```

`make check` and CI verify that committed `public/build` assets match the frontend source. Same-repository PR branches can also have CI commit built asset changes automatically when the `BUILT_ASSETS_COMMIT_TOKEN` repository secret is configured. Use a fine-grained token scoped to this repository's contents and do not allow it to bypass protected `main`; otherwise run `pnpm build` locally and commit `public/build` with the PR.

On Node-capable machines, `make build` rebuilds frontend assets and clears the Symfony cache with `APP_ENV=prod` and `APP_DEBUG=0` by default. Override those values only for unusual environment-specific builds:

```bash
make build APP_ENV=staging APP_DEBUG=0
```

Point the web server document root at `public/` and make sure the web runtime also receives `APP_ENV=prod`, `APP_DEBUG=0`, and the production `APP_SECRET`.

## Upload/runtime settings

Use the hosting platform's domain-level PHP configuration tooling to set request limits high enough for the app. For cPanel-managed Apache PHP-FPM and LiteSpeed LSAPI hosts, prefer **cPanel → Software → MultiPHP INI Editor**.

Recommended starting values:

```ini
upload_max_filesize = 100M
post_max_size = 220M
max_file_uploads = 30
max_input_time = 300
max_execution_time = 300
memory_limit = 512M
```

The application will still enforce its own limits so users receive clear validation errors. The PHP/web-server settings only make sure large requests can reach Symfony.

See [`docs/deployment.md`](docs/deployment.md) for Apache PHP-FPM and LiteSpeed LSAPI notes.

## Temporary files

All uploaded files and generated archives are temporary by design. The archive backend uses a layered cleanup strategy:

1. avoids copying uploaded files outside PHP's request temp storage unless needed,
2. creates generated ZIP files in private app workspaces under `var/tmp/archives/`,
3. deletes the workspace after the response is streamed,
4. uses `try`/`finally` cleanup around failures,
5. provides a scheduled purge command for orphaned workspaces.

Run the doctor command after deployment as the same OS user that serves PHP or owns the runtime temp directory. It verifies PHP extensions, encrypted ZIP creation, PHP upload limits, and temporary storage permissions:

```bash
sudo -u www-data php bin/console app:doctor --env=prod
```

On cPanel/shared hosting, run the command as the cPanel account/PHP runtime user instead of `root`.

Run the purge command every 5-10 minutes in production:

```bash
php bin/console app:temp:purge --env=prod
```

Example cron and systemd timer units are available under `deploy/`.
