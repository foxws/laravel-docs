<?php

declare(strict_types=1);

namespace Foxws\Docs\Models;

use Foxws\Docs\Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property string $driver
 * @property string|null $github_repository
 * @property string|null $local_path
 * @property string $docs_path
 * @property array<string, mixed>|null $seo
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

    /** @var list<string> */
    protected $fillable = [
        'slug',
        'title',
        'driver',
        'github_repository',
        'local_path',
        'docs_path',
        'seo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seo' => 'array',
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
            'local' => (string) $this->local_path,
            default => (string) $this->github_repository,
        };
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
