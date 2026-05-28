# Deployment notes

Archiver is designed for traditional PHP hosting. The web server document root must point to the Symfony `public/` directory.

## Shared production steps

Configure production environment values through the host, an untracked `.env.local`, or Symfony's optimized environment dump:

```bash
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=<generate-a-unique-secret>
```

Then install dependencies, build assets, and warm the production cache:

```bash
composer install --no-dev --optimize-autoloader
pnpm install --frozen-lockfile
pnpm build
APP_ENV=prod APP_DEBUG=0 php bin/console cache:clear
```

The web runtime must receive the same `APP_ENV=prod`, `APP_DEBUG=0`, and production `APP_SECRET` values used during cache warmup. The PHP runtime user must be able to write to Symfony runtime directories under `var/`, including the planned archive workspace root `var/tmp/archives/`.

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

The archive backend PR will add a command similar to:

```bash
php bin/console app:temp:purge --env=prod
```

Production deployments should schedule that command every 5-10 minutes once it exists. The command should remove expired unlocked workspaces and skip active workspaces.

## Diagnostics

Temporary `phpinfo()` files are useful while verifying a host, but they should be deleted or access-restricted immediately after diagnostics.
