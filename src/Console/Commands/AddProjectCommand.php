<?php

declare(strict_types=1);

namespace Foxws\Docs\Console\Commands;

use Foxws\Docs\Models\Project;
use Illuminate\Console\Command;
use RuntimeException;

class AddProjectCommand extends Command
{
    protected $signature = 'docs:projects:add
        {slug : Unique identifier used in routing, e.g. "laravel-podman"}
        {title : Display title, e.g. "Laravel Podman"}
        {github_repository : "owner/repo", e.g. "foxws/laravel-podman"}
        {--docs-path=docs : Path to the docs folder within the repository}
        {--seo-title-pattern= : sprintf-style title pattern, e.g. "%s — Laravel Podman — Foxws"}
        {--seo-description= : Fallback SEO description for this project\'s documents}';

    protected $description = 'Register a project (or update an existing one, matched by slug). Register at least one version with docs:versions:add before syncing.';

    public function handle(): int
    {
        $seo = array_filter([
            'title_pattern' => $this->option('seo-title-pattern'),
            'description' => $this->option('seo-description'),
        ]);

        $attributes = [
            'title' => $this->argument('title'),
            'github_repository' => $this->argument('github_repository'),
            'docs_path' => $this->option('docs-path'),
            'seo' => $seo === [] ? null : $seo,
        ];

        $project = Project::findOrCreate($this->stringArgument('slug'), $attributes)
            ->updateRegistration($attributes);

        $this->components->info("Registered project [{$project->slug}]. Register a version with `docs:versions:add` before syncing.");

        return self::SUCCESS;
    }

    private function stringArgument(string $key): string
    {
        $value = $this->argument($key);

        if (! is_string($value)) {
            throw new RuntimeException("The [{$key}] argument must be a string.");
        }

        return $value;
    }
}
