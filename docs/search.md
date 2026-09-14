# Search

`Document` always uses Scout's `Searchable` trait — `laravel/scout` is a
required dependency of this package, not optional, because a trait `use`
inside a class body is resolved at class-load time. There's no way to guard
that with `class_exists()`, so making Scout optional would mean `Document`
fatals for any consumer that doesn't have it installed, even one that never
searches.

No `config/scout.php` setup is required to get working search, either.
Scout's own default driver (`SCOUT_DRIVER`, unset) is `collection` — an
in-memory engine that needs no external service, no API keys, and no
migrations. It's not the most efficient option at scale, but for a
documentation site's document count it's a reasonable zero-setup default.

## Excluding a document from search

Every document has a `searchable` column (`true` by default, overridable via
`searchable: false` in a document's YAML front matter). This is **not**
enforced automatically — add the constraint yourself wherever you call
`Document::search()`:

```php
Document::search('installation')->where('searchable', true)->get();
```

This is deliberate rather than an oversight: Scout's database engine (see
below) never consults `shouldBeSearchable()`, so that method can't be the
single source of truth for per-document opt-out without behaving
differently across engines. A `where()` clause is the one mechanism every
Scout engine honors identically.

`shouldBeSearchable()` still exists, but only as a *global* switch:

```php
public function shouldBeSearchable(): bool
{
    return (bool) config('docs.search.enabled');
}
```

Set `DOCS_SEARCH_ENABLED=false` to disable indexing entirely — no Scout
driver needs to be configured in that case, and no search-related network or
database calls will be made. This only matters for engines that maintain a
separate index (Algolia, Meilisearch, `collection`); the database engine has
no index to push to, so it's a no-op there regardless.

## Switching to the database engine

For anything beyond a handful of documents, switch to Scout's `database`
engine (`SCOUT_DRIVER=database`) — it searches your existing tables directly
via `LIKE`/full-text queries, no external service or separate index required.
This package is built with it in mind:

- `toSearchableArray()` only returns real `documents` columns (`title`,
  `body`, `section`). The database engine executes its queries directly
  against columns named in that array, so anything relation-derived (the
  parent project or version) can't appear there — it would reference a
  column that doesn't exist on the `documents` table. Scope a search to a
  specific project/version with `->where('version_id', $version->id)`
  instead.
- `body` holds raw markdown (see the [README](../README.md)), not
  HTML-stripped plain text — the database engine reads the column value
  directly, so the stripped/rendered form used to matter only for
  third-party or `collection` engines and isn't worth computing on every
  index write anymore.
- `toSearchableArray()` is annotated with `#[SearchUsingFullText(['title',
  'body'])]`, and the `documents` migration adds a matching full-text index
  on MySQL/MariaDB/PostgreSQL (skipped on SQLite, which has no full-text
  index support — the `collection` engine used in this package's own tests
  doesn't need one).

`searchableAs()` returns `config('docs.search.index_prefix') . 'documents'`,
though it has no effect under the database engine, which always searches the
model's table directly.
