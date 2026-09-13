---
name: docs-development
description: >
  Configure and apply the foxws/laravel-docs package in Laravel applications.
license: MIT
metadata:
  author: francoism90
---

# Laravel Docs

Use this skill when a Laravel application needs to integrate the `foxws/laravel-docs` package.

## Primary Goal

- pull a package's `docs/*.md` folder from GitHub into queryable `Project`/`Document` Eloquent models, and let the consuming app render them

## Workflow

### 1. Install and configure

- `composer require foxws/laravel-docs`
- Migrations for `projects`/`documents` run automatically — no `vendor:publish` step is required
- Optionally publish `config/docs.php` (`--tag=docs-config`) to set `seo`, `search`, `sync`, and `github.token` defaults

### 2. Register a project

Registration is a command, not a config array (it doesn't scale as a literal array once many packages are registered):

```bash
php artisan docs:projects:add {slug} {title} {github_repository} \
    --docs-path=docs --branch=main \
    --seo-title-pattern="%s — Foxws" --seo-description="..."
```

Re-running the command with the same `slug` updates that project's static fields without touching its sync bookkeeping (`last_synced_at`/`last_synced_sha`).

### 3. Sync documentation

```bash
php artisan docs:sync
```

Schedule it (e.g. `Schedule::command('docs:sync')->daily()` in `routes/console.php`) rather than relying on a webhook — there is none in v1.

### 4. Query and render

```php
use Foxws\Docs\Models\Project;

$project = Project::where('slug', $slug)->firstOrFail();
$document = $project->documents()->where('slug', $slug ?? 'index')->firstOrFail();

$document->resolveSeoTitle();
```

`Document::body` is already-rendered HTML (from markdown). `Document` uses Scout's `Searchable` trait; indexing is gated by `config('docs.search.enabled')` and each document's `searchable` flag (set via front matter) — it stays fully inert with no configured Scout driver when search is disabled.

## Rules, References, and Templates

Read before executing:

- `docs/index.md` in this package for the full guide (configuration, registering projects, syncing, models, search)

## Examples

- A blog/docs site pulling each of its own packages' `docs/` folders into one searchable documentation index, with the app's own Inertia/Vue pages rendering `Document::body` and using `resolveSeoTitle()` for `<title>`.

## Anti-patterns

- Do not register projects via a config array — use `docs:projects:add`.
- Do not build a "Page"/site-wide content type on top of this package's current models — that's an intentionally separate, not-yet-built concern.
- Do not disable Scout by removing the dependency — it's a hard dependency by design (see `docs/search.md`); disable indexing via `DOCS_SEARCH_ENABLED=false` instead.
