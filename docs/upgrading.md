---
section: Meta
order: 1
---

# Upgrading

No breaking changes yet.

## Upgrading to 1.8

1.8 adds `type` and `project_id` columns to `documents` and makes
`version_id` nullable, so run `php artisan migrate`. If you published the
package's migrations, publish them again first
(`php artisan vendor:publish --tag=docs-migrations`) so the new one is
copied over.

`docs:sync` now also stores each project's `README.md`, `CHANGELOG.md` and
`NEWS.md` as documents of type `File`, which takes one more GitHub API
request per project. They have no version and `searchable` false, so
`Version::documents()`, `Project::documents()` and search are unchanged.
If you query `Document` directly, add `->where('type', DocumentType::Page)`
where you only want pages, and use `$document->owningProject()` instead of
`$document->version->project` where a document could be a root file. Set
`docs.sync.additional_files` to `[]` to keep the old behaviour, or change
it to pick other files.

A planned future version adds a second content type — site-wide static pages
(home, about, etc.) sourced from a single shared repository — alongside the
per-package "project" docs this version covers. That will be documented here
once its registration/grouping design is finalized.
