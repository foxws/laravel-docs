<?php

declare(strict_types=1);

namespace Foxws\Docs\Models;

use Foxws\Docs\Database\Factories\VersionFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property string $name
 * @property string $ref
 * @property bool $is_default
 * @property Carbon|null $last_synced_at
 * @property string|null $last_synced_sha
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Project $project
 * @property Collection<int, Document> $documents
 */
class Version extends Model
{
    /** @use HasFactory<VersionFactory> */
    use HasFactory;

    /** Pinned so a subclass still resolves to this table. */
    protected $table = 'versions';

    /** @var list<string> */
    protected $fillable = [
        'project_id',
        'name',
        'ref',
        'is_default',
        'last_synced_at',
        'last_synced_sha',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::modelClass());
    }

    /**
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        // Pinned FK: hasMany() would otherwise guess it from the subclass's class name.
        return $this->hasMany(Document::modelClass(), 'version_id');
    }

    protected static function newFactory(): VersionFactory
    {
        return VersionFactory::new();
    }

    /**
     * @return class-string<Version>
     */
    public static function modelClass(): string
    {
        return config('docs.models.version', static::class);
    }

    /**
     * Find a project's version by name, or create it with the given attributes.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function findOrCreate(int $projectId, string $name, array $attributes = []): self
    {
        $modelClass = static::modelClass();

        return $modelClass::query()->firstOrCreate(
            ['project_id' => $projectId, 'name' => $name],
            $attributes,
        );
    }

    /**
     * Sync the registration fields (ref, is_default). Leaves sync
     * bookkeeping untouched.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateRegistration(array $attributes): static
    {
        $this->update(Arr::only($attributes, ['ref', 'is_default']));

        return $this;
    }
}
