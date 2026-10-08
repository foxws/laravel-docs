<?php

declare(strict_types=1);

namespace Foxws\Docs\Models;

use Foxws\Docs\Database\Factories\ProjectFileFactory;
use Foxws\Docs\Support\MarkdownDocumentParser;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * A file from the root of a project's repository, such as its README or
 * CHANGELOG, stored as raw markdown by docs:sync.
 *
 * @property int $id
 * @property int $project_id
 * @property string $path
 * @property string $content
 * @property string $blob_sha
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Project $project
 */
class ProjectFile extends Model implements Htmlable
{
    /** @use HasFactory<ProjectFileFactory> */
    use HasFactory;

    protected $table = 'project_files';

    protected $fillable = [
        'project_id',
        'path',
        'content',
        'blob_sha',
    ];

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::modelClass());
    }

    /**
     * Rendered and cached the same way as Document::toHtml(), keyed by the
     * file's blob sha so a changed file re-renders on its own.
     */
    public function toHtml(?bool $shouldCache = null): string
    {
        if (! ($shouldCache ?? $this->shouldCache())) {
            return app(MarkdownDocumentParser::class)->renderAsHtml($this->content);
        }

        $store = Cache::store(config('docs.cache.store'));
        $ttl = config('docs.cache.ttl');
        $key = "docs:project-files:{$this->id}:{$this->blob_sha}:html";

        return $ttl === null
            ? $store->rememberForever($key, fn () => $this->toHtml(shouldCache: false))
            : $store->remember($key, $ttl, fn () => $this->toHtml(shouldCache: false));
    }

    public function shouldCache(): bool
    {
        return (bool) config('docs.cache.enabled');
    }

    protected static function newFactory(): ProjectFileFactory
    {
        return ProjectFileFactory::new();
    }

    /**
     * @return class-string<ProjectFile>
     */
    public static function modelClass(): string
    {
        return config('docs.models.project_file', static::class);
    }
}
