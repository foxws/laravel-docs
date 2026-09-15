<?php

declare(strict_types=1);

use Foxws\Docs\Models\Document;
use Foxws\Docs\Models\Project;
use Foxws\Docs\Models\Version;

it('creates a project when none exists for the slug', function () {
    $project = Project::findOrCreate('laravel-podman', [
        'title' => 'Laravel Podman',
        'github_repository' => 'foxws/laravel-podman',
        'docs_path' => 'docs',
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

it('finds a project by slug', function () {
    $existing = Project::factory()->create(['slug' => 'laravel-podman']);

    $project = Project::findBySlug('laravel-podman');

    expect($project->id)->toBe($existing->id);
});

it('returns null when no project matches the slug', function () {
    expect(Project::findBySlug('missing'))->toBeNull();
});

it('eager-loads the given relations when finding a project by slug', function () {
    $existing = Project::factory()->create(['slug' => 'laravel-podman']);
    Version::factory()->create(['project_id' => $existing->id]);

    $project = Project::findBySlug('laravel-podman', ['versions']);

    expect($project->relationLoaded('versions'))->toBeTrue();
});

it('uses slug as the route key', function () {
    expect((new Project)->getRouteKeyName())->toBe('slug');
});

it('updates registration fields', function () {
    $project = Project::factory()->create(['title' => 'Old Title']);

    $project->updateRegistration([
        'title' => 'New Title',
        'github_repository' => 'foxws/laravel-podman',
        'docs_path' => 'docs',
        'seo' => null,
    ]);

    expect($project->title)->toBe('New Title');
});

it('ignores attributes outside the registration fields', function () {
    $project = Project::factory()->create(['slug' => 'original-slug']);

    $project->updateRegistration(['slug' => 'changed-slug']);

    expect($project->slug)->toBe('original-slug');
});

it('resolves the source location from github_repository for a github-driver project', function () {
    $project = Project::factory()->create(['github_repository' => 'foxws/laravel-podman']);

    expect($project->sourceLocation())->toBe('foxws/laravel-podman');
});

it('resolves the source location from local_path for a local-driver project', function () {
    $project = Project::factory()->local('docs')->create();

    expect($project->sourceLocation())->toBe('docs');
});

it('builds the index document path from docs_path', function () {
    $project = Project::factory()->create(['docs_path' => 'docs']);

    expect($project->indexDocumentPath())->toBe('docs/index.md');
});

it('resolves the default version, falling back to the first when none is marked default', function () {
    $project = Project::factory()->create();
    $first = Version::factory()->create(['project_id' => $project->id, 'is_default' => false]);

    expect($project->refresh()->defaultVersion()->is($first))->toBeTrue();

    $default = Version::factory()->create(['project_id' => $project->id, 'is_default' => true]);

    expect($project->refresh()->defaultVersion()->is($default))->toBeTrue();
});

it('resolves a version by name, falling back to the default when the name is null', function () {
    $project = Project::factory()->create();
    $default = Version::factory()->create(['project_id' => $project->id, 'name' => '1.0.1', 'is_default' => true]);
    $latest = Version::factory()->create(['project_id' => $project->id, 'name' => 'latest', 'is_default' => false]);

    expect($project->refresh()->versionOrDefault('latest')->is($latest))->toBeTrue()
        ->and($project->versionOrDefault(null)->is($default))->toBeTrue();
});

it('falls back to the default version when the requested name does not exist', function () {
    $project = Project::factory()->create();
    $default = Version::factory()->create(['project_id' => $project->id, 'is_default' => true]);

    expect($project->refresh()->versionOrDefault('nonexistent')->is($default))->toBeTrue();
});

it('resolves the index document from index.md, falling back to about.md', function () {
    $project = Project::factory()->create();
    $version = Version::factory()->create(['project_id' => $project->id]);
    $about = Document::factory()->create(['version_id' => $version->id, 'slug' => 'about']);

    expect($project->indexDocument($version->documents)->is($about))->toBeTrue();

    $index = Document::factory()->create(['version_id' => $version->id, 'slug' => 'index']);

    expect($project->indexDocument($version->refresh()->documents)->is($index))->toBeTrue();
});

it('iterates every registered project', function () {
    Project::factory()->count(3)->create();

    $slugs = [];
    Project::eachRegistered(function (Project $project) use (&$slugs) {
        $slugs[] = $project->slug;
    });

    expect($slugs)->toHaveCount(3);
});
