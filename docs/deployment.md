# Deployment notes

Archiver is designed for traditional PHP hosting. The web server document root must point to the Symfony `public/` directory.

## Shared production steps

Configure production environment values through the host, an untracked `.env.local`, or Symfony's optimized environment dump. On a fresh checkout, install Composer dependencies first so Symfony's console is available:

```bash
composer install --no-dev --optimize-autoloader
php bin/console app:env:generate-local --app-env=prod --default-uri=https://your-domain.example
```

The command generates a fresh `APP_SECRET` and refuses to overwrite an existing `.env.local` unless you pass `--force`.

Then install frontend dependencies, build assets, and warm the production cache:

```bash
pnpm install --frozen-lockfile
make build
```

After pulling future changes on a production server, run those install/build steps again so Composer packages, frontend packages, built assets, and Symfony's cache all match the new commit. The shorthand target is:

```bash
git pull origin main
make production-update
```

`make build` defaults to `APP_ENV=prod` and `APP_DEBUG=0`. Override those values only for unusual environment-specific builds:

```bash
make build APP_ENV=staging APP_DEBUG=0
```

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

## Apache with PHP-FPM

- Set `DocumentRoot` to `/path/to/archiver/public`.
- Route non-file requests to `index.php`.
- Configure request body limits at Apache/PHP-FPM/PHP levels.
- If using manual per-directory PHP overrides, PHP-FPM typically reads `.user.ini`. Prefer the host control panel when available.

A reference virtual host is available at [`deploy/apache-fpm/vhost.example.conf`](../deploy/apache-fpm/vhost.example.conf).

## LiteSpeed Enterprise with LSAPI

- Set the virtual host document root to `/path/to/archiver/public`.
- Ensure rewrite rules send non-file requests to `index.php`.
- Use cPanel MultiPHP INI Editor for upload/time/memory values when available.
- If configuring manually, follow the host's LSAPI guidance. LiteSpeed/cPanel commonly applies supported PHP values through `.htaccess` or server-managed LSAPI configuration.
- Restart LSAPI workers if required by the host after changing PHP values.

A reference `.htaccess` snippet is available at [`deploy/litespeed/htaccess-lsapi.example`](../deploy/litespeed/htaccess-lsapi.example).

## Cleanup scheduling

Production deployments should schedule the purge command every 5-10 minutes from the PHP runtime user, such as the cPanel account or `www-data`. Do not use a separate deploy user unless it is also the runtime user that owns `var/tmp/archives` workspaces. Example cron and systemd timer files are available in `deploy/cron.example` and `deploy/systemd/`.

```bash
php bin/console app:temp:purge --env=prod
```

The command removes expired unlocked workspaces and skips active workspaces. The default TTL is controlled by `ARCHIVER_TEMP_TTL_SECONDS`.

## Diagnostics

Temporary `phpinfo()` files are useful while verifying a host, but they should be deleted or access-restricted immediately after diagnostics.
