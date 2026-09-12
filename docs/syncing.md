# Syncing

```bash
php artisan docs:sync
```

For each registered project: fetches the repository's git tree, prunes
documents whose file no longer exists remotely (unless
`docs.sync.prune_missing` is `false`), then fetches and upserts any file whose
git blob SHA has changed since the last sync. Unchanged files are skipped
entirely — their content is never re-fetched.

A project's `last_synced_at`/`last_synced_sha` are only updated once its sync
completes without error, so a failed run never marks a project as up to date.

Schedule it, e.g. in `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('docs:sync')->daily();
```

There is no webhook listener — sync is manual or scheduled only.
