---
section: Meta
order: 1
---

# Upgrading

No breaking changes yet.

## Upgrading to 1.8

1.8 adds a `project_files` table, so run `php artisan migrate`. If you
published the package's migrations, publish them again first
(`php artisan vendor:publish --tag=docs-migrations`) so the new one is
copied over.

`docs:sync` now also stores each project's `README.md`, `CHANGELOG.md` and
`NEWS.md`, which takes one more GitHub API request per project. Set
`DOCS_FILES_ENABLED=false` to keep the old behaviour, or change
`docs.files.paths` to pick other files.

A planned future version adds a second content type — site-wide static pages
(home, about, etc.) sourced from a single shared repository — alongside the
per-package "project" docs this version covers. That will be documented here
once its registration/grouping design is finalized.
