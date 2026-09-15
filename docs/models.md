---
section: Reference
order: 1
---

# Models

## `Project`

`Foxws\Docs\Models\Project`

| Field | Notes |
| --- | --- |
| `slug` | Unique, used for routing/lookup. |
| `title`, `github_repository`, `docs_path` | Static metadata, set via `docs:projects:add`. |
| `seo` | Array cast; optional `title_pattern`/`description` override. |
| `metadata` | Array cast; synced from the index document's front matter — see [Project metadata](registering-projects.md#project-metadata). |

`Project::versions()` — `HasMany<Version>`.

`Project::documents()` — `HasManyThrough<Document, Version>`, i.e. every
document across every version of this project.

`Project::findOrCreate(string $slug, array $attributes = [])` — finds by
slug, or creates with the given attributes if it doesn't exist yet.

`$project->updateRegistration(array $attributes)` — syncs the registration
fields (`title`, `github_repository`, `docs_path`, `seo`) on an existing
project; other keys are ignored. `docs:projects:add` calls both in sequence,
so re-running it always finds-or-creates then re-syncs the fields.

`Project::eachRegistered(callable $callback)` — iterates every registered
project, using the configured model class. Used by `docs:sync`.

`$project->defaultVersion()` — the version to read from absent a more
specific choice: the one marked `is_default`, or the first registered if
none is. Reads `$project->versions` in memory, so eager-load it
(`Project::with('versions')`) to avoid a query per project.

`$project->versionOrDefault($name)` — the version matching `$name` (e.g.
from a `?version=` query string), falling back to `defaultVersion()` when
`$name` is `null` or doesn't match any registered version.

`$project->indexDocument($documents)` — given an already-loaded collection
of this project's documents (any version), returns the one at
`indexDocumentPath()` — `index.md`, or `about.md` for a project with no
index of its own.

## `Version`

`Foxws\Docs\Models\Version`

| Field | Notes |
| --- | --- |
| `project_id` | `belongsTo(Project::class)`. |
| `name` | e.g. `1.0.0`, `2.0.0`, `latest`. Unique per project. |
| `ref` | Git tag or branch this version syncs from, e.g. `v2.0.0`. |
| `is_default` | Which version a docs UI should show initially. |
| `last_synced_at`, `last_synced_sha` | Sync bookkeeping, independent per version — only set after a successful sync. |

`Version::documents()` — `HasMany<Document>`.

`$version->orderedDocuments()` — this version's documents in a stable
reading order (`order`, then `id` to break ties). `order` only ranks a
document among others in its own section — two documents in different
sections both left at the default (or both explicitly set to the same
value) tie, and without the `id` tiebreaker the database is free to
resolve that however it likes.

`Version::findOrCreate(int $projectId, string $name, array $attributes = [])`
— finds by project + name, or creates with the given attributes.

`$version->updateRegistration(array $attributes)` — syncs `ref` on an
existing version; sync bookkeeping is never touched.

`$version->markAsDefault()` — marks this version as the project's default,
unmarking whichever version was default before. This is the only way
`is_default` changes; `updateRegistration()` doesn't touch it.

## `Document`

`Foxws\Docs\Models\Document`

| Field | Notes |
| --- | --- |
| `version_id` | `belongsTo(Version::class)`. |
| `slug`, `title`, `body` (rendered HTML), `order`, `section` | Content, from front matter + rendered markdown. |
| `source_path`, `blob_sha` | Identify the file in the source repo and detect changes between syncs. |
| `searchable` | Per-document opt-out of Scout indexing, set via front matter. |
| `seo` | Array cast; per-document override. |

Reach the owning project via `$document->version->project` — there's no
direct `project` relation, since a document only ever belongs to one version.

`Document::syncSearchIndex()` — re-indexes all searchable documents, if
`docs.search.enabled` is true. Used by `docs:sync`.

## Customizing the models

Override `config('docs.models.project')` / `config('docs.models.version')` /
`config('docs.models.document')` with your own subclasses, e.g.:

```php
// config/docs.php
'models' => [
    'project' => App\Models\Project::class,
    'version' => App\Models\Version::class,
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

Everywhere the package resolves these models internally — relations,
`docs:projects:add`, `docs:versions:add`, `docs:sync` — it goes through
`Project::modelClass()` / `Version::modelClass()` / `Document::modelClass()`
rather than the hardcoded base class, so your subclass is used consistently.
All three base models pin their `$table` and their relations' foreign keys
explicitly, so a subclass with a different class name still resolves to the
right table/column.

## SEO title resolution

`$document->resolveSeoTitle()` resolves in order, stopping at the first
non-null result:

1. `$document->seo['title']`
2. `sprintf($document->version->project->seo['title_pattern'], $document->title)`
3. `sprintf(config('docs.seo.title_pattern'), $document->title)`
4. `$document->title`
