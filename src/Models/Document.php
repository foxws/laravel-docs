<?php

declare(strict_types=1);

namespace Foxws\Docs\Models;

use Foxws\Docs\Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;

/**
 * @property int $id
 * @property int $project_id
 * @property string $slug
 * @property string $title
 * @property string $body
 * @property int $order
 * @property string|null $section
 * @property string $source_path
 * @property string $blob_sha
 * @property bool $searchable
 * @property array<string, mixed>|null $seo
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Project $project
 */
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    use Searchable;

    /** @var list<string> */
    protected $fillable = [
        'project_id',
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
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
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

        if ($pattern = $this->project->seo['title_pattern'] ?? null) {
            return sprintf($pattern, $this->title);
        }

        if ($pattern = config('docs.seo.title_pattern')) {
            return sprintf($pattern, $this->title);
        }

        return $this->title;
    }

    public function shouldBeSearchable(): bool
    {
        return (bool) config('docs.search.enabled') && $this->searchable !== false;
    }

    public function searchableAs(): string
    {
        return config('docs.search.index_prefix').'documents';
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'title' => $this->title,
            'body' => Str::of($this->body)->stripTags()->squish()->toString(),
            'project' => $this->project->slug,
            'section' => $this->section,
        ];
    }

    protected static function newFactory(): DocumentFactory
    {
        return DocumentFactory::new();
    }
}
