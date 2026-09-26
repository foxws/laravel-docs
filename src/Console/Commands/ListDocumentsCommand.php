<?php

declare(strict_types=1);

namespace Foxws\Docs\Console\Commands;

use Foxws\Docs\Models\Document;
use Foxws\Docs\Models\Project;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class ListDocumentsCommand extends Command
{
    protected $signature = 'docs:documents:list
        {project? : Only list documents of the project with this slug, e.g. "laravel-podman"; omit to list every project\'s documents}
        {version? : Only list documents of this version name, e.g. "1.0.0" or "latest"; requires a project}';

    protected $description = 'List synced documents with the project and version they belong to, in reading order.';

    public function handle(): int
    {
        $projectSlug = $this->argument('project');
        $versionName = $this->argument('version');
        $project = null;
        $version = null;

        if (is_string($projectSlug)) {
            $project = Project::findBySlug($projectSlug);

            if (! $project) {
                $this->components->error("No project registered with slug [{$projectSlug}].");

                return self::FAILURE;
            }
        }

        if ($project && is_string($versionName)) {
            $version = $project->versions()->where('name', $versionName)->first();

            if (! $version) {
                $this->components->error("No version [{$versionName}] registered for project [{$project->slug}].");

                return self::FAILURE;
            }
        }

        $documents = Document::modelClass()::query()
            ->with('version.project')
            ->when($version, fn (Builder $query) => $query->where('version_id', $version?->id))
            ->when($project && ! $version, fn (Builder $query) => $query->whereRelation('version', 'project_id', $project?->id))
            ->get()
            ->sortBy([
                ['version.project.slug', 'asc'],
                ['version_id', 'asc'],
                ['order', 'asc'],
                ['id', 'asc'],
            ]);

        if ($documents->isEmpty()) {
            $this->components->info('No documents found. Run `docs:sync` to pull them.');

            return self::SUCCESS;
        }

        $this->table(
            ['Project', 'Version', 'Slug', 'Title', 'Section', 'Order', 'Searchable'],
            $documents->map(fn (Document $document): array => [
                $document->version->project->slug,
                $document->version->name,
                $document->slug,
                $document->title,
                $document->section ?? '-',
                $document->order,
                $document->searchable ? 'yes' : 'no',
            ])->all(),
        );

        return self::SUCCESS;
    }
}
