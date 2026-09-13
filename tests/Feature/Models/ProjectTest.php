<?php

declare(strict_types=1);

use Foxws\Docs\Models\Project;

it('creates a project when none exists for the slug', function () {
    $project = Project::findOrCreate('laravel-podman', [
        'title' => 'Laravel Podman',
        'github_repository' => 'foxws/laravel-podman',
        'docs_path' => 'docs',
        'branch' => 'main',
    ]);

    expect($project->slug)->toBe('laravel-podman')
        ->and($project->title)->toBe('Laravel Podman');

    $this->assertDatabaseCount('projects', 1);
});

it('returns the existing project without changing it when found', function () {
    $existing = Project::factory()->create(['slug' => 'laravel-podman', 'title' => 'Original Title']);

    $project = Project::findOrCreate('laravel-podman', ['title' => 'Ignored Title']);

    expect($project->id)->toBe($existing->id)
        ->and($project->title)->toBe('Original Title');

    $this->assertDatabaseCount('projects', 1);
});

it('updates registration fields without touching sync bookkeeping', function () {
    $project = Project::factory()->create([
        'title' => 'Old Title',
        'last_synced_at' => now()->subDay(),
        'last_synced_sha' => 'previous-sha',
    ]);

    $project->updateRegistration([
        'title' => 'New Title',
        'github_repository' => 'foxws/laravel-podman',
        'docs_path' => 'docs',
        'branch' => 'main',
        'seo' => null,
    ]);

    expect($project->title)->toBe('New Title')
        ->and($project->last_synced_sha)->toBe('previous-sha')
        ->and($project->last_synced_at)->not->toBeNull();
});

it('ignores attributes outside the registration fields', function () {
    $project = Project::factory()->create(['last_synced_sha' => 'previous-sha']);

    $project->updateRegistration(['last_synced_sha' => 'should-not-apply']);

    expect($project->last_synced_sha)->toBe('previous-sha');
});
