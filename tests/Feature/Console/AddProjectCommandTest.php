<?php

declare(strict_types=1);

use Foxws\Docs\Models\Project;

it('registers a new project', function () {
    $this->artisan('docs:projects:add', [
        'slug' => 'laravel-podman',
        'title' => 'Laravel Podman',
        'github_repository' => 'foxws/laravel-podman',
        '--seo-title-pattern' => '%s — Laravel Podman — Foxws',
        '--seo-description' => 'Podman Quadlet tooling for Laravel.',
    ])->assertSuccessful();

    $project = Project::query()->where('slug', 'laravel-podman')->firstOrFail();

    expect($project->title)->toBe('Laravel Podman')
        ->and($project->github_repository)->toBe('foxws/laravel-podman')
        ->and($project->docs_path)->toBe('docs')
        ->and($project->seo)->toBe([
            'title_pattern' => '%s — Laravel Podman — Foxws',
            'description' => 'Podman Quadlet tooling for Laravel.',
        ]);

    $this->assertDatabaseCount('projects', 1);
});

it('accepts a custom docs path', function () {
    $this->artisan('docs:projects:add', [
        'slug' => 'laravel-podman',
        'title' => 'Laravel Podman',
        'github_repository' => 'foxws/laravel-podman',
        '--docs-path' => 'documentation',
    ])->assertSuccessful();

    $project = Project::query()->where('slug', 'laravel-podman')->firstOrFail();

    expect($project->docs_path)->toBe('documentation')
        ->and($project->seo)->toBeNull();
});

it('registers a local-driver project', function () {
    $this->artisan('docs:projects:add', [
        'slug' => 'stry',
        'title' => 'Stry',
        '--driver' => 'local',
        '--local-path' => 'docs',
    ])->assertSuccessful();

    $project = Project::query()->where('slug', 'stry')->firstOrFail();

    expect($project->driver)->toBe('local')
        ->and($project->local_path)->toBe('docs')
        ->and($project->github_repository)->toBeNull()
        ->and($project->sourceLocation())->toBe('docs');
});

it('rejects an unknown driver', function () {
    $this->artisan('docs:projects:add', [
        'slug' => 'stry',
        'title' => 'Stry',
        '--driver' => 'ftp',
    ])->assertFailed();

    $this->assertDatabaseCount('projects', 0);
});

it('requires github_repository when driver is github', function () {
    $this->artisan('docs:projects:add', [
        'slug' => 'laravel-podman',
        'title' => 'Laravel Podman',
    ])->assertFailed();

    $this->assertDatabaseCount('projects', 0);
});

it('requires --local-path when driver is local', function () {
    $this->artisan('docs:projects:add', [
        'slug' => 'stry',
        'title' => 'Stry',
        '--driver' => 'local',
    ])->assertFailed();

    $this->assertDatabaseCount('projects', 0);
});

it('updates an existing project matched by slug', function () {
    $project = Project::factory()->create([
        'slug' => 'laravel-podman',
        'title' => 'Old Title',
    ]);

    $this->artisan('docs:projects:add', [
        'slug' => 'laravel-podman',
        'title' => 'New Title',
        'github_repository' => 'foxws/laravel-podman',
    ])->assertSuccessful();

    $updated = $project->refresh();

    expect($updated->id)->toBe($project->id)
        ->and($updated->title)->toBe('New Title');

    $this->assertDatabaseCount('projects', 1);
});
