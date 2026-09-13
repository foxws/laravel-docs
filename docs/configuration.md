# Configuration

`config/docs.php`:

Projects are registered via the `docs:projects:add` command, not config —
see [registering-projects.md](registering-projects.md).

| Key | Description |
| --- | --- |
| `models.project` | The `Project` model class to use. Override to your own subclass — see [models.md](models.md#customizing-the-models). |
| `models.document` | The `Document` model class to use. Override to your own subclass — see [models.md](models.md#customizing-the-models). |
| `seo.title_pattern` | Package-wide `sprintf`-style fallback for `Document::resolveSeoTitle()`, e.g. `'%s — Foxws'`. |
| `seo.description` | Package-wide fallback SEO description. |
| `search.enabled` | Whether documents are indexed via Scout at all. Defaults to `env('DOCS_SEARCH_ENABLED', true)`. |
| `search.index_prefix` | Prefix applied to the Scout index name. Defaults to `env('DOCS_SEARCH_PREFIX', 'foxws_')`. |
| `sync.prune_missing` | Whether `docs:sync` deletes documents whose source file no longer exists remotely. Defaults to `env('DOCS_PRUNE_MISSING', true)`. |
| `github.token` | A GitHub personal access token, used only for the authenticated Trees API call (`env('DOCS_GITHUB_TOKEN')`). Raw content fetches are unauthenticated. |
