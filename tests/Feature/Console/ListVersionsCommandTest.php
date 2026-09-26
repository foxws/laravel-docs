<?php

declare(strict_types=1);

use Foxws\Docs\Models\Document;
use Foxws\Docs\Models\Project;
use Foxws\Docs\Models\Version;
use Illuminate\Support\Carbon;

it('lists every version grouped by project', function () {
    $podman = Project::factory()->create(['slug' => 'laravel-podman']);
    $docs = Project::factory()->create(['slug' => 'laravel-docs']);

    $synced = Version::factory()->create([
        'project_id' => $podman->id,
        'name' => '1.0.0',
        'ref' => 'v1.0.0',
        'is_default' => true,
        'last_synced_at' => Carbon::parse('2026-09-26 03:00:00'),
        'last_synced_sha' => '1eab9f3ed5ac20b98c7be3ee0210c98f53f85d90',
    ]);
    Document::factory()->count(3)->create(['version_id' => $synced->id]);

    Version::factory()->create(['project_id' => $docs->id, 'name' => 'latest', 'ref' => 'main', 'last_synced_at' => null, 'last_synced_sha' => null]);

    $this->artisan('docs:versions:list')
        ->expectsTable(
            ['Project', 'Name', 'Ref', 'Default', 'Documents', 'Last synced', 'Synced sha'],
            [
                ['laravel-docs', 'latest', 'main', 'no', 0, 'never', '-'],
                ['laravel-podman', '1.0.0', 'v1.0.0', 'yes', 3, '2026-09-26 03:00:00', '1eab9f3'],
            ],
        )
        ->assertSuccessful();
});

it('only lists versions of the given project', function () {
    $podman = Project::factory()->create(['slug' => 'laravel-podman']);
    $docs = Project::factory()->create(['slug' => 'laravel-docs']);
    Version::factory()->create(['project_id' => $podman->id, 'name' => '1.0.0']);
    Version::factory()->create(['project_id' => $docs->id, 'name' => '2.0.0']);

    $this->artisan('docs:versions:list', ['project' => 'laravel-podman'])
        ->expectsOutputToContain('1.0.0')
        ->doesntExpectOutputToContain('2.0.0')
        ->assertSuccessful();
});

it('fails when the project is not registered', function () {
    $this->artisan('docs:versions:list', ['project' => 'missing-project'])
        ->assertFailed();
});

it('reports when no versions are registered', function () {
    $this->artisan('docs:versions:list')
        ->expectsOutputToContain('No versions registered.')
        ->assertSuccessful();
});
