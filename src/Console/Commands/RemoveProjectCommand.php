<?php

declare(strict_types=1);

namespace Foxws\Docs\Console\Commands;

use Foxws\Docs\Console\Concerns\InteractsWithStringInput;
use Foxws\Docs\Actions\DeleteVersion;
use Foxws\Docs\Models\Project;
use Foxws\Docs\Models\Version;
use Illuminate\Console\Command;

class RemoveProjectCommand extends Command
{
    use InteractsWithStringInput;

    protected $signature = 'docs:projects:remove
        {slug : The project\'s slug, e.g. "laravel-podman"}
        {--force : Remove without asking for confirmation}';

    protected $description = 'Remove a project along with all of its versions and documents.';

    public function handle(DeleteVersion $deleteVersion): int
    {
        $slug = $this->stringArgument('slug');

        $project = Project::findBySlug($slug);

        if (! $project) {
            $this->components->error("No project registered with slug [{$slug}].");

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->components->confirm("Remove project [{$project->slug}] with all of its versions and documents?")) {
            return self::FAILURE;
        }

        $project->versions->each(fn (Version $version) => $deleteVersion->handle($version));

        $project->delete();

        $this->components->info("Removed project [{$project->slug}].");

        return self::SUCCESS;
    }
}
