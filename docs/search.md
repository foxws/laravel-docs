# Search

`Document` always uses Scout's `Searchable` trait — `laravel/scout` is a
required dependency of this package, not optional, because a trait `use`
inside a class body is resolved at class-load time. There's no way to guard
that with `class_exists()`, so making Scout optional would mean `Document`
fatals for any consumer that doesn't have it installed, even one that never
searches.

Instead, indexing behavior is fully gated through `shouldBeSearchable()`:

```php
public function shouldBeSearchable(): bool
{
    return (bool) config('docs.search.enabled') && $this->searchable !== false;
}
```

Set `DOCS_SEARCH_ENABLED=false` to disable indexing entirely — no Scout
driver needs to be configured in that case, and no search-related network or
database calls will be made.

No `config/scout.php` setup is required to get working search, either.
Scout's own default driver (`SCOUT_DRIVER`, unset) is `collection` — an
in-memory engine that needs no external service, no API keys, and no
migrations. It's not the most efficient option at scale, but for a
documentation site's document count it's a reasonable zero-setup default.
Switch to `database` or a real search service (Meilisearch, Typesense, …)
via Scout's own config if you need more.

`searchableAs()` returns `config('docs.search.index_prefix') . 'documents'`.
`toSearchableArray()` indexes the title, plain-text body (markdown/HTML
stripped once at sync time), the owning project's slug, the version name,
and the section.
