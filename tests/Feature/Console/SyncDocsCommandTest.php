<?php

declare(strict_types=1);

use Foxws\Docs\Exceptions\EmptySourceTreeException;
use Foxws\Docs\Models\Document;
use Foxws\Docs\Models\Project;
use Foxws\Docs\Models\Version;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Exceptions;
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

function fakeVersion(array $attributes = []): Version
{
    $project = Project::factory()->create(['slug' => 'example', 'github_repository' => 'foxws/example']);

    return Version::factory()->create([...['project_id' => $project->id, 'ref' => 'main'], ...$attributes]);
}

beforeEach(function () {
    // These tests exercise SyncVersionDocuments, not version auto-discovery
    // (see DiscoverLatestVersionTest) — fake "no releases" so docs:sync's
    // discovery step no-ops instead of making a real GitHub API call.
    Http::fake(['api.github.com/repos/*/releases/latest' => Http::response(null, 404)]);
});

it('creates new documents from a fresh project', function () {
    fakeVersion();

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

    $version = Version::query()->firstOrFail();
    expect($version->last_synced_at)->not->toBeNull();
    expect($version->last_synced_sha)->toBe('root-tree-sha');

    $installation = Document::query()->where('source_path', 'docs/installation.md')->firstOrFail();
    expect($installation->title)->toBe('Installation');
    expect($installation->order)->toBe(1);
    expect($installation->blob_sha)->toBe('blob-sha-a');
});

it('collapses stray whitespace in a front matter title and section', function () {
    fakeVersion();

    $rawContent = "---\ntitle: \"Getting\\n  Started\"\nsection: \"Getting\\n  Started\"\norder: 1\n---\n\n# Getting Started";

    Http::fake([
        'api.github.com/repos/foxws/example/git/trees/main*' => Http::response(fakeTreeResponse([
            ['path' => 'docs/getting-started.md', 'mode' => '100644', 'type' => 'blob', 'sha' => 'blob-sha-a', 'size' => 512, 'url' => '...'],
        ]), 200),
        'raw.githubusercontent.com/foxws/example/main/docs/getting-started.md' => Http::response($rawContent, 200),
    ]);

    $this->artisan('docs:sync')->assertSuccessful();

    $document = Document::query()->where('source_path', 'docs/getting-started.md')->firstOrFail();
    expect($document->title)->toBe('Getting Started');
    expect($document->section)->toBe('Getting Started');
});

it('updates a document when its blob sha changes', function () {
    $version = fakeVersion();
    $version->documents()->create([
        'slug' => 'installation',
        'title' => 'Installation (old)',
        'body' => 'old',
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
    $version = fakeVersion();
    $version->documents()->create([
        'slug' => 'usage',
        'title' => 'Usage',
        'body' => 'unchanged',
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
    $version = fakeVersion();
    $version->documents()->create([
        'slug' => 'removed',
        'title' => 'Removed',
        'body' => 'gone',
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

    $version = fakeVersion();
    $version->documents()->create([
        'slug' => 'orphan',
        'title' => 'Orphan',
        'body' => 'still here',
        'source_path' => 'docs/orphan.md',
        'blob_sha' => 'orphan-sha',
    ]);

    Http::fake([
        'api.github.com/repos/foxws/example/git/trees/main*' => Http::response(fakeTreeResponse([]), 200),
    ]);

    $this->artisan('docs:sync')->assertSuccessful();

    $this->assertDatabaseCount('documents', 1);
});

it('skips a version instead of pruning it when the raw git tree comes back completely empty', function () {
    $version = fakeVersion(['last_synced_at' => null, 'last_synced_sha' => null]);
    $version->documents()->create([
        'slug' => 'installation',
        'title' => 'Installation',
        'body' => 'still here',
        'source_path' => 'docs/installation.md',
        'blob_sha' => 'installation-sha',
    ]);

    Http::fake([
        'api.github.com/repos/foxws/example/git/trees/main*' => Http::response([
            'sha' => 'root-tree-sha',
            'tree' => [],
            'truncated' => false,
        ], 200),
    ]);

    Exceptions::fake();

    $this->artisan('docs:sync')
        ->expectsOutputToContain('SKIPPED')
        ->assertSuccessful();

    $this->assertDatabaseCount('documents', 1);

    $version->refresh();
    expect($version->last_synced_at)->toBeNull();
    expect($version->last_synced_sha)->toBeNull();

    Exceptions::assertReported(EmptySourceTreeException::class);
});

it('does not update last_synced_at when the sync fails partway', function () {
    $version = fakeVersion(['last_synced_at' => null, 'last_synced_sha' => null]);

    Http::fake([
        'api.github.com/repos/foxws/example/git/trees/main*' => Http::response(fakeTreeResponse([
            ['path' => 'docs/installation.md', 'mode' => '100644', 'type' => 'blob', 'sha' => 'blob-sha-a', 'size' => 512, 'url' => '...'],
        ]), 200),
        'raw.githubusercontent.com/foxws/example/main/docs/installation.md' => Http::response('server error', 500),
    ]);

    try {
        $this->artisan('docs:sync')->run();
    } catch (RequestException) {
        // v1 behavior: an uncaught exception on one version aborts the whole command.
    }

    $version->refresh();
    expect($version->last_synced_at)->toBeNull();
    expect($version->last_synced_sha)->toBeNull();
});

it("promotes the index document's metadata front matter onto the project", function () {
    $version = fakeVersion();

    Http::fake([
        'api.github.com/repos/foxws/example/git/trees/main*' => Http::response(fakeTreeResponse([
            ['path' => 'docs/index.md', 'mode' => '100644', 'type' => 'blob', 'sha' => 'blob-sha-a', 'size' => 512, 'url' => '...'],
        ]), 200),
        'raw.githubusercontent.com/foxws/example/main/docs/index.md' => Http::response(
            "---\nslug: /\nmetadata:\n  role: CONTAINERS\n  eyebrow: 'CONTAINERS · ROOTLESS'\n---\n\n# Introduction",
            200,
        ),
    ]);

    $this->artisan('docs:sync')->assertSuccessful();

    expect($version->project->refresh()->metadata->getArrayCopy())->toBe([
        'role' => 'CONTAINERS',
        'eyebrow' => 'CONTAINERS · ROOTLESS',
    ]);
});

it('leaves project metadata alone for documents other than the index', function () {
    $version = fakeVersion();

    Http::fake([
        'api.github.com/repos/foxws/example/git/trees/main*' => Http::response(fakeTreeResponse([
            ['path' => 'docs/installation.md', 'mode' => '100644', 'type' => 'blob', 'sha' => 'blob-sha-a', 'size' => 512, 'url' => '...'],
        ]), 200),
        'raw.githubusercontent.com/foxws/example/main/docs/installation.md' => Http::response(
            "---\nmetadata:\n  role: SHOULD NOT APPLY\n---\n\n# Installation",
            200,
        ),
    ]);

    $this->artisan('docs:sync')->assertSuccessful();

    expect($version->project->refresh()->metadata)->toBeNull();
});

it('sets project metadata to null when the index document declares none', function () {
    $project = Project::factory()->create(['slug' => 'example', 'github_repository' => 'foxws/example', 'metadata' => ['role' => 'STALE']]);
    $version = Version::factory()->create(['project_id' => $project->id, 'ref' => 'main']);

    Http::fake([
        'api.github.com/repos/foxws/example/git/trees/main*' => Http::response(fakeTreeResponse([
            ['path' => 'docs/index.md', 'mode' => '100644', 'type' => 'blob', 'sha' => 'blob-sha-a', 'size' => 512, 'url' => '...'],
        ]), 200),
        'raw.githubusercontent.com/foxws/example/main/docs/index.md' => Http::response('# Introduction, no front matter', 200),
    ]);

    $this->artisan('docs:sync')->assertSuccessful();

    expect($version->project->refresh()->metadata)->toBeNull();
});

it('does not re-derive metadata when the index document is unchanged', function () {
    $version = fakeVersion();
    $version->documents()->create([
        'slug' => 'index',
        'title' => 'Introduction',
        'body' => 'unchanged',
        'source_path' => 'docs/index.md',
        'blob_sha' => 'unchanged-sha',
    ]);
    $version->project->update(['metadata' => ['role' => 'PRESERVED']]);

    Http::fake([
        'api.github.com/repos/foxws/example/git/trees/main*' => Http::response(fakeTreeResponse([
            ['path' => 'docs/index.md', 'mode' => '100644', 'type' => 'blob', 'sha' => 'unchanged-sha', 'size' => 512, 'url' => '...'],
        ]), 200),
        'raw.githubusercontent.com/foxws/example/main/docs/index.md' => Http::response('should not be fetched', 200),
    ]);

    $this->artisan('docs:sync')->assertSuccessful();

    expect($version->project->refresh()->metadata->getArrayCopy())->toBe(['role' => 'PRESERVED']);
});

it('only syncs the project matching --project', function () {
    $projectA = Project::factory()->create(['slug' => 'example-a', 'github_repository' => 'foxws/example-a']);
    Version::factory()->create(['project_id' => $projectA->id, 'ref' => 'main']);

    $projectB = Project::factory()->create(['slug' => 'example-b', 'github_repository' => 'foxws/example-b']);
    Version::factory()->create(['project_id' => $projectB->id, 'ref' => 'main']);

    Http::fake([
        'api.github.com/repos/foxws/example-a/git/trees/main*' => Http::response(fakeTreeResponse([
            ['path' => 'docs/installation.md', 'mode' => '100644', 'type' => 'blob', 'sha' => 'blob-sha-a', 'size' => 512, 'url' => '...'],
        ]), 200),
        'raw.githubusercontent.com/foxws/example-a/main/docs/installation.md' => Http::response('# Installation', 200),
    ]);

    $this->artisan('docs:sync', ['--project' => 'example-a'])->assertSuccessful();

    $this->assertDatabaseCount('documents', 1);
    Http::assertNotSent(fn ($request) => str_contains((string) $request->url(), 'example-b'));
});

it('fails when --project does not match a registered slug', function () {
    $this->artisan('docs:sync', ['--project' => 'missing'])->assertFailed();
});
