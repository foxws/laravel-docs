<?php

declare(strict_types=1);

use Foxws\Docs\Models\Document;
use Foxws\Docs\Models\Project;
use Foxws\Docs\Models\Version;

it('removes a project with its versions and documents', function () {
    $project = Project::factory()->create(['slug' => 'laravel-podman']);
    $version = Version::factory()->create(['project_id' => $project->id]);
    Document::factory()->create(['version_id' => $version->id]);
    $other = Project::factory()->create(['slug' => 'stry']);

    $this->artisan('docs:projects:remove', [
        'slug' => 'laravel-podman',
        '--force' => true,
    ])->assertSuccessful();

    $this->assertModelMissing($project);
    $this->assertModelExists($other);
    $this->assertDatabaseCount('versions', 0);
    $this->assertDatabaseCount('documents', 0);
});

it('asks for confirmation before removing a project', function () {
    $project = Project::factory()->create(['slug' => 'laravel-podman']);

    $this->artisan('docs:projects:remove', ['slug' => 'laravel-podman'])
        ->expectsConfirmation('Remove project [laravel-podman] with all of its versions and documents?', 'no')
        ->assertFailed();

    $this->assertModelExists($project);
});

it('fails when the project is not registered', function () {
    $this->artisan('docs:projects:remove', [
        'slug' => 'missing-project',
        '--force' => true,
    ])->assertFailed();
});
