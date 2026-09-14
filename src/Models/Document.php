<?php

declare(strict_types=1);

namespace Foxws\Docs\Models;

use Foxws\Docs\Database\Factories\DocumentFactory;
use Foxws\Docs\Support\MarkdownDocumentParser;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Laravel\Scout\Attributes\SearchUsingFullText;
use Laravel\Scout\Attributes\SearchUsingPrefix;
use Laravel\Scout\Searchable;

/**
 * @property int $id
 * @property int $version_id
 * @property string $slug
 * @property string $title
 * @property string $body Raw markdown source; render with toHtml().
 * @property int $order
 * @property string|null $section
 * @property string $source_path
 * @property string $blob_sha
 * @property bool $searchable
 * @property array<string, mixed>|null $seo
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Version $version
 */
class Document extends Model implements Htmlable
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    use Searchable;

    /** Pinned so a subclass still resolves to this table. */
    protected $table = 'documents';

    /** @var list<string> */
    protected $fillable = [
        'version_id',
        'slug',
        'title',
        'body',
        'order',
        'section',
        'source_path',
        'blob_sha',
        'searchable',
        'seo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'searchable' => 'boolean',
            'seo' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Version, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(Version::modelClass());
    }

    /**
     * Resolve the document's SEO title, cascading from the most specific
     * override down to the package-wide default.
     */
    public function resolveSeoTitle(): string
    {
        if ($title = $this->seo['title'] ?? null) {
            return $title;
        }

        if ($pattern = $this->version->project->seo['title_pattern'] ?? null) {
            return sprintf($pattern, $this->title);
        }

        if ($pattern = config('docs.seo.title_pattern')) {
            return sprintf($pattern, $this->title);
        }

        return $this->title;
    }

    /**
     * Render the markdown body to HTML, cached by blob sha so a re-sync
     * that changes the content automatically busts stale entries. Pass
     * $shouldCache to override shouldCache() for this call only.
     *
     * Implements Htmlable, so `{{ $document }}` in Blade renders this
     * unescaped instead of the raw markdown.
     */
    public function toHtml(?bool $shouldCache = null): string
    {
        if (! ($shouldCache ?? $this->shouldCache())) {
            return app(MarkdownDocumentParser::class)->renderAsHtml($this->body);
        }

        $store = Cache::store(config('docs.cache.store'));
        $ttl = config('docs.cache.ttl');
        $key = "docs:documents:{$this->id}:{$this->blob_sha}:html";

        return $ttl === null
            ? $store->rememberForever($key, fn () => $this->toHtml(shouldCache: false))
            : $store->remember($key, $ttl, fn () => $this->toHtml(shouldCache: false));
    }

    public function shouldCache(): bool
    {
        return (bool) config('docs.cache.enabled');
    }

    /**
     * Global kill switch for automatic indexing (only meaningful for
     * indexed engines — Algolia, Meilisearch, collection — since it gates
     * the model observer Searchable registers on save/delete; a no-op
     * under the database engine, which has no separate index to push to).
     *
     * This does not cover the per-document `searchable` column: Scout's
     * database engine never consults shouldBeSearchable(), so add your own
     * `->where('searchable', true)` when calling Document::search().
     */
    public function shouldBeSearchable(): bool
    {
        return (bool) config('docs.search.enabled');
    }

    public function searchableAs(): string
    {
        return config('docs.search.index_prefix').'documents';
    }

    /**
     * Only real documents columns — the database engine executes its
     * LIKE/full-text queries directly against columns named here, so
     * relation-derived values (e.g. the parent project's slug) can't
     * appear in this array. version_id is included (unlike project/
     * version) because it's a real column: the database engine can
     * already filter by it without this, but Algolia/Meilisearch need a
     * field present in the indexed record to filter on it at all, so this
     * keeps `->where('version_id', ...)` scoping working everywhere.
     *
     * section uses prefix matching — it's a short category label (e.g.
     * "Getting Started"), so matching from its start is both more useful
     * than a substring scan and, unlike a numeric id, is a column where
     * that actually applies. version_id gets no attribute: it's filtered
     * via where(), never meant to be free-text matched, and neither
     * strategy fits an integer column anyway — Scout has no "filter-only,
     * excluded from free text" option, so it stays on the default LIKE
     * strategy as an accepted, low-impact side effect.
     *
     * @return array<string, mixed>
     */
    #[SearchUsingFullText(['title', 'body'])]
    #[SearchUsingPrefix(['section'])]
    public function toSearchableArray(): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'section' => $this->section,
            'version_id' => $this->version_id,
        ];
    }

    protected static function newFactory(): DocumentFactory
    {
        return DocumentFactory::new();
    }

    /**
     * @return class-string<Document>
     */
    public static function modelClass(): string
    {
        return config('docs.models.document', static::class);
    }

    /**
     * Re-index all searchable documents, if search is enabled.
     */
    public static function syncSearchIndex(): void
    {
        if (! config('docs.search.enabled')) {
            return;
        }

        $modelClass = static::modelClass();

        $modelClass::makeAllSearchable();
    }
}
