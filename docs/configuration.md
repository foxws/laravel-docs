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
| `seo.title_pattern` | Package-wide `sprintf`-style fallback for `Document::resolveSeoTitle()`, e.g. `'%s — Foxws'`. |
| `seo.description` | Package-wide fallback SEO description. |
| `search.enabled` | Whether documents are indexed via Scout at all. Defaults to `env('DOCS_SEARCH_ENABLED', true)`. |
| `search.index_prefix` | Prefix applied to the Scout index name. Defaults to `env('DOCS_SEARCH_PREFIX', 'foxws_')`. |
| `sync.prune_missing` | Whether `docs:sync` deletes documents whose source file no longer exists remotely. Defaults to `env('DOCS_PRUNE_MISSING', true)`. |
| `sync.auto_discover_versions` | Whether `docs:sync` checks each project's latest GitHub release and registers it as the default version automatically. Defaults to `env('DOCS_AUTO_DISCOVER_VERSIONS', true)` — see [registering-projects.md](registering-projects.md#automatic-version-discovery). |
| `sync.keep_versions` | How many non-default versions to keep per project (most recently created first) — older ones are deleted on the next sync. The default version itself is never pruned. Set to `0` to keep everything. Defaults to `env('DOCS_KEEP_VERSIONS', 5)`. |
| `sync.prune_chunk_size` | Maximum number of old versions deleted per `docs:sync` run once `keep_versions` is exceeded — caps how much a single run prunes if a project has a large backlog. Defaults to `env('DOCS_PRUNE_CHUNK_SIZE', 50)`. |
| `github.token` | A GitHub personal access token, used only for the authenticated Trees API call (`env('DOCS_GITHUB_TOKEN')`). Raw content fetches are unauthenticated. |
