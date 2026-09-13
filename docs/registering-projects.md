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
that version's `ref`. Passing `--default` marks it as the project's default,
unmarking whichever version was default before (only one version per
project can be default at a time). Omitting `--default` on a re-run leaves
the current default unchanged. Each version syncs independently from its
own `ref` and tracks its own `last_synced_at`/`last_synced_sha`.

Once at least one version is registered, run `docs:sync` (see
[syncing.md](syncing.md)) to pull documentation for every registered version.

## Automatic version discovery

You don't have to run `docs:versions:add` at all if you're happy always
tracking whatever GitHub considers the latest release: with
`docs.sync.auto_discover_versions` enabled (the default), `docs:sync` checks
each project's `GET /repos/{owner}/{repo}/releases/latest` before syncing
and registers that release as the default version automatically — creating
it if it doesn't exist yet, or just updating its `ref` if it does. A project
registered via `docs:projects:add` alone, with no `docs:versions:add` ever
run, will pick up its first version this way on the next `docs:sync`.

The version's `name` is the release's tag with a leading `v` stripped (e.g.
tag `v2.0.0` becomes name `2.0.0`; tag `2.0.0` stays `2.0.0`). Repositories
with no GitHub releases are skipped silently — nothing is registered for
them until you either publish a release or register a version manually.

Set `DOCS_AUTO_DISCOVER_VERSIONS=false` to turn this off and manage versions
entirely through `docs:versions:add`.

## Retention

By default every version you've ever registered is kept forever. Set
`docs.sync.keep_versions` to prune old ones: `docs:sync` will delete
non-default versions beyond that count (most recently created first),
along with their documents. The default version is never pruned, regardless
of its age, so the version a docs UI currently points to by default is
always safe. `0` (the default) disables pruning entirely.

`docs.sync.prune_chunk_size` (default `50`) caps how many versions get
deleted in a single `docs:sync` run, in case a project has a large backlog
of old versions to work through — it'll take a few runs to fully catch up
rather than deleting hundreds of rows (and their documents) at once.

This pairs naturally with automatic discovery — every new release becomes
the default, and `keep_versions` cleans up versions that are no longer
default without you having to do it by hand.

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
