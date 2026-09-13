<?php

declare(strict_types=1);

use Foxws\Docs\Models\Project;
use Foxws\Docs\Models\Version;

it('registers a new version for an existing project', function () {
    $project = Project::factory()->create(['slug' => 'laravel-podman']);

    $this->artisan('docs:versions:add', [
        'project' => 'laravel-podman',
        'name' => '1.0.0',
        'ref' => 'v1.0.0',
        '--default' => true,
    ])->assertSuccessful();

    $version = Version::query()->where('project_id', $project->id)->where('name', '1.0.0')->firstOrFail();

    expect($version->ref)->toBe('v1.0.0')
        ->and($version->is_default)->toBeTrue();
});

it('fails when the project is not registered', function () {
    $this->artisan('docs:versions:add', [
        'project' => 'missing-project',
        'name' => '1.0.0',
        'ref' => 'v1.0.0',
    ])->assertFailed();

    $this->assertDatabaseCount('versions', 0);
});

it('updates an existing version matched by name', function () {
    $project = Project::factory()->create(['slug' => 'laravel-podman']);
    $version = Version::factory()->create(['project_id' => $project->id, 'name' => '1.0.0', 'ref' => 'v1.0.0']);

    $this->artisan('docs:versions:add', [
        'project' => 'laravel-podman',
        'name' => '1.0.0',
        'ref' => 'v1.0.1',
    ])->assertSuccessful();

    expect($version->refresh()->ref)->toBe('v1.0.1');

    $this->assertDatabaseCount('versions', 1);
});
