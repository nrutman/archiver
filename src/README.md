# Backend architecture

The Symfony backend owns HTTP routing, request validation, archive generation, temporary workspace management, and operational console commands.

## Current entry points

- `App\Controller\HomeController` renders the Twig shell that mounts the React app.
- `app:env:generate-local` generates an untracked `.env.local` with a fresh `APP_SECRET` and runtime defaults.

## Planned archive responsibilities

Future archive code should keep interfaces narrow:

- Controllers translate HTTP requests/responses and enforce the per-IP archive rate limit before doing any work.
- Archive services validate upload metadata, choose encryption mode, and create ZIP files. The same services are reused by `app:archive:create` so the console command and the HTTP endpoint stay behaviorally identical.
- Temporary storage services create private workspaces, delete response files after send, and purge abandoned workspaces.

Uploaded files should remain in PHP's request-managed temporary storage whenever possible. Generated ZIP files should be the only app-managed files, and they should be short-lived.

## Checks

Run backend checks with:

```bash
composer check
```
