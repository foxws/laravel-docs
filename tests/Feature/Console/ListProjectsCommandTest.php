<?php

declare(strict_types=1);

use Foxws\Docs\Models\Document;
use Foxws\Docs\Models\Project;
use Foxws\Docs\Models\Version;

it('lists every project with its default version and counts', function () {
    $project = Project::factory()->create(['slug' => 'laravel-podman', 'title' => 'Laravel Podman', 'github_repository' => 'foxws/laravel-podman']);
    $version = Version::factory()->create(['project_id' => $project->id, 'name' => '1.0.0', 'is_default' => true]);
    Version::factory()->create(['project_id' => $project->id, 'name' => 'latest']);
    Document::factory()->count(2)->create(['version_id' => $version->id]);

    Project::factory()->local('packages/docs')->create(['slug' => 'laravel-docs', 'title' => 'Laravel Docs']);

    $this->artisan('docs:projects:list')
        ->expectsTable(
            ['Slug', 'Title', 'Driver', 'Source', 'Default version', 'Versions', 'Documents'],
            [
                ['laravel-docs', 'Laravel Docs', 'local', 'packages/docs', '-', 0, 0],
                ['laravel-podman', 'Laravel Podman', 'github', 'foxws/laravel-podman', '1.0.0', 2, 2],
            ],
        )
        ->assertSuccessful();
});

it('reports when no projects are registered', function () {
    $this->artisan('docs:projects:list')
        ->expectsOutputToContain('No projects registered.')
        ->assertSuccessful();
});
