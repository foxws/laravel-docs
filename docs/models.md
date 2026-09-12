# Models

## `Project`

`Foxws\Docs\Domain\Projects\Models\Project`

| Field | Notes |
| --- | --- |
| `slug` | Unique, used for routing/lookup. |
| `title`, `github_repository`, `docs_path`, `branch` | Static metadata, upserted from config on each sync. |
| `seo` | Array cast; optional `title_pattern`/`description` override. |
| `last_synced_at`, `last_synced_sha` | Sync bookkeeping — only set after a successful sync. |

`Project::documents()` — `HasMany<Document>`.

## `Document`

`Foxws\Docs\Domain\Documents\Models\Document`

| Field | Notes |
| --- | --- |
| `project_id` | `belongsTo(Project::class)`. |
| `slug`, `title`, `body` (rendered HTML), `order`, `section` | Content, from front matter + rendered markdown. |
| `source_path`, `blob_sha` | Identify the file in the source repo and detect changes between syncs. |
| `searchable` | Per-document opt-out of Scout indexing, set via front matter. |
| `seo` | Array cast; per-document override. |

## SEO title resolution

`$document->resolveSeoTitle()` resolves in order, stopping at the first
non-null result:

1. `$document->seo['title']`
2. `sprintf($document->project->seo['title_pattern'], $document->title)`
3. `sprintf(config('docs.seo.title_pattern'), $document->title)`
4. `$document->title`
