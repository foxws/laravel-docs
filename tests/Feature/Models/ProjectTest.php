<?php

declare(strict_types=1);

use Foxws\Docs\Models\Project;

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

it('iterates every registered project', function () {
    Project::factory()->count(3)->create();

    $slugs = [];
    Project::eachRegistered(function (Project $project) use (&$slugs) {
        $slugs[] = $project->slug;
    });

    expect($slugs)->toHaveCount(3);
});
