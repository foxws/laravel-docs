<?php

declare(strict_types=1);

use Foxws\Docs\Enums\ProjectDriver;
use Foxws\Docs\Models\Project;
use Illuminate\Support\Facades\Http;

it('registers a new project', function () {
    $this->artisan('docs:projects:add', [
        'slug' => 'laravel-podman',
        'title' => 'Laravel Podman',
        '--github' => 'foxws/laravel-podman',
        '--seo-title-pattern' => '%s — Laravel Podman — Foxws',
        '--seo-description' => 'Podman Quadlet tooling for Laravel.',
    ])->assertSuccessful();

    $project = Project::query()->where('slug', 'laravel-podman')->firstOrFail();

    expect($project->title)->toBe('Laravel Podman')
        ->and($project->github_repository)->toBe('foxws/laravel-podman')
        ->and($project->docs_path)->toBe('docs')
        ->and($project->seo->getArrayCopy())->toBe([
            'title_pattern' => '%s — Laravel Podman — Foxws',
            'description' => 'Podman Quadlet tooling for Laravel.',
        ]);

    $this->assertDatabaseCount('projects', 1);
});

it('accepts a custom docs path', function () {
    $this->artisan('docs:projects:add', [
        'slug' => 'laravel-podman',
        'title' => 'Laravel Podman',
        '--github' => 'foxws/laravel-podman',
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

    expect($project->driver)->toBe(ProjectDriver::Local)
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

it('requires --github when driver is github', function () {
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
        '--github' => 'foxws/laravel-podman',
    ])->assertSuccessful();

    $updated = $project->refresh();

    expect($updated->id)->toBe($project->id)
        ->and($updated->title)->toBe('New Title');

    $this->assertDatabaseCount('projects', 1);
});

it('registers a default "latest"/"main" version and syncs it immediately with --sync', function () {
    config()->set('docs.sync.requires_versions', false);

    Http::fake([
        'api.github.com/repos/foxws/laravel-podman/releases/latest' => Http::response(null, 404),
        'api.github.com/repos/foxws/laravel-podman/git/trees/main*' => Http::response([
            'sha' => 'root-sha',
            'tree' => [
                ['path' => 'docs/installation.md', 'mode' => '100644', 'type' => 'blob', 'sha' => 'blob-sha-a', 'size' => 512, 'url' => '...'],
            ],
            'truncated' => false,
        ], 200),
        'raw.githubusercontent.com/foxws/laravel-podman/main/docs/installation.md' => Http::response('# Installation', 200),
    ]);

    $this->artisan('docs:projects:add', [
        'slug' => 'laravel-podman',
        'title' => 'Laravel Podman',
        '--github' => 'foxws/laravel-podman',
        '--sync' => true,
    ])->assertSuccessful();

    $project = Project::query()->where('slug', 'laravel-podman')->firstOrFail();
    $version = $project->versions()->firstOrFail();

    expect($version->name)->toBe('latest')
        ->and($version->ref)->toBe('main')
        ->and($version->is_default)->toBeTrue()
        ->and($version->last_synced_at)->not->toBeNull();

    $this->assertDatabaseCount('documents', 1);
});

it('syncs only the discovered release version with --sync when versions are required', function () {
    config()->set('docs.sync.requires_versions', true);

    Http::fake([
        'api.github.com/repos/foxws/laravel-podman/releases/latest' => Http::response(['tag_name' => 'v2.0.0'], 200),
        'api.github.com/repos/foxws/laravel-podman/git/trees/v2.0.0*' => Http::response([
            'sha' => 'root-sha',
            'tree' => [
                ['path' => 'docs/installation.md', 'mode' => '100644', 'type' => 'blob', 'sha' => 'blob-sha-a', 'size' => 512, 'url' => '...'],
            ],
            'truncated' => false,
        ], 200),
        'raw.githubusercontent.com/foxws/laravel-podman/v2.0.0/docs/installation.md' => Http::response('# Installation', 200),
    ]);

    $this->artisan('docs:projects:add', [
        'slug' => 'laravel-podman',
        'title' => 'Laravel Podman',
        '--github' => 'foxws/laravel-podman',
        '--sync' => true,
    ])->assertSuccessful();

    $project = Project::query()->where('slug', 'laravel-podman')->firstOrFail();
    $version = $project->versions()->sole();

    expect($version->name)->toBe('2.0.0')
        ->and($version->ref)->toBe('v2.0.0')
        ->and($version->is_default)->toBeTrue()
        ->and($version->last_synced_at)->not->toBeNull();

    $this->assertDatabaseCount('documents', 1);
});

it('syncs nothing with --sync when versions are required and there is no release', function () {
    config()->set('docs.sync.requires_versions', true);

    Http::fake([
        'api.github.com/repos/foxws/laravel-podman/releases/latest' => Http::response(null, 404),
    ]);

    $this->artisan('docs:projects:add', [
        'slug' => 'laravel-podman',
        'title' => 'Laravel Podman',
        '--github' => 'foxws/laravel-podman',
        '--sync' => true,
    ])->assertSuccessful();

    $project = Project::query()->where('slug', 'laravel-podman')->firstOrFail();

    expect($project->versions)->toBeEmpty();

    $this->assertDatabaseCount('documents', 0);
});

it('still registers a "latest" version for a local project with --sync when versions are required', function () {
    config()->set('docs.sync.requires_versions', true);

    $this->artisan('docs:projects:add', [
        'slug' => 'stry',
        'title' => 'Stry',
        '--driver' => 'local',
        '--local-path' => 'docs',
        '--sync' => true,
    ])->assertSuccessful();

    $project = Project::query()->where('slug', 'stry')->firstOrFail();
    $version = $project->versions()->sole();

    expect($version->name)->toBe('latest')
        ->and($version->is_default)->toBeTrue();
});

it('does not register a version without --sync', function () {
    $this->artisan('docs:projects:add', [
        'slug' => 'laravel-podman',
        'title' => 'Laravel Podman',
        '--github' => 'foxws/laravel-podman',
    ])->assertSuccessful();

    $project = Project::query()->where('slug', 'laravel-podman')->firstOrFail();

    expect($project->versions)->toBeEmpty();
});
