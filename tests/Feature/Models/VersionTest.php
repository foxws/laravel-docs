<?php

declare(strict_types=1);

use Foxws\Docs\Models\Document;
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
        'last_synced_at' => now()->subDay(),
        'last_synced_sha' => 'previous-sha',
    ]);

    $version->updateRegistration(['ref' => 'v1.0.1']);

    expect($version->ref)->toBe('v1.0.1')
        ->and($version->last_synced_sha)->toBe('previous-sha')
        ->and($version->last_synced_at)->not->toBeNull();
});

it('ignores attributes outside the registration fields', function () {
    $version = Version::factory()->create(['name' => 'original-name']);

    $version->updateRegistration(['name' => 'changed-name', 'is_default' => true]);

    expect($version->name)->toBe('original-name')
        ->and($version->is_default)->toBeFalse();
});

it('marks a version as default and unmarks the previous default', function () {
    $project = Project::factory()->create();
    $current = Version::factory()->create(['project_id' => $project->id, 'is_default' => true]);
    $new = Version::factory()->create(['project_id' => $project->id, 'is_default' => false]);

    $new->markAsDefault();

    expect($new->refresh()->is_default)->toBeTrue()
        ->and($current->refresh()->is_default)->toBeFalse();
});

it('orders documents by the order column, breaking ties by id', function () {
    $version = Version::factory()->create();

    // Two different sections each restart their own numbering at 1 — a tie
    // the database is free to resolve however it likes without a tiebreaker.
    $first = Document::factory()->create(['version_id' => $version->id, 'section' => 'Getting Started', 'order' => 1]);
    $second = Document::factory()->create(['version_id' => $version->id, 'section' => 'Meta', 'order' => 1]);
    $third = Document::factory()->create(['version_id' => $version->id, 'section' => 'Getting Started', 'order' => 2]);

    expect($version->orderedDocuments()->pluck('id')->all())
        ->toBe([$first->id, $second->id, $third->id]);
});

it('does not affect another project\'s default when marking a version as default', function () {
    $otherProject = Project::factory()->create();
    $otherDefault = Version::factory()->create(['project_id' => $otherProject->id, 'is_default' => true]);

    $project = Project::factory()->create();
    $version = Version::factory()->create(['project_id' => $project->id, 'is_default' => false]);

    $version->markAsDefault();

    expect($otherDefault->refresh()->is_default)->toBeTrue();
});
