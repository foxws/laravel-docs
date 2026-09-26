<?php

declare(strict_types=1);

use Foxws\Docs\Models\Document;
use Foxws\Docs\Models\Project;
use Foxws\Docs\Models\Version;

it('lists every document with its project and version in reading order', function () {
    $project = Project::factory()->create(['slug' => 'laravel-podman']);
    $version = Version::factory()->create(['project_id' => $project->id, 'name' => '1.0.0']);
    Document::factory()->create(['version_id' => $version->id, 'slug' => 'usage', 'title' => 'Usage', 'order' => 2, 'section' => 'Guides']);
    Document::factory()->create(['version_id' => $version->id, 'slug' => 'installation', 'title' => 'Installation', 'order' => 1, 'searchable' => false]);

    $this->artisan('docs:documents:list')
        ->expectsTable(
            ['Project', 'Version', 'Slug', 'Title', 'Section', 'Order', 'Searchable'],
            [
                ['laravel-podman', '1.0.0', 'installation', 'Installation', '-', 1, 'no'],
                ['laravel-podman', '1.0.0', 'usage', 'Usage', 'Guides', 2, 'yes'],
            ],
        )
        ->assertSuccessful();
});

it('only lists documents of the given project', function () {
    $podman = Version::factory()->for(Project::factory()->state(['slug' => 'laravel-podman']))->create();
    $docs = Version::factory()->for(Project::factory()->state(['slug' => 'laravel-docs']))->create();
    Document::factory()->create(['version_id' => $podman->id, 'slug' => 'podman-installation']);
    Document::factory()->create(['version_id' => $docs->id, 'slug' => 'docs-installation']);

    $this->artisan('docs:documents:list', ['project' => 'laravel-podman'])
        ->expectsOutputToContain('podman-installation')
        ->doesntExpectOutputToContain('docs-installation')
        ->assertSuccessful();
});

it('only lists documents of the given version', function () {
    $project = Project::factory()->create(['slug' => 'laravel-podman']);
    $current = Version::factory()->create(['project_id' => $project->id, 'name' => '2.0.0']);
    $previous = Version::factory()->create(['project_id' => $project->id, 'name' => '1.0.0']);
    Document::factory()->create(['version_id' => $current->id, 'slug' => 'current-installation']);
    Document::factory()->create(['version_id' => $previous->id, 'slug' => 'previous-installation']);

    $this->artisan('docs:documents:list', ['project' => 'laravel-podman', 'version' => '2.0.0'])
        ->expectsOutputToContain('current-installation')
        ->doesntExpectOutputToContain('previous-installation')
        ->assertSuccessful();
});

it('fails when the project is not registered', function () {
    $this->artisan('docs:documents:list', ['project' => 'missing-project'])
        ->assertFailed();
});

it('fails when the version is not registered', function () {
    Project::factory()->create(['slug' => 'laravel-podman']);

    $this->artisan('docs:documents:list', ['project' => 'laravel-podman', 'version' => '1.0.0'])
        ->assertFailed();
});

it('reports when no documents are synced', function () {
    $this->artisan('docs:documents:list')
        ->expectsOutputToContain('No documents found.')
        ->assertSuccessful();
});
