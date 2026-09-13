<?php

declare(strict_types=1);

namespace Foxws\Docs\Models;

use Foxws\Docs\Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    /**
     * Pinned so a consumer's subclass (e.g. `App\Models\Project extends
     * Foxws\Docs\Models\Project`) still resolves to this table instead of
     * Eloquent guessing one from the subclass's own class name.
     */
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
        // Pinned foreign key: hasMany()'s default guess derives from the
        // calling model's own class name, which would break as soon as a
        // consumer's subclass (e.g. AcmeProject) calls this method.
        return $this->hasMany(Document::getDocumentClassName(), 'project_id');
    }

    protected static function newFactory(): ProjectFactory
    {
        return ProjectFactory::new();
    }

    /**
     * @return class-string<Project>
     */
    public static function getProjectClassName(): string
    {
        return config('docs.models.project', static::class);
    }
}
