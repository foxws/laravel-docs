<?php

declare(strict_types=1);

use Foxws\Docs\Models\Document;
use Foxws\Docs\Models\Project;
use Foxws\Docs\Models\Version;

it('removes a version and its documents', function () {
    $project = Project::factory()->create(['slug' => 'laravel-podman']);
    $version = Version::factory()->create(['project_id' => $project->id, 'name' => '1.0.0']);
    $other = Version::factory()->create(['project_id' => $project->id, 'name' => '2.0.0']);
    Document::factory()->create(['version_id' => $version->id]);
    Document::factory()->create(['version_id' => $other->id]);

    $this->artisan('docs:versions:remove', [
        'project' => 'laravel-podman',
        'name' => '1.0.0',
        '--force' => true,
    ])->assertSuccessful();

    $this->assertModelMissing($version);
    $this->assertModelExists($other);
    $this->assertDatabaseCount('documents', 1);
});

it('asks for confirmation before removing a version', function () {
    $project = Project::factory()->create(['slug' => 'laravel-podman']);
    $version = Version::factory()->create(['project_id' => $project->id, 'name' => '1.0.0']);

    $this->artisan('docs:versions:remove', [
        'project' => 'laravel-podman',
        'name' => '1.0.0',
    ])->expectsConfirmation('Remove version [laravel-podman@1.0.0] and its documents?', 'no')
        ->assertFailed();

    $this->assertModelExists($version);
});

it('fails when the project is not registered', function () {
    $this->artisan('docs:versions:remove', [
        'project' => 'missing-project',
        'name' => '1.0.0',
        '--force' => true,
    ])->assertFailed();
});

it('fails when the version is not registered', function () {
    Project::factory()->create(['slug' => 'laravel-podman']);

    $this->artisan('docs:versions:remove', [
        'project' => 'laravel-podman',
        'name' => '1.0.0',
        '--force' => true,
    ])->assertFailed();
});
