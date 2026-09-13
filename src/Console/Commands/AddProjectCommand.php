<?php

declare(strict_types=1);

namespace Foxws\Docs\Console\Commands;

use Foxws\Docs\Models\Project;
use Illuminate\Console\Command;

class AddProjectCommand extends Command
{
    protected $signature = 'docs:projects:add
        {slug : Unique identifier used in routing, e.g. "laravel-podman"}
        {title : Display title, e.g. "Laravel Podman"}
        {github_repository : "owner/repo", e.g. "foxws/laravel-podman"}
        {--docs-path=docs : Path to the docs folder within the repository}
        {--branch=main : Branch to sync from}
        {--seo-title-pattern= : sprintf-style title pattern, e.g. "%s — Laravel Podman — Foxws"}
        {--seo-description= : Fallback SEO description for this project\'s documents}';

    protected $description = 'Register a project (or update an existing one, matched by slug) for docs:sync to pull.';

    public function handle(): int
    {
        $seo = array_filter([
            'title_pattern' => $this->option('seo-title-pattern'),
            'description' => $this->option('seo-description'),
        ]);

        $project = Project::query()->updateOrCreate(
            ['slug' => $this->argument('slug')],
            [
                'title' => $this->argument('title'),
                'github_repository' => $this->argument('github_repository'),
                'docs_path' => $this->option('docs-path'),
                'branch' => $this->option('branch'),
                'seo' => $seo === [] ? null : $seo,
            ],
        );

        $this->components->info("Registered project [{$project->slug}]. Run `docs:sync` to pull its documentation.");

        return self::SUCCESS;
    }
}
