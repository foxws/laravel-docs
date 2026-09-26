<?php

declare(strict_types=1);

namespace Foxws\Docs\Console\Commands;

use Foxws\Docs\Console\Concerns\InteractsWithStringInput;
use Foxws\Docs\Actions\DeleteVersion;
use Foxws\Docs\Models\Project;
use Illuminate\Console\Command;

class RemoveVersionCommand extends Command
{
    use InteractsWithStringInput;

    protected $signature = 'docs:versions:remove
        {project : The project\'s slug, e.g. "laravel-podman"}
        {name : Version name, e.g. "1.0.0" or "latest"}
        {--force : Remove without asking for confirmation}';

    protected $description = 'Remove a version and its documents.';

    public function handle(DeleteVersion $deleteVersion): int
    {
        $projectSlug = $this->stringArgument('project');
        $project = Project::findBySlug($projectSlug);

        if (! $project) {
            $this->components->error("No project registered with slug [{$projectSlug}].");

            return self::FAILURE;
        }

        $name = $this->stringArgument('name');
        $version = $project->versions()->where('name', $name)->first();

        if (! $version) {
            $this->components->error("No version [{$name}] registered for project [{$project->slug}].");

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->components->confirm("Remove version [{$project->slug}@{$version->name}] and its documents?")) {
            return self::FAILURE;
        }

        $deleteVersion->handle($version);

        $this->components->info("Removed version [{$project->slug}@{$version->name}].");

        return self::SUCCESS;
    }
}
