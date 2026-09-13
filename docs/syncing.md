# Syncing

```bash
php artisan docs:sync
```

For every registered version (see [registering-projects.md](registering-projects.md)):
fetches the repository's git tree at that version's `ref`, prunes documents
whose file no longer exists remotely (unless `docs.sync.prune_missing` is
`false`), then fetches and upserts any file whose git blob SHA has changed
since the last sync. Unchanged files are skipped entirely — their content is
never re-fetched.

A version's `last_synced_at`/`last_synced_sha` are only updated once its sync
completes without error, so a failed run never marks a version as up to
date. Each version of a project syncs independently, so v1.0.0 and v2.0.0
can be at different commits, synced at different times.

Schedule it, e.g. in `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('docs:sync')->daily();
```

There is no webhook listener — sync is manual or scheduled only.
