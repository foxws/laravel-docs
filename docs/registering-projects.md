# Registering projects and versions

Register a project with `docs:projects:add`:

```bash
php artisan docs:projects:add laravel-podman "Laravel Podman" --github=foxws/laravel-podman \
    --seo-title-pattern="%s — Laravel Podman — Foxws" \
    --seo-description="Podman Quadlet tooling for Laravel."
```

| Argument/option | Default | Notes |
| --- | --- | --- |
| `slug` | — | Unique identifier, used in routing. |
| `title` | — | Display title. |
| `--github` | — | `owner/repo`. Required unless `--driver=local`. |
| `--driver` | `github` | `github` or `local` — see [Local projects](#local-projects). |
| `--local-path` | — | Base path to the docs folder. Required when `--driver=local`. |
| `--docs-path` | `docs` | Path to the docs folder within the repository/local path. |
| `--seo-title-pattern` | — | `sprintf`-style pattern, e.g. `"%s — Laravel Podman — Foxws"`. |
| `--seo-description` | — | Fallback SEO description for this project's documents. |

The command matches on `slug` — running it again with the same slug updates
that project's title/driver/repository/local path/docs_path/seo.

## Local projects

A project with no GitHub repository yet (or one you'd simply rather author
locally) can pull its docs from a folder instead:

```bash
php artisan docs:projects:add stry "Stry" --driver=local --local-path=docs
```

`--local-path` is a base path, absolute or relative to the application root,
holding the same `{docs-path}/*.md` structure a GitHub repository would (see
[Per-document front matter](#per-document-front-matter) below — it applies
identically either way). `--github` becomes optional and is cleared
when `--driver=local`; switching a project from `local` back to `github`
(or vice versa) is just re-running `docs:projects:add` with the other driver.

Local projects skip [automatic version discovery](#automatic-version-discovery)
entirely — a folder has no releases to discover — so register at least one
version for it explicitly, same as any other project. `ref` is unused for a
local driver (a folder has no concept of a git ref) but still required by
`docs:versions:add`; any label works, e.g. `local`.

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

The version's `name` is derived from the release's tag via
`docs.sync.version_name_pattern`, a regex (default `/\d.*/`, "keep from the
first digit onward") — tag `v2.0.0`, `version-2.0.0`, and `2.0.0` all become
name `2.0.0`, regardless of prefix convention. If the pattern doesn't match
at all (e.g. a tag like `stable` with no digits), the raw tag is used as the
name unchanged. Set it to `null` to always use the raw tag as-is, or to your
own pattern if your tags follow a different convention. Repositories with no
GitHub releases are skipped silently — nothing is registered for them until
you either publish a release or register a version manually.

Set `DOCS_AUTO_DISCOVER_VERSIONS=false` to turn this off and manage versions
entirely through `docs:versions:add`.

## Retention

By default `docs:sync` keeps the 5 most recently created non-default
versions per project (`docs.sync.keep_versions`) and deletes older ones,
along with their documents. The default version is never pruned, regardless
of its age, so the version a docs UI currently points to by default is
always safe. Set `DOCS_KEEP_VERSIONS=0` to keep everything instead.

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

## Project metadata

Add a `metadata:` block to your **index document**'s front matter
(`{docs_path}/index.md`, e.g. `docs/index.md`) to attach your own data to a
project — anything your app wants to show (a tag, a short description,
whatever). It's stored as-is on `Project::metadata`, an array:

```yaml
---
title: Introduction
metadata:
  role: Containers
  eyebrow: "Containers · Rootless · Laravel 11"
---
```

Only the index document is read for this — the same key elsewhere does
nothing. It updates on every sync and clears to `null` if you remove it.
Keep values plain text, normal case — if a UI wants it uppercase, that's a
CSS concern (e.g. Tailwind's `uppercase`), not something to bake into the
content.
