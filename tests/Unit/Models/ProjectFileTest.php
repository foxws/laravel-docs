<?php

declare(strict_types=1);

use Foxws\Docs\Models\Project;
use Foxws\Docs\Models\ProjectFile;

it('renders its markdown as html', function () {
    $file = ProjectFile::factory()->create(['content' => "# Changelog\n\n> [!NOTE]\n> Breaking."]);

    expect($file->toHtml(shouldCache: false))
        ->toContain('<h1>Changelog</h1>')
        ->toContain('<div class="callout callout-note" data-callout="note">');
});

it('caches the rendered html per blob sha', function () {
    config()->set('docs.cache.enabled', true);

    $file = ProjectFile::factory()->create(['content' => 'First.', 'blob_sha' => 'sha-1']);

    expect($file->toHtml())->toContain('First.');

    $file->update(['content' => 'Second.']);
    expect($file->toHtml())->toContain('First.');

    $file->update(['blob_sha' => 'sha-2']);
    expect($file->toHtml())->toContain('Second.');
});

it('finds a file by path regardless of case, loaded or not', function () {
    $project = Project::factory()->create();
    ProjectFile::factory()->for($project)->create(['path' => 'README.md', 'content' => 'Intro.']);

    expect($project->file('readme.md')?->content)->toBe('Intro.')
        ->and($project->load('files')->file('/ReadMe.MD')?->content)->toBe('Intro.')
        ->and($project->file('CHANGELOG.md'))->toBeNull();
});
