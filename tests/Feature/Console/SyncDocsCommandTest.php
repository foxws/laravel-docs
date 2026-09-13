<?php

declare(strict_types=1);

use Foxws\Docs\Models\Document;
use Foxws\Docs\Models\Project;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

function fakeTreeResponse(array $entries, string $sha = 'root-tree-sha'): array
{
    return [
        'sha' => $sha,
        'tree' => [
            ...$entries,
            ['path' => 'src/Foo.php', 'mode' => '100644', 'type' => 'blob', 'sha' => 'unrelated-sha', 'size' => 200, 'url' => '...'],
        ],
        'truncated' => false,
    ];
}

it('creates new documents from a fresh project', function () {
    Project::factory()->create(['slug' => 'example', 'github_repository' => 'foxws/example']);

    Http::fake([
        'api.github.com/repos/foxws/example/git/trees/main*' => Http::response(fakeTreeResponse([
            ['path' => 'docs/installation.md', 'mode' => '100644', 'type' => 'blob', 'sha' => 'blob-sha-a', 'size' => 512, 'url' => '...'],
            ['path' => 'docs/usage.md', 'mode' => '100644', 'type' => 'blob', 'sha' => 'blob-sha-b', 'size' => 1024, 'url' => '...'],
        ]), 200),
        'raw.githubusercontent.com/foxws/example/main/docs/installation.md' => Http::response("---\ntitle: Installation\norder: 1\n---\n\n# Installation", 200),
        'raw.githubusercontent.com/foxws/example/main/docs/usage.md' => Http::response("---\ntitle: Usage\norder: 2\n---\n\n# Usage", 200),
    ]);

    $this->artisan('docs:sync')->assertSuccessful();

    $this->assertDatabaseCount('documents', 2);

    $project = Project::query()->where('slug', 'example')->firstOrFail();
    expect($project->last_synced_at)->not->toBeNull();
    expect($project->last_synced_sha)->toBe('root-tree-sha');

    $installation = Document::query()->where('source_path', 'docs/installation.md')->firstOrFail();
    expect($installation->title)->toBe('Installation');
    expect($installation->order)->toBe(1);
    expect($installation->blob_sha)->toBe('blob-sha-a');
});

it('updates a document when its blob sha changes', function () {
    $project = Project::factory()->create(['slug' => 'example', 'github_repository' => 'foxws/example']);
    $project->documents()->create([
        'slug' => 'installation',
        'title' => 'Installation (old)',
        'body' => '<p>old</p>',
        'source_path' => 'docs/installation.md',
        'blob_sha' => 'old-sha',
    ]);

    Http::fake([
        'api.github.com/repos/foxws/example/git/trees/main*' => Http::response(fakeTreeResponse([
            ['path' => 'docs/installation.md', 'mode' => '100644', 'type' => 'blob', 'sha' => 'new-sha', 'size' => 512, 'url' => '...'],
        ]), 200),
        'raw.githubusercontent.com/foxws/example/main/docs/installation.md' => Http::response("---\ntitle: Installation\n---\n\n# New content", 200),
    ]);

    $this->artisan('docs:sync')->assertSuccessful();

    $this->assertDatabaseCount('documents', 1);

    $document = Document::query()->where('source_path', 'docs/installation.md')->firstOrFail();
    expect($document->blob_sha)->toBe('new-sha');
    expect($document->body)->toContain('New content');
});

it('skips fetching raw content when the blob sha is unchanged', function () {
    $project = Project::factory()->create(['slug' => 'example', 'github_repository' => 'foxws/example']);
    $project->documents()->create([
        'slug' => 'usage',
        'title' => 'Usage',
        'body' => '<p>unchanged</p>',
        'source_path' => 'docs/usage.md',
        'blob_sha' => 'blob-sha-b',
    ]);

    Http::fake([
        'api.github.com/repos/foxws/example/git/trees/main*' => Http::response(fakeTreeResponse([
            ['path' => 'docs/usage.md', 'mode' => '100644', 'type' => 'blob', 'sha' => 'blob-sha-b', 'size' => 1024, 'url' => '...'],
        ]), 200),
        'raw.githubusercontent.com/foxws/example/main/docs/usage.md' => Http::response('should not be fetched', 200),
    ]);

    $this->artisan('docs:sync')->assertSuccessful();

    Http::assertNotSent(fn ($request) => str_contains((string) $request->url(), 'usage.md'));
});

it('prunes documents whose source path is no longer present remotely', function () {
    $project = Project::factory()->create(['slug' => 'example', 'github_repository' => 'foxws/example']);
    $project->documents()->create([
        'slug' => 'removed',
        'title' => 'Removed',
        'body' => '<p>gone</p>',
        'source_path' => 'docs/removed.md',
        'blob_sha' => 'removed-sha',
    ]);

    Http::fake([
        'api.github.com/repos/foxws/example/git/trees/main*' => Http::response(fakeTreeResponse([]), 200),
    ]);

    $this->artisan('docs:sync')->assertSuccessful();

    $this->assertDatabaseCount('documents', 0);
});

it('does not prune when prune_missing is disabled', function () {
    config()->set('docs.sync.prune_missing', false);

    $project = Project::factory()->create(['slug' => 'example', 'github_repository' => 'foxws/example']);
    $project->documents()->create([
        'slug' => 'orphan',
        'title' => 'Orphan',
        'body' => '<p>still here</p>',
        'source_path' => 'docs/orphan.md',
        'blob_sha' => 'orphan-sha',
    ]);

    Http::fake([
        'api.github.com/repos/foxws/example/git/trees/main*' => Http::response(fakeTreeResponse([]), 200),
    ]);

    $this->artisan('docs:sync')->assertSuccessful();

    $this->assertDatabaseCount('documents', 1);
});

it('does not update last_synced_at when the sync fails partway', function () {
    $project = Project::factory()->create([
        'slug' => 'example',
        'github_repository' => 'foxws/example',
        'last_synced_at' => null,
        'last_synced_sha' => null,
    ]);

    Http::fake([
        'api.github.com/repos/foxws/example/git/trees/main*' => Http::response(fakeTreeResponse([
            ['path' => 'docs/installation.md', 'mode' => '100644', 'type' => 'blob', 'sha' => 'blob-sha-a', 'size' => 512, 'url' => '...'],
        ]), 200),
        'raw.githubusercontent.com/foxws/example/main/docs/installation.md' => Http::response('server error', 500),
    ]);

    try {
        $this->artisan('docs:sync')->run();
    } catch (RequestException) {
        // v1 behavior: an uncaught exception on one project aborts the whole command.
    }

    $project->refresh();
    expect($project->last_synced_at)->toBeNull();
    expect($project->last_synced_sha)->toBeNull();
});
