# Changelog

All notable changes to `laravel-docs` will be documented in this file.

## v1.0.0 - 2026-09-15

First release.

A headless package that pulls a package's `docs/*.md` folder — from GitHub, or a local folder for a project with no repository of its own yet — into queryable `Project`/`Version`/`Document` Eloquent models. There is no bundled frontend or theme; the consuming application owns all rendering.

**Highlights**

- Register projects via `docs:projects:add`, versions via `docs:versions:add` — no config array to maintain.
- `docs:sync` pulls each version's `docs/` folder, auto-discovers the latest GitHub release as the default version, and prunes old ones.
- Per-document front matter (`title`, `order`, `section`, `searchable`, `seo`) and a project-level `metadata` block synced from the index document.
- Full-text search via Laravel Scout, gated by config and a per-document `searchable` flag.
- Swap in your own `Project`/`Version`/`Document` subclasses via config — no forking required.

See [docs/index.md](docs/index.md) for the full guide.
