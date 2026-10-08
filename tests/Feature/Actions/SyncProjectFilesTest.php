<?php

declare(strict_types=1);

use Foxws\Docs\Actions\SyncProjectFiles;
use Foxws\Docs\Enums\DocumentType;
use Foxws\Docs\Models\Document;
use Foxws\Docs\Models\Project;
use Foxws\Docs\Models\Version;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

/**
 * @param  array<string, string>  $files  path => blob sha
 */
function fakeRootTree(array $files): array
{
    return [
        'sha' => 'root-tree-sha',
        'tree' => collect($files)
            ->map(fn (string $sha, string $path): array => ['path' => $path, 'mode' => '100644', 'type' => 'blob', 'sha' => $sha, 'size' => 100, 'url' => '...'])
            ->values()
            ->push(['path' => 'docs', 'mode' => '040000', 'type' => 'tree', 'sha' => 'docs-tree-sha', 'url' => '...'])
            ->all(),
        'truncated' => false,
    ];
}

beforeEach(function () {
    config()->set('docs.sync.additional_files', ['README.md', 'CHANGELOG.md', 'NEWS.md']);
    config()->set('docs.github.retry.times', 1);
});

it('does nothing when no additional files are configured', function () {
    config()->set('docs.sync.additional_files', []);

    $project = Project::factory()->create(['github_repository' => 'foxws/example']);

    app(SyncProjectFiles::class)->handle($project);

    Http::assertNothingSent();
});

it('stores the configured root files at the default version ref, matching paths case-insensitively', function () {
    $project = Project::factory()->create(['github_repository' => 'foxws/example']);
    Version::factory()->create(['project_id' => $project->id, 'ref' => 'v2.0.0', 'is_default' => true]);

    Http::fake([
        'api.github.com/repos/foxws/example/git/trees/v2.0.0*' => Http::response(fakeRootTree([
            'README.md' => 'readme-sha',
            'changelog.md' => 'changelog-sha',
            'docs/README.md' => 'nested-sha',
            'LICENSE.md' => 'license-sha',
        ])),
        'raw.githubusercontent.com/foxws/example/v2.0.0/README.md' => Http::response('# Example'),
        'raw.githubusercontent.com/foxws/example/v2.0.0/changelog.md' => Http::response('## 2.0.0'),
    ]);

    app(SyncProjectFiles::class)->handle($project->load('versions'));

    expect($project->files()->orderBy('source_path')->get(['source_path', 'slug', 'title', 'body', 'blob_sha'])->toArray())->toBe([
        ['source_path' => 'README.md', 'slug' => 'readme', 'title' => 'Readme', 'body' => '# Example', 'blob_sha' => 'readme-sha'],
        ['source_path' => 'changelog.md', 'slug' => 'changelog', 'title' => 'Changelog', 'body' => '## 2.0.0', 'blob_sha' => 'changelog-sha'],
    ]);
});

it('reads the files at HEAD while the project has no versions', function () {
    $project = Project::factory()->create(['github_repository' => 'foxws/example']);

    Http::fake([
        'api.github.com/repos/foxws/example/git/trees/HEAD*' => Http::response(fakeRootTree(['README.md' => 'readme-sha'])),
        'raw.githubusercontent.com/foxws/example/HEAD/README.md' => Http::response('Intro.'),
    ]);

    app(SyncProjectFiles::class)->handle($project);

    $file = $project->file('README.md');

    expect($file?->body)->toBe('Intro.')
        ->and($file?->type)->toBe(DocumentType::File)
        ->and($file?->version_id)->toBeNull()
        ->and($file?->searchable)->toBeFalse();
});

it('only fetches files whose blob sha changed', function () {
    $project = Project::factory()->create(['github_repository' => 'foxws/example']);
    Document::factory()->file('README.md')->for($project)->create(['body' => 'Unchanged.', 'blob_sha' => 'readme-sha']);
    Document::factory()->file('CHANGELOG.md')->for($project)->create(['body' => 'Old.', 'blob_sha' => 'old-sha']);

    Http::fake([
        'api.github.com/repos/foxws/example/git/trees/HEAD*' => Http::response(fakeRootTree([
            'README.md' => 'readme-sha',
            'CHANGELOG.md' => 'new-sha',
        ])),
        'raw.githubusercontent.com/foxws/example/HEAD/CHANGELOG.md' => Http::response('New.'),
    ]);

    app(SyncProjectFiles::class)->handle($project);

    Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/README.md'));

    expect($project->file('README.md')?->body)->toBe('Unchanged.')
        ->and($project->file('CHANGELOG.md')?->body)->toBe('New.');
});

it('deletes stored files the repository no longer has', function () {
    $project = Project::factory()->create(['github_repository' => 'foxws/example']);
    Document::factory()->file('NEWS.md')->for($project)->create(['blob_sha' => 'news-sha']);

    Http::fake([
        'api.github.com/repos/foxws/example/git/trees/HEAD*' => Http::response(fakeRootTree([])),
    ]);

    app(SyncProjectFiles::class)->handle($project);

    expect($project->files()->count())->toBe(0);
});

it('keeps the stored files and reports when the tree fetch fails', function () {
    Exceptions::fake();

    $project = Project::factory()->create(['github_repository' => 'foxws/example']);
    Document::factory()->file('README.md')->for($project)->create(['body' => 'Kept.']);

    Http::fake([
        'api.github.com/repos/foxws/example/git/trees/HEAD*' => Http::response(null, 503),
    ]);

    app(SyncProjectFiles::class)->handle($project);

    expect($project->file('README.md')?->body)->toBe('Kept.');
    Exceptions::assertReported(RequestException::class);
});

it('reports a file that fails to download and still stores the others', function () {
    Exceptions::fake();

    $project = Project::factory()->create(['github_repository' => 'foxws/example']);

    Http::fake([
        'api.github.com/repos/foxws/example/git/trees/HEAD*' => Http::response(fakeRootTree([
            'README.md' => 'readme-sha',
            'CHANGELOG.md' => 'changelog-sha',
        ])),
        'raw.githubusercontent.com/foxws/example/HEAD/README.md' => Http::response(null, 503),
        'raw.githubusercontent.com/foxws/example/HEAD/CHANGELOG.md' => Http::response('## 1.0.0'),
    ]);

    app(SyncProjectFiles::class)->handle($project);

    expect($project->file('README.md'))->toBeNull()
        ->and($project->file('CHANGELOG.md')?->body)->toBe('## 1.0.0');
    Exceptions::assertReported(RequestException::class);
});

it('reads the files of a local project from disk', function () {
    $base = sys_get_temp_dir().'/laravel-docs-files-tests-'.uniqid();
    File::ensureDirectoryExists("{$base}/docs");

    try {
        File::put("{$base}/Readme.md", 'Local intro.');
        File::put("{$base}/docs/index.md", '# Docs');

        $project = Project::factory()->local($base)->create();

        app(SyncProjectFiles::class)->handle($project);

        expect($project->files()->pluck('source_path')->all())->toBe(['Readme.md'])
            ->and($project->file('README.md')?->body)->toBe('Local intro.');
    } finally {
        File::deleteDirectory($base);
    }

    Http::assertNothingSent();
});
