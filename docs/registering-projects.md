# Registering projects and versions

Register a project with `docs:projects:add`:

```bash
php artisan docs:projects:add laravel-podman "Laravel Podman" foxws/laravel-podman \
    --seo-title-pattern="%s — Laravel Podman — Foxws" \
    --seo-description="Podman Quadlet tooling for Laravel."
```

| Argument/option | Default | Notes |
| --- | --- | --- |
| `slug` | — | Unique identifier, used in routing. |
| `title` | — | Display title. |
| `github_repository` | — | `owner/repo`. |
| `--docs-path` | `docs` | Path to the docs folder within the repository. |
| `--seo-title-pattern` | — | `sprintf`-style pattern, e.g. `"%s — Laravel Podman — Foxws"`. |
| `--seo-description` | — | Fallback SEO description for this project's documents. |

The command matches on `slug` — running it again with the same slug updates
that project's title/repository/docs_path/seo.

A project alone has nothing to sync — register at least one version:

```bash
php artisan docs:versions:add laravel-podman 1.0.0 v1.0.0
php artisan docs:versions:add laravel-podman 2.0.0 v2.0.0 --default
```

| Argument/option | Default | Notes |
| --- | --- | --- |
| `project` | — | The project's slug. |
| `name` | — | Version name, e.g. `1.0.0` or `latest`. |
| `ref` | — | Git tag or branch to sync this version from, e.g. `v1.0.0` or `main`. |
| `--default` | off | Marks this version as the default one (e.g. for a docs UI's initial view). |

The command matches on `name` within the project — running it again updates
that version's `ref`/`is_default`. Each version syncs independently from its
own `ref` and tracks its own `last_synced_at`/`last_synced_sha`.

Once at least one version is registered, run `docs:sync` (see
[syncing.md](syncing.md)) to pull documentation for every registered version.

## Per-document front matter

Each `.md` file under a project's `docs_path` (as it exists at a version's
`ref`) may declare:

```yaml
---
title: Installation
order: 1
section: Getting Started
searchable: true
seo:
  description: "Install and configure the thing."
---
```

All fields are optional. `slug`/`title` fall back to the file's name when
omitted; `order` defaults to `0`; `searchable` defaults to `true`.
