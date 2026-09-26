<?php

declare(strict_types=1);

namespace Foxws\Docs\Console\Commands;

use Foxws\Docs\Console\Concerns\InteractsWithStringInput;
use Foxws\Docs\Enums\ProjectDriver;
use Foxws\Docs\Models\Project;
use Foxws\Docs\Models\Version;
use Illuminate\Console\Command;

class AddProjectCommand extends Command
{
    use InteractsWithStringInput;

    protected $signature = 'docs:projects:add
        {slug : Unique identifier used in routing, e.g. "laravel-podman"}
        {title : Display title, e.g. "Laravel Podman"}
        {--github= : "owner/repo", e.g. "foxws/laravel-podman" — required unless --driver=local}
        {--driver=github : Where this project\'s docs live: "github" or "local"}
        {--local-path= : Base path to the docs folder, absolute or relative to the app root — required when --driver=local}
        {--docs-path=docs : Path to the docs folder within the repository/local path}
        {--seo-title-pattern= : sprintf-style title pattern, e.g. "%s — Laravel Podman — Foxws"}
        {--seo-description= : Fallback SEO description for this project\'s documents}
        {--sync : Sync the project immediately, registering a "latest" version tracking "main" (marked default) unless docs.sync.requires_versions is enabled for a GitHub project}';

    protected $description = 'Register a project (or update an existing one, matched by slug). Register at least one version with docs:versions:add before syncing.';

    public function handle(): int
    {
        $driver = ProjectDriver::tryFrom($this->stringOption('driver'));

        if ($driver === null) {
            $this->components->error('The --driver option must be "github" or "local".');

            return self::FAILURE;
        }

        $githubRepository = $this->option('github');
        $localPath = $this->option('local-path');

        if ($driver === ProjectDriver::Github && ! is_string($githubRepository)) {
            $this->components->error('The --github option is required when --driver=github.');

            return self::FAILURE;
        }

        if ($driver === ProjectDriver::Local && ! is_string($localPath)) {
            $this->components->error('The --local-path option is required when --driver=local.');

            return self::FAILURE;
        }

        $seo = array_filter([
            'title_pattern' => $this->option('seo-title-pattern'),
            'description' => $this->option('seo-description'),
        ]);

        $attributes = [
            'title' => $this->argument('title'),
            'driver' => $driver,
            'github_repository' => $driver === ProjectDriver::Github ? $githubRepository : null,
            'local_path' => $driver === ProjectDriver::Local ? $localPath : null,
            'docs_path' => $this->option('docs-path'),
            'seo' => $seo === [] ? null : $seo,
        ];

        $project = Project::findOrCreate($this->stringArgument('slug'), $attributes)
            ->updateRegistration($attributes);

        if ($this->option('sync')) {
            $this->components->info("Registered project [{$project->slug}].");

            if ($driver === ProjectDriver::Local || ! config('docs.sync.requires_versions')) {
                Version::findOrCreate($project->id, 'latest', ['ref' => 'main'])
                    ->updateRegistration(['ref' => 'main'])
                    ->markAsDefault();
            }

            return $this->call('docs:sync', ['--project' => $project->slug]);
        }

        $this->components->info("Registered project [{$project->slug}]. Register a version with `docs:versions:add` before syncing.");

        return self::SUCCESS;
    }
}
