<?php

declare(strict_types=1);

namespace Foxws\Docs\Models;

use Foxws\Docs\Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property string $github_repository
 * @property string $docs_path
 * @property string $branch
 * @property array<string, mixed>|null $seo
 * @property Carbon|null $last_synced_at
 * @property string|null $last_synced_sha
 * @property Carbon $created_at
 * @property Carbon $updated_at
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
        'github_repository',
        'docs_path',
        'branch',
        'seo',
        'last_synced_at',
        'last_synced_sha',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seo' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        // Pinned FK: hasMany() would otherwise guess it from the subclass's class name.
        return $this->hasMany(Document::modelClass(), 'project_id');
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
     * Sync the registration fields (title, repository, docs path, branch,
     * seo). Leaves sync bookkeeping untouched.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateRegistration(array $attributes): static
    {
        $this->update(Arr::only($attributes, ['title', 'github_repository', 'docs_path', 'branch', 'seo']));

        return $this;
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
