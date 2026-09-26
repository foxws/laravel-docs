<?php

declare(strict_types=1);

namespace Foxws\Docs\Console\Commands;

use Foxws\Docs\Models\Project;
use Illuminate\Console\Command;

class ListProjectsCommand extends Command
{
    protected $signature = 'docs:projects:list';

    protected $description = 'List every registered project with its source, default version, and version and document counts.';

    public function handle(): int
    {
        $projects = Project::modelClass()::query()
            ->with('versions')
            ->withCount(['versions', 'documents'])
            ->orderBy('slug')
            ->get();

        if ($projects->isEmpty()) {
            $this->components->info('No projects registered. Register one with `docs:projects:add`.');

            return self::SUCCESS;
        }

        $this->table(
            ['Slug', 'Title', 'Driver', 'Source', 'Default version', 'Versions', 'Documents'],
            $projects->map(fn (Project $project): array => [
                $project->slug,
                $project->title,
                $project->driver->value,
                $project->sourceLocation(),
                $project->defaultVersion()->name ?? '-',
                $project->getAttribute('versions_count'),
                $project->getAttribute('documents_count'),
            ])->all(),
        );

        return self::SUCCESS;
    }
}
