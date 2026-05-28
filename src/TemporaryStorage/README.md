# Temporary storage

Temporary storage owns the short-lived workspaces used while generating and streaming ZIP files.

## Lifecycle

1. `TemporaryWorkspaceFactory` creates a private workspace under `var/tmp/archives/` and takes an exclusive lock on `.lock`.
2. Archive code writes the generated ZIP inside that workspace.
3. The controller streams the ZIP response and closes the workspace in a `finally` block after streaming.
4. `TemporaryWorkspace::close()` recursively removes the workspace and releases the lock.
5. `TemporaryWorkspacePurger` removes expired unlocked workspaces left behind by crashes or killed workers.

## Purging

Run the purge command in production on a schedule once the app is deployed:

```bash
php bin/console app:temp:purge --env=prod
```

A 5-10 minute cron interval is appropriate. The command skips fresh workspaces and workspaces whose lock is still held by an active request.
