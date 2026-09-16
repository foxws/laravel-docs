# Changelog

All notable changes to `laravel-docs` will be documented in this file.

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
