---
section: Getting Started
order: 3
---

# Configuration

`config/docs.php`:

Projects and versions are registered via the `docs:projects:add` /
`docs:versions:add` commands, not config — see
[registering-projects.md](registering-projects.md).

| Key | Description |
| --- | --- |
| `models.project` | The `Project` model class to use. Override to your own subclass — see [models.md](models.md#customizing-the-models). |
| `models.version` | The `Version` model class to use. Override to your own subclass — see [models.md](models.md#customizing-the-models). |
| `models.document` | The `Document` model class to use. Override to your own subclass — see [models.md](models.md#customizing-the-models). |
| `seo.title_pattern` | Package-wide `sprintf`-style fallback for `Document::resolveSeoTitle()`. Defaults to `env('DOCS_SEO_TITLE_PATTERN', '%s — '.env('APP_NAME', 'Laravel'))`. |
| `seo.description` | Package-wide fallback for `Document::resolveSeoDescription()`. Defaults to `env('DOCS_SEO_DESCRIPTION', '')`. |
| `search.enabled` | Whether documents are indexed via Scout at all. Defaults to `env('DOCS_SEARCH_ENABLED', true)`. |
| `search.index_prefix` | Prefix applied to the Scout index name. Defaults to `env('DOCS_SEARCH_PREFIX', 'docs_')`. |
| `sync.prune_missing` | Whether `docs:sync` deletes documents whose source file no longer exists remotely. Defaults to `env('DOCS_PRUNE_MISSING', true)`. |
| `sync.auto_discover_versions` | Whether `docs:sync` checks each project's latest GitHub release and registers it as the default version automatically. Defaults to `env('DOCS_AUTO_DISCOVER_VERSIONS', true)` — see [registering-projects.md](registering-projects.md#automatic-version-discovery). |
| `sync.requires_versions` | Whether `docs:projects:add --sync` skips registering a `latest` version tracking `main` for a GitHub project, so only explicitly registered or auto-discovered release versions are synced. Defaults to `env('DOCS_REQUIRES_VERSIONS', true)` — see [registering-projects.md](registering-projects.md#quick-start-with---sync). |
| `sync.version_name_pattern` | Regex used to derive an auto-discovered version's `name` from its release tag — default `/\d.*/` keeps from the first digit onward, so `v2.0.0`/`version-2.0.0`/`2.0.0` all become `2.0.0`. Falls back to the raw tag if it doesn't match. Set to `null` to always use the raw tag. Defaults to `env('DOCS_VERSION_NAME_PATTERN', '/\d.*/')`. |
| `sync.keep_versions` | How many non-default versions to keep per project (most recently created first) — older ones are deleted on the next sync. The default version itself is never pruned. Set to `0` to keep everything. Defaults to `env('DOCS_KEEP_VERSIONS', 5)`. |
| `sync.prune_chunk_size` | Maximum number of old versions deleted per `docs:sync` run once `keep_versions` is exceeded — caps how much a single run prunes if a project has a large backlog. Defaults to `env('DOCS_PRUNE_CHUNK_SIZE', 50)`. |
| `sync.queue.enabled` | Whether `docs:sync` queues each project's sync instead of running inline by default — see [syncing.md](syncing.md#queued-syncing). Override per run with `--queue`/`--sync`. Defaults to `env('DOCS_SYNC_QUEUED', false)`. |
| `sync.queue.connection` | Queue connection the chained sync jobs are dispatched on. Defaults to `env('DOCS_SYNC_QUEUE_CONNECTION')` — `null` uses the application's default connection. |
| `sync.queue.queue` | Queue name the chained sync jobs are dispatched on. Defaults to `env('DOCS_SYNC_QUEUE')` — `null` uses the application's default queue. |
| `sync.queue.overlap_release_after` | Seconds a duplicate sync of the same project waits before retrying when it can't acquire the overlap lock. Defaults to `env('DOCS_SYNC_QUEUE_OVERLAP_RELEASE_AFTER', 30)`. |
| `sync.queue.overlap_expires_after` | Maximum seconds a project's overlap lock is trusted before being force-expired, e.g. if a worker dies mid-job without releasing it. Defaults to `env('DOCS_SYNC_QUEUE_OVERLAP_EXPIRES_AFTER', 600)`. |
| `github.token` | A GitHub personal access token, used only for the authenticated Trees API call (`env('DOCS_GITHUB_TOKEN')`). Raw content fetches are unauthenticated. |
| `github.retry.times` | How many times to retry a Trees API or raw content request that fails — a ref pushed/tagged moments ago can briefly 404 on either before it propagates. Set to `1` to disable retrying. Defaults to `env('DOCS_GITHUB_RETRY_TIMES', 3)`. |
| `github.retry.sleep_milliseconds` | Milliseconds to wait between retry attempts. Defaults to `env('DOCS_GITHUB_RETRY_SLEEP_MILLISECONDS', 500)`. |
