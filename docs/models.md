# Models

## `Project`

`Foxws\Docs\Models\Project`

| Field | Notes |
| --- | --- |
| `slug` | Unique, used for routing/lookup. |
| `title`, `github_repository`, `docs_path`, `branch` | Static metadata, set via `docs:projects:add`. |
| `seo` | Array cast; optional `title_pattern`/`description` override. |
| `last_synced_at`, `last_synced_sha` | Sync bookkeeping — only set after a successful sync. |

`Project::documents()` — `HasMany<Document>`.

`Project::findOrCreate(string $slug, array $attributes = [])` — finds by
slug, or creates with the given attributes if it doesn't exist yet.

`$project->updateRegistration(array $attributes)` — syncs the registration
fields (`title`, `github_repository`, `docs_path`, `branch`, `seo`) on an
existing project; other keys are ignored, and sync bookkeeping is never
touched. `docs:projects:add` calls both in sequence, so re-running it always
finds-or-creates then re-syncs the fields.

`Project::eachRegistered(callable $callback)` — iterates every registered
project, using the configured model class. Used by `docs:sync`.

## `Document`

`Foxws\Docs\Models\Document`

| Field | Notes |
| --- | --- |
| `project_id` | `belongsTo(Project::class)`. |
| `slug`, `title`, `body` (rendered HTML), `order`, `section` | Content, from front matter + rendered markdown. |
| `source_path`, `blob_sha` | Identify the file in the source repo and detect changes between syncs. |
| `searchable` | Per-document opt-out of Scout indexing, set via front matter. |
| `seo` | Array cast; per-document override. |

`Document::syncSearchIndex()` — re-indexes all searchable documents, if
`docs.search.enabled` is true. Used by `docs:sync`.

## Customizing the models

Override `config('docs.models.project')` / `config('docs.models.document')`
with your own subclasses, e.g.:

```php
// config/docs.php
'models' => [
    'project' => App\Models\Project::class,
    'document' => App\Models\Document::class,
],
```

```php
namespace App\Models;

class Project extends \Foxws\Docs\Models\Project
{
    public function isFeatured(): bool
    {
        return in_array($this->slug, ['laravel-podman', 'laravel-docs']);
    }
}
```

Everywhere the package resolves `Project`/`Document` internally — relations,
`docs:projects:add`, `docs:sync` — it goes through `Project::modelClass()`
/ `Document::modelClass()` rather than the hardcoded base class, so
your subclass is used consistently. Both base models pin their `$table` and
the `documents` relation's foreign key explicitly, so a subclass with a
different class name still resolves to the `projects`/`documents` tables and
the `project_id` column correctly.

## SEO title resolution

`$document->resolveSeoTitle()` resolves in order, stopping at the first
non-null result:

1. `$document->seo['title']`
2. `sprintf($document->project->seo['title_pattern'], $document->title)`
3. `sprintf(config('docs.seo.title_pattern'), $document->title)`
4. `$document->title`
