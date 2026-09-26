<?php

declare(strict_types=1);

namespace Foxws\Docs\Models;

use Foxws\Docs\Collections\VersionCollection;
use Foxws\Docs\Database\Factories\VersionFactory;
use Illuminate\Database\Eloquent\Attributes\CollectedBy;
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
#[CollectedBy(VersionCollection::class)]
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

    /**
     * This version's documents in a stable reading order. `order` only ranks
     * a document among others in its own section (see docs/registering-projects.md),
     * so two documents in different sections both left at the default (or
     * both explicitly set to the same value) tie — an `id` tiebreaker keeps
     * that tie resolved the same way on every call instead of however the
     * database happens to return it.
     *
     * @return Collection<int, Document>
     */
    public function orderedDocuments(): Collection
    {
        return $this->documents()->orderBy('order')->orderBy('id')->get();
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
     * Sync the registration fields (ref). Leaves sync bookkeeping and
     * is_default untouched — use markAsDefault() to change that.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateRegistration(array $attributes): static
    {
        $this->update(Arr::only($attributes, ['ref']));

        return $this;
    }

    /**
     * This version's name as a version number — without a leading "v" —
     * or null if the name isn't one (e.g. "latest").
     */
    public function versionNumber(): ?string
    {
        if (preg_match('/^v?(\d+(?:\.\d+)*(?:[-+.]?[0-9A-Za-z.-]+)?)$/i', $this->name, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    /**
     * Mark this version as the project's default, unmarking any other.
     */
    public function markAsDefault(): static
    {
        static::query()->where('project_id', $this->project_id)->update(['is_default' => false]);

        $this->update(['is_default' => true]);

        return $this;
    }
}
