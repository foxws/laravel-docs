---
section: Getting Started
order: 4
---

# Syncing

```bash
php artisan docs:sync
```

For each registered project, before syncing: if `docs.sync.auto_discover_versions`
is enabled (it is by default), checks the project's latest GitHub release and
registers/updates it as the default version — see
[registering-projects.md](registering-projects.md#automatic-version-discovery).
Then, if `docs.sync.keep_versions` is set above `0`, prunes old non-default
versions beyond that count (see [registering-projects.md](registering-projects.md#retention)).

Then, for every registered version: fetches the repository's git tree at
that version's `ref`, prunes documents whose file no longer exists remotely
(unless `docs.sync.prune_missing` is `false`), then fetches and upserts any
file whose git blob SHA has changed since the last sync. Unchanged files are
skipped entirely — their content is never re-fetched.

A version's `last_synced_at`/`last_synced_sha` are only updated once its sync
completes without error, so a failed run never marks a version as up to
date. Each version of a project syncs independently, so v1.0.0 and v2.0.0
can be at different commits, synced at different times.

Pass `--project={slug}` to sync a single project instead of every registered
one — useful right after registering or updating just that project:

```bash
php artisan docs:sync --project=laravel-podman
```

Schedule it, e.g. in `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('docs:sync')->daily();
```

There is no webhook listener — sync is manual or scheduled only.

## Queued syncing

By default `docs:sync` runs inline and blocks until every project has
synced. Pass `--queue` to dispatch it instead, or set
`docs.sync.queue.enabled` to `true` to make queuing the default for every
run (use `--sync` on a given run to force an inline run regardless):

```bash
php artisan docs:sync --queue
```

This chains one `SyncProjectDocuments` job per project, followed by a job
that rebuilds the search index once every project has synced. Chained jobs
run one at a time, in that order — never in parallel — regardless of how
many queue workers are running, so projects never compete for the same
GitHub API rate limit.

Each project's job is protected against overlapping with another sync of
the *same* project (e.g. an overlapping schedule run, or `docs:sync
--queue` invoked twice before the first finishes) — a duplicate is released
back onto the queue to retry instead of running alongside the first. Tune
this via `docs.sync.queue.overlap_release_after` /
`docs.sync.queue.overlap_expires_after`, and the connection/queue name jobs
are dispatched on via `docs.sync.queue.connection` / `docs.sync.queue.queue`
— see [configuration.md](configuration.md).
