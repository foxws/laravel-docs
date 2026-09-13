<?php

declare(strict_types=1);

namespace Foxws\Docs\Console\Commands;

use Foxws\Docs\Models\Project;
use Foxws\Docs\Models\Version;
use Illuminate\Console\Command;

class AddVersionCommand extends Command
{
    protected $signature = 'docs:versions:add
        {project : The project\'s slug, e.g. "laravel-podman"}
        {name : Version name, e.g. "1.0.0" or "latest"}
        {ref : Git tag or branch to sync from, e.g. "v1.0.0" or "main"}
        {--default : Mark this version as the default one}';

    protected $description = 'Register a version (or update an existing one, matched by name) for docs:sync to pull.';

    public function handle(): int
    {
        $project = Project::query()->where('slug', $this->argument('project'))->first();

        if (! $project) {
            $this->components->error("No project registered with slug [{$this->argument('project')}]. Run `docs:projects:add` first.");

            return self::FAILURE;
        }

        $attributes = [
            'ref' => $this->argument('ref'),
            'is_default' => $this->option('default'),
        ];

        $version = Version::findOrCreate($project->id, $this->argument('name'), $attributes)
            ->updateRegistration($attributes);

        $this->components->info("Registered version [{$project->slug}@{$version->name}]. Run `docs:sync` to pull its documentation.");

        return self::SUCCESS;
    }
}
