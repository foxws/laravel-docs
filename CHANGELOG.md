# Changelog

All notable changes to `laravel-docs` will be documented in this file.

## v1.4.0 - 2026-09-26

<!-- Release notes generated using configuration in .github/release.yml at main -->
### What's Changed

#### Other Changes

* feat: default title pattern to app name and search prefix to docs_ by @francoism90 in https://github.com/foxws/laravel-docs/pull/23
* feat: add requires_versions option and remove commands for projects and versions by @francoism90 in https://github.com/foxws/laravel-docs/pull/24

**Full Changelog**: https://github.com/foxws/laravel-docs/compare/v1.3.4...v1.4.0

#### Upgrade notes

- `docs.search.index_prefix` now defaults to `docs_` (was `foxws_`). If you use an index-based Scout engine, set `DOCS_SEARCH_PREFIX=foxws_` or re-import with `php artisan scout:import "Foxws\Docs\Models\Document"`.
- `docs.seo.title_pattern` now defaults to `'%s — '.env('APP_NAME', 'Laravel')` (was `'%s — Foxws'`). Override with `DOCS_SEO_TITLE_PATTERN`.
- New `docs.sync.requires_versions` (default `true`): `docs:projects:add --sync` no longer registers a `latest`/`main` version for GitHub projects. Set `DOCS_REQUIRES_VERSIONS=false` to keep the old behaviour.
- New commands: `docs:versions:remove` and `docs:projects:remove`.

## v1.3.4 - 2026-09-25

<!-- Release notes generated using configuration in .github/release.yml at ae76d8babcb152ca3981289ea6ef7f8bad4429e2 -->
### What's Changed

#### Other Changes

* fix: store front matter slugs without surrounding slashes by @francoism90 in https://github.com/foxws/laravel-docs/pull/22

**Full Changelog**: https://github.com/foxws/laravel-docs/compare/v1.3.3...v1.3.4

## v1.3.3 - 2026-09-22

<!-- Release notes generated using configuration in .github/release.yml at main -->
### What's Changed

#### Bug Fixes

* fix: retry transient GitHub CDN/API 404s during docs:sync by @francoism90 in https://github.com/foxws/laravel-docs/pull/21

**Full Changelog**: https://github.com/foxws/laravel-docs/compare/v1.3.2...v1.3.3

## v1.3.2 - 2026-09-17

<!-- Release notes generated using configuration in .github/release.yml at main -->
### What's Changed

#### Bug Fixes

* fix: don't prune/mark synced on an empty raw git tree by @francoism90 in https://github.com/foxws/laravel-docs/pull/20

**Full Changelog**: https://github.com/foxws/laravel-docs/compare/v1.3.1...v1.3.2

## v1.3.1 - 2026-09-16

<!-- Release notes generated using configuration in .github/release.yml at main -->
### What's Changed

#### Bug Fixes

* Let resolveSeoDescription() accept a pre-rendered html excerpt source by @francoism90 in https://github.com/foxws/laravel-docs/pull/19

**Full Changelog**: https://github.com/foxws/laravel-docs/compare/v1.3.0...v1.3.1

## v1.3.0 - 2026-09-16

<!-- Release notes generated using configuration in .github/release.yml at main -->
### What's Changed

#### Enhancements

* Add Document::resolveSeoDescription() by @francoism90 in https://github.com/foxws/laravel-docs/pull/18

**Full Changelog**: https://github.com/foxws/laravel-docs/compare/v1.2.0...v1.3.0

## v1.2.0 - 2026-09-16

<!-- Release notes generated using configuration in .github/release.yml at main -->
### What's Changed

#### Enhancements

* Add queued, overlap-protected docs:sync by @francoism90 in https://github.com/foxws/laravel-docs/pull/17

**Full Changelog**: https://github.com/foxws/laravel-docs/compare/v1.1.0...v1.2.0

## v1.1.0 - 2026-09-15

<!-- Release notes generated using configuration in .github/release.yml at main -->
### What's Changed

#### Other Changes

* Add Project::versionOrDefault() to resolve a version by name by @francoism90 in https://github.com/foxws/laravel-docs/pull/16

**Full Changelog**: https://github.com/foxws/laravel-docs/compare/v1.0.1...v1.1.0

## v1.0.1 - 2026-09-15

### Fixed

- `Document::title` now uses prefix matching instead of full-text search. Postgres full-text search matches whole, stemmed words, so a search-as-you-type query like "insta" never surfaced a document titled "Installation" — it now does.

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
