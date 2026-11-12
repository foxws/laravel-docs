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

it('stores the front matter slug without surrounding slashes', function (string $frontMatterSlug, string $expected) {
    fakeVersion();

    Http::fake([
        'api.github.com/repos/foxws/example/git/trees/main*' => Http::response(fakeTreeResponse([
            ['path' => 'docs/index.md', 'mode' => '100644', 'type' => 'blob', 'sha' => 'blob-sha-a', 'size' => 512, 'url' => '...'],
        ]), 200),
        'raw.githubusercontent.com/foxws/example/main/docs/index.md' => Http::response("---\nslug: {$frontMatterSlug}\n---\n\n# Introduction", 200),
    ]);

    $this->artisan('docs:sync')->assertSuccessful();

    expect(Document::query()->where('source_path', 'docs/index.md')->value('slug'))->toBe($expected);
})->with([
    'the docs root falls back to the file name' => ['/', 'index'],
    'a leading slash' => ['/introduction', 'introduction'],
    'a trailing slash' => ['introduction/', 'introduction'],
    'no slashes' => ['introduction', 'introduction'],
]);

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
    config()->set('docs.github.retry.times', 1);

    $version = fakeVersion(['last_synced_at' => null, 'last_synced_sha' => null]);

    Http::fake([
        'api.github.com/repos/foxws/example/git/trees/main*' => Http::response(fakeTreeResponse([
            ['path' => 'docs/installation.md', 'mode' => '100644', 'type' => 'blob', 'sha' => 'blob-sha-a', 'size' => 512, 'url' => '...'],
        ]), 200),
        'raw.githubusercontent.com/foxws/example/main/docs/installation.md' => Http::response('server error', 500),
    ]);

    Exceptions::fake();

    $this->artisan('docs:sync')->assertFailed();

    $version->refresh();
    expect($version->last_synced_at)->toBeNull();
    expect($version->last_synced_sha)->toBeNull();

    Exceptions::assertReported(RequestException::class);
});

it('reports a version that fails to sync and still syncs the next project', function () {
    config()->set('docs.github.retry.times', 1);

    fakeVersion();
    $other = Project::factory()->create(['slug' => 'other', 'github_repository' => 'foxws/other']);
    Version::factory()->create(['project_id' => $other->id, 'ref' => 'main']);

    Http::fake([
        'api.github.com/repos/foxws/example/git/trees/main*' => Http::response(['message' => 'Forbidden'], 403),
        'api.github.com/repos/foxws/other/git/trees/main*' => Http::response(fakeTreeResponse([
            ['path' => 'docs/installation.md', 'mode' => '100644', 'type' => 'blob', 'sha' => 'blob-sha-a', 'size' => 512, 'url' => '...'],
        ]), 200),
        'raw.githubusercontent.com/foxws/other/main/docs/installation.md' => Http::response('# Installation', 200),
    ]);

    Exceptions::fake();

    $this->artisan('docs:sync')
        ->expectsOutputToContain('FAIL')
        ->assertFailed();

    expect(Document::query()->pluck('source_path')->all())->toBe(['docs/installation.md']);

    Exceptions::assertReported(RequestException::class);
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

it('stores each project root file', function () {
    config()->set('docs.sync.additional_files', ['README.md']);

    fakeVersion();

    Http::fake([
        'api.github.com/repos/foxws/example/git/trees/main*' => Http::response(fakeTreeResponse([
            ['path' => 'README.md', 'mode' => '100644', 'type' => 'blob', 'sha' => 'readme-sha', 'size' => 64, 'url' => '...'],
        ]), 200),
        'raw.githubusercontent.com/foxws/example/main/README.md' => Http::response('Intro.', 200),
    ]);

    $this->artisan('docs:sync')->assertSuccessful();

    expect(Project::query()->firstOrFail()->file('README.md')?->body)->toBe('Intro.');
});

it('does not prune documents missing from a truncated git tree', function () {
    $version = fakeVersion();
    $version->documents()->create([
        'slug' => 'installation',
        'title' => 'Installation',
        'body' => 'still here',
        'source_path' => 'docs/installation.md',
        'blob_sha' => 'installation-sha',
    ]);

    Http::fake([
        'api.github.com/repos/foxws/example/git/trees/main*' => Http::response([
            ...fakeTreeResponse([]),
            'truncated' => true,
        ], 200),
    ]);

    $this->artisan('docs:sync')->assertSuccessful();

    $this->assertDatabaseHas('documents', ['source_path' => 'docs/installation.md']);
});

it('casts non-string front matter values instead of failing the sync', function () {
    fakeVersion();

    Http::fake([
        'api.github.com/repos/foxws/example/git/trees/main*' => Http::response(fakeTreeResponse([
            ['path' => 'docs/releases.md', 'mode' => '100644', 'type' => 'blob', 'sha' => 'blob-sha-a', 'size' => 512, 'url' => '...'],
        ]), 200),
        'raw.githubusercontent.com/foxws/example/main/docs/releases.md' => Http::response("---\ntitle: 2024\nsection: [a, b]\norder: first\nseo: none\n---\n\n# Releases", 200),
    ]);

    $this->artisan('docs:sync')->assertSuccessful();

    $document = Document::query()->where('source_path', 'docs/releases.md')->firstOrFail();
    expect($document->title)->toBe('2024');
    expect($document->section)->toBeNull();
    expect($document->order)->toBe(0);
    expect($document->seo)->toBeNull();
});

it('percent-encodes each path segment when fetching raw content', function () {
    fakeVersion();

    Http::fake([
        'api.github.com/repos/foxws/example/git/trees/main*' => Http::response(fakeTreeResponse([
            ['path' => 'docs/what is #new.md', 'mode' => '100644', 'type' => 'blob', 'sha' => 'blob-sha-a', 'size' => 512, 'url' => '...'],
        ]), 200),
        'raw.githubusercontent.com/foxws/example/main/docs/what%20is%20%23new.md' => Http::response('# New', 200),
    ]);

    $this->artisan('docs:sync')->assertSuccessful();

    $this->assertDatabaseHas('documents', ['source_path' => 'docs/what is #new.md', 'body' => '# New']);
});

it('gives a file whose slug is already taken in its version a path-derived slug', function () {
    fakeVersion();

    Http::fake([
        'api.github.com/repos/foxws/example/git/trees/main*' => Http::response(fakeTreeResponse([
            ['path' => 'docs/guides/intro.md', 'mode' => '100644', 'type' => 'blob', 'sha' => 'blob-sha-a', 'size' => 512, 'url' => '...'],
            ['path' => 'docs/intro.md', 'mode' => '100644', 'type' => 'blob', 'sha' => 'blob-sha-b', 'size' => 512, 'url' => '...'],
            ['path' => 'docs/reference/intro.md', 'mode' => '100644', 'type' => 'blob', 'sha' => 'blob-sha-c', 'size' => 512, 'url' => '...'],
            ['path' => 'docs/start.md', 'mode' => '100644', 'type' => 'blob', 'sha' => 'blob-sha-d', 'size' => 512, 'url' => '...'],
            ['path' => 'docs/x/guides/intro.md', 'mode' => '100644', 'type' => 'blob', 'sha' => 'blob-sha-e', 'size' => 512, 'url' => '...'],
            ['path' => 'docs/x/guides-intro.md', 'mode' => '100644', 'type' => 'blob', 'sha' => 'blob-sha-f', 'size' => 512, 'url' => '...'],
        ]), 200),
        'raw.githubusercontent.com/foxws/example/main/docs/x/guides/intro.md' => Http::response("---\nslug: guides-intro\n---\n\n# Clash", 200),
        'raw.githubusercontent.com/foxws/example/main/docs/x/guides-intro.md' => Http::response("---\nslug: guides-intro\n---\n\n# Clash", 200),
        'raw.githubusercontent.com/foxws/example/main/docs/guides/intro.md' => Http::response('# Guides', 200),
        'raw.githubusercontent.com/foxws/example/main/docs/intro.md' => Http::response('# Intro', 200),
        'raw.githubusercontent.com/foxws/example/main/docs/reference/intro.md' => Http::response('# Reference', 200),
        'raw.githubusercontent.com/foxws/example/main/docs/start.md' => Http::response("---\nslug: intro\n---\n\n# Start", 200),
    ]);

    $this->artisan('docs:sync')->assertSuccessful();

    expect(Document::query()->pluck('slug', 'source_path')->all())->toEqual([
        'docs/guides/intro.md' => 'guides-intro',
        'docs/intro.md' => 'intro',
        'docs/reference/intro.md' => 'reference-intro',
        'docs/start.md' => 'start',
        'docs/x/guides-intro.md' => 'x-guides-intro',
        'docs/x/guides/intro.md' => 'x-guides-intro-2',
    ]);
});

it('keeps the slug of an unchanged document when another file claims it', function () {
    $version = fakeVersion();
    $version->documents()->create([
        'slug' => 'intro',
        'title' => 'Intro',
        'body' => '# Intro',
        'source_path' => 'docs/intro.md',
        'blob_sha' => 'intro-sha',
    ]);

    Http::fake([
        'api.github.com/repos/foxws/example/git/trees/main*' => Http::response(fakeTreeResponse([
            ['path' => 'docs/guides/intro.md', 'mode' => '100644', 'type' => 'blob', 'sha' => 'blob-sha-a', 'size' => 512, 'url' => '...'],
            ['path' => 'docs/intro.md', 'mode' => '100644', 'type' => 'blob', 'sha' => 'intro-sha', 'size' => 512, 'url' => '...'],
        ]), 200),
        'raw.githubusercontent.com/foxws/example/main/docs/guides/intro.md' => Http::response('# Guides', 200),
    ]);

    $this->artisan('docs:sync')->assertSuccessful();

    expect(Document::query()->pluck('slug', 'source_path')->all())->toEqual([
        'docs/intro.md' => 'intro',
        'docs/guides/intro.md' => 'guides-intro',
    ]);
});

it('reads a front matter searchable flag written as a word', function (string $value, bool $expected) {
    fakeVersion();

    Http::fake([
        'api.github.com/repos/foxws/example/git/trees/main*' => Http::response(fakeTreeResponse([
            ['path' => 'docs/internal.md', 'mode' => '100644', 'type' => 'blob', 'sha' => 'blob-sha-a', 'size' => 512, 'url' => '...'],
        ]), 200),
        'raw.githubusercontent.com/foxws/example/main/docs/internal.md' => Http::response("---\nsearchable: {$value}\n---\n\n# Internal", 200),
    ]);

    $this->artisan('docs:sync')->assertSuccessful();

    expect(Document::query()->firstOrFail()->searchable)->toBe($expected);
})->with([
    ['no', false],
    ['off', false],
    ['"false"', false],
    ['0', false],
    ['yes', true],
    ['nonsense', true],
]);
