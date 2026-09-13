# Registering projects

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
| `--branch` | `main` | Branch to sync from. |
| `--seo-title-pattern` | — | `sprintf`-style pattern, e.g. `"%s — Laravel Podman — Foxws"`. |
| `--seo-description` | — | Fallback SEO description for this project's documents. |

The command matches on `slug` — running it again with the same slug updates
that project's title/repository/docs_path/branch/seo without touching its
sync bookkeeping (`last_synced_at`/`last_synced_sha`). This is deliberately a
command rather than a config array: with dozens of `foxws/*` packages
registered, a literal PHP array in `config/docs.php` gets unwieldy fast,
whereas a one-off command per project doesn't.

Once registered, run `docs:sync` (see [syncing.md](syncing.md)) to pull the
project's documentation — `docs:sync` operates on every registered project,
i.e. every row in the `projects` table.

## Per-document front matter

Each `.md` file under a project's `docs_path` may declare:

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
