# Backend architecture

The Symfony backend owns HTTP routing, request validation, archive generation, temporary workspace management, and operational console commands.

## Current entry points

- `App\Controller\HomeController` renders the Twig shell that mounts the React app.

## Planned archive responsibilities

Future archive code should keep interfaces narrow:

- Controllers translate HTTP requests/responses.
- Archive services validate upload metadata, choose encryption mode, and create ZIP files.
- Temporary storage services create private workspaces, delete response files after send, and purge abandoned workspaces.

Uploaded files should remain in PHP's request-managed temporary storage whenever possible. Generated ZIP files should be the only app-managed files, and they should be short-lived.

## Checks

Run backend checks with:

```bash
composer check
```
