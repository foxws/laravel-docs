<?php

declare(strict_types=1);

namespace Foxws\Docs\Models;

use ArrayObject;
use Foxws\Docs\Database\Factories\ProjectFactory;
use Foxws\Docs\Enums\ProjectDriver;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property ProjectDriver $driver
 * @property string|null $github_repository
 * @property string|null $local_path
 * @property string $docs_path
 * @property ArrayObject<string, mixed>|null $seo
 * @property ArrayObject<string, mixed>|null $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Collection<int, Version> $versions
 * @property Collection<int, Document> $documents
 */
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    /** Pinned so a subclass still resolves to this table. */
    protected $table = 'projects';

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @var list<string> */
    protected $fillable = [
        'slug',
        'title',
        'driver',
        'github_repository',
        'local_path',
        'docs_path',
        'seo',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'driver' => ProjectDriver::class,
            'seo' => AsArrayObject::class,
            'metadata' => AsArrayObject::class,
        ];
    }

    /**
     * @return HasMany<Version, $this>
     */
    public function versions(): HasMany
    {
        // Pinned FK: hasMany() would otherwise guess it from the subclass's class name.
        return $this->hasMany(Version::modelClass(), 'project_id');
    }

    /**
     * All documents across every version of this project.
     *
     * @return HasManyThrough<Document, Version, $this>
     */
    public function documents(): HasManyThrough
    {
        return $this->hasManyThrough(
            Document::modelClass(),
            Version::modelClass(),
            'project_id',
            'version_id',
        );
    }

    protected static function newFactory(): ProjectFactory
    {
        return ProjectFactory::new();
    }

    /**
     * @return class-string<Project>
     */
    public static function modelClass(): string
    {
        return config('docs.models.project', static::class);
    }

    /**
     * Find a project by slug, or create it with the given attributes.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function findOrCreate(string $slug, array $attributes = []): self
    {
        $modelClass = static::modelClass();

        return $modelClass::query()->firstOrCreate(['slug' => $slug], $attributes);
    }

    /**
     * Find a project by slug, optionally eager-loading relations.
     *
     * @param  list<string>  $with
     */
    public static function findBySlug(string $slug, array $with = []): ?self
    {
        $modelClass = static::modelClass();

        return $modelClass::query()->with($with)->where('slug', $slug)->first();
    }

    /**
     * Sync the registration fields (title, driver, repository/local path,
     * docs path, seo).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateRegistration(array $attributes): static
    {
        $this->update(Arr::only($attributes, [
            'title', 'driver', 'github_repository', 'local_path', 'docs_path', 'seo',
        ]));

        return $this;
    }

    /**
     * Where this project's docs live, per its driver — a GitHub "owner/repo"
     * slug, or a local base path. SyncVersionDocuments/DiscoverLatestVersion
     * read this instead of a column name, so they don't need to know which
     * driver they're dealing with.
     */
    public function sourceLocation(): string
    {
        return match ($this->driver) {
            ProjectDriver::Local => (string) $this->local_path,
            default => (string) $this->github_repository,
        };
    }

    /**
     * The document SyncVersionDocuments reads project metadata from.
     */
    public function indexDocumentPath(): string
    {
        return Str::finish($this->docs_path, '/').'index.md';
    }

    /**
     * This project's version to read from absent a more specific choice —
     * the one marked default, or the first registered if none is.
     */
    public function defaultVersion(): ?Version
    {
        return $this->versions->firstWhere('is_default', true) ?? $this->versions->first();
    }

    /**
     * The document that corresponds to indexDocumentPath() within an
     * already-loaded collection of this project's documents — `index.md`,
     * or `about.md` for a project with no index of its own. Accepts the
     * base Support\Collection, not just Eloquent's — a project with no
     * version at all has nothing to load a real Collection from.
     *
     * @param  BaseCollection<int, Document>  $documents
     */
    public function indexDocument(BaseCollection $documents): ?Document
    {
        return $documents->firstWhere('slug', 'index') ?? $documents->firstWhere('slug', 'about');
    }

    /**
     * Iterate every registered project.
     *
     * @param  callable(Project): void  $callback
     */
    public static function eachRegistered(callable $callback): void
    {
        $modelClass = static::modelClass();

        $modelClass::query()->each($callback);
    }
}
