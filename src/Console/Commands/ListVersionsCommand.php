<?php

declare(strict_types=1);

namespace Foxws\Docs\Console\Commands;

use Foxws\Docs\Models\Project;
use Foxws\Docs\Models\Version;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class ListVersionsCommand extends Command
{
    protected $signature = 'docs:versions:list
        {project? : Only list versions of the project with this slug, e.g. "laravel-podman"; omit to list every project\'s versions}';

    protected $description = 'List registered versions with their project, ref, sync state, and document count.';

    public function handle(): int
    {
        $projectSlug = $this->argument('project');
        $project = null;

        if (is_string($projectSlug)) {
            $project = Project::findBySlug($projectSlug);

            if (! $project) {
                $this->components->error("No project registered with slug [{$projectSlug}].");

                return self::FAILURE;
            }
        }

        $versions = Version::modelClass()::query()
            ->with('project')
            ->withCount('documents')
            ->when($project, fn (Builder $query) => $query->where('project_id', $project?->id))
            ->get()
            ->sortBy([['project.slug', 'asc'], ['id', 'asc']]);

        if ($versions->isEmpty()) {
            $this->components->info('No versions registered. Register one with `docs:versions:add`.');

            return self::SUCCESS;
        }

        $this->table(
            ['Project', 'Name', 'Ref', 'Default', 'Documents', 'Last synced', 'Synced sha'],
            $versions->map(fn (Version $version): array => [
                $version->project->slug,
                $version->name,
                $version->ref,
                $version->is_default ? 'yes' : 'no',
                $version->getAttribute('documents_count'),
                $version->last_synced_at?->toDateTimeString() ?? 'never',
                $version->last_synced_sha === null ? '-' : substr($version->last_synced_sha, 0, 7),
            ])->all(),
        );

        return self::SUCCESS;
    }
}
