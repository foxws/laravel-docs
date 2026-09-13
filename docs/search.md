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

`searchableAs()` returns `config('docs.search.index_prefix') . 'documents'`.
`toSearchableArray()` indexes the title, plain-text body (markdown/HTML
stripped once at sync time), the owning project's slug, the version name,
and the section.
