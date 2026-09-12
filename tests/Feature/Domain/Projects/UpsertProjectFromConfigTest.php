<?php

declare(strict_types=1);

use Foxws\Docs\Domain\Projects\Actions\UpsertProjectFromConfig;
use Foxws\Docs\Domain\Projects\Models\Project;

it('creates a project from a config entry', function () {
    $project = (new UpsertProjectFromConfig)->handle([
        'slug' => 'laravel-podman',
        'title' => 'Laravel Podman',
        'github_repository' => 'foxws/laravel-podman',
        'docs_path' => 'docs',
        'branch' => 'main',
        'seo' => ['title_pattern' => '%s — Laravel Podman'],
    ]);

    expect($project)->toBeInstanceOf(Project::class)
        ->and($project->slug)->toBe('laravel-podman')
        ->and($project->github_repository)->toBe('foxws/laravel-podman')
        ->and($project->seo)->toBe(['title_pattern' => '%s — Laravel Podman'])
        ->and($project->last_synced_at)->toBeNull();

    $this->assertDatabaseCount('projects', 1);
});

it('updates static fields on an existing project matched by slug without touching sync bookkeeping', function () {
    $project = Project::factory()->create([
        'slug' => 'laravel-podman',
        'title' => 'Old Title',
        'last_synced_at' => now()->subDay(),
        'last_synced_sha' => 'previous-sha',
    ]);

    $updated = (new UpsertProjectFromConfig)->handle([
        'slug' => 'laravel-podman',
        'title' => 'New Title',
        'github_repository' => 'foxws/laravel-podman',
        'docs_path' => 'docs',
        'branch' => 'main',
        'seo' => null,
    ]);

    expect($updated->id)->toBe($project->id)
        ->and($updated->title)->toBe('New Title')
        ->and($updated->last_synced_at->toDateTimeString())->toBe($project->last_synced_at->toDateTimeString())
        ->and($updated->last_synced_sha)->toBe('previous-sha');
});
