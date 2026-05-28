# Deployment notes

Archiver is designed for traditional PHP hosting. The web server document root must point to the Symfony `public/` directory.

## Shared production steps

Configure production environment values through the host, an untracked `.env.local`, or Symfony's optimized environment dump. On a fresh checkout, install Composer dependencies first so Symfony's console is available:

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

`make production-update` intentionally does not run frontend package-manager commands. If you are on a development or CI machine and want to rebuild assets directly, use:

```bash
make production-update-with-node
```

CI verifies that committed `public/build` assets match the frontend source after rebuilding them. Same-repository PR branches can also have CI commit built asset changes automatically when the `BUILD_ASSETS_COMMIT_TOKEN` repository secret is configured. Use a fine-grained token scoped to this repository's contents and do not allow it to bypass protected `main`; otherwise rebuild assets locally and commit `public/build` with the PR.

The web runtime must receive the same `APP_ENV=prod`, `APP_DEBUG=0`, and production `APP_SECRET` values used during cache warmup. The PHP runtime user must be able to write to Symfony runtime directories under `var/`, including the archive workspace root `var/tmp/archives/`.

After deployment, run the doctor command as the same OS user that serves PHP or owns the runtime temp directory:

```bash
sudo -u www-data php bin/console app:doctor --env=prod
```

On cPanel/shared hosting, run the command as the cPanel account/PHP runtime user instead of `root`. The doctor command verifies required PHP extensions, encrypted ZIP creation, PHP upload limits, and temporary storage permissions.

## PHP values

For cPanel-managed Apache PHP-FPM and LiteSpeed Enterprise LSAPI hosts, use **cPanel → Software → MultiPHP INI Editor** as the standard domain-level configuration path. It writes the handler-specific files/directives that the active platform understands.

Recommended values:

```ini
upload_max_filesize = 100M
post_max_size = 220M
max_file_uploads = 30
max_input_time = 300
max_execution_time = 300
memory_limit = 512M
```

Application validation will enforce the product limits independently. PHP and web-server limits must be high enough for the request to reach Symfony.

## Apache and LiteSpeed routing

- Set the web document root to `/path/to/archiver/public`.
- Ensure the host honors the committed [`public/.htaccess`](../public/.htaccess). For Apache virtual hosts, this usually means enabling `AllowOverride` for the `public/` directory. For shared hosting, this is often already enabled.
- The shared `.htaccess` routes `/` and non-file URLs through `index.php`, serves committed `public/build` assets directly, and blocks dotfiles except `.well-known`.
- Configure request body limits at the server/PHP handler/PHP levels.
- Apache PHP-FPM does not usually read `php_value` directives from `.htaccess`; use MultiPHP INI Editor or `.user.ini` for PHP upload/time/memory values.
- LiteSpeed LSAPI can apply the guarded `php_value` directives in `public/.htaccess` when manual LSAPI configuration is needed. Restart LSAPI workers if required by the host after changing PHP values.

## Cleanup scheduling

Production deployments should schedule the purge command every 5-10 minutes from the PHP runtime user, such as the cPanel account or `www-data`. Do not use a separate deploy user unless it is also the runtime user that owns `var/tmp/archives` workspaces. Example cron and systemd timer files are available in `deploy/cron.example` and `deploy/systemd/`.

```bash
php bin/console app:temp:purge --env=prod
```

The command removes expired unlocked workspaces and skips active workspaces. The default TTL is controlled by `ARCHIVER_TEMP_TTL_SECONDS`.

## Diagnostics

Temporary `phpinfo()` files are useful while verifying a host, but they should be deleted or access-restricted immediately after diagnostics.
