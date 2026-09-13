<?php

declare(strict_types=1);

use Foxws\Docs\Models\Project;
use Foxws\Docs\Models\Version;

it('creates a version when none exists for the name', function () {
    $project = Project::factory()->create();

    $version = Version::findOrCreate($project->id, '1.0.0', ['ref' => 'v1.0.0']);

    expect($version->project_id)->toBe($project->id)
        ->and($version->name)->toBe('1.0.0')
        ->and($version->ref)->toBe('v1.0.0');

    $this->assertDatabaseCount('versions', 1);
});

it('returns the existing version without changing it when found', function () {
    $project = Project::factory()->create();
    $existing = Version::factory()->create(['project_id' => $project->id, 'name' => '1.0.0', 'ref' => 'v1.0.0']);

    $version = Version::findOrCreate($project->id, '1.0.0', ['ref' => 'ignored']);

    expect($version->id)->toBe($existing->id)
        ->and($version->ref)->toBe('v1.0.0');

    $this->assertDatabaseCount('versions', 1);
});

it('scopes findOrCreate to the given project', function () {
    $projectA = Project::factory()->create();
    $projectB = Project::factory()->create();
    Version::factory()->create(['project_id' => $projectA->id, 'name' => '1.0.0']);

    Version::findOrCreate($projectB->id, '1.0.0', ['ref' => 'v1.0.0']);

    $this->assertDatabaseCount('versions', 2);
});

it('updates registration fields without touching sync bookkeeping', function () {
    $version = Version::factory()->create([
        'ref' => 'v1.0.0',
        'is_default' => false,
        'last_synced_at' => now()->subDay(),
        'last_synced_sha' => 'previous-sha',
    ]);

    $version->updateRegistration(['ref' => 'v1.0.1', 'is_default' => true]);

    expect($version->ref)->toBe('v1.0.1')
        ->and($version->is_default)->toBeTrue()
        ->and($version->last_synced_sha)->toBe('previous-sha')
        ->and($version->last_synced_at)->not->toBeNull();
});

it('ignores attributes outside the registration fields', function () {
    $version = Version::factory()->create(['name' => 'original-name']);

    $version->updateRegistration(['name' => 'changed-name']);

    expect($version->name)->toBe('original-name');
});
