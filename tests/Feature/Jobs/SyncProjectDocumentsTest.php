<?php

declare(strict_types=1);

use Foxws\Docs\Jobs\SyncProjectDocuments;
use Foxws\Docs\Models\Document;
use Foxws\Docs\Models\Project;
use Foxws\Docs\Models\Version;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // These tests exercise the job's sync, not version auto-discovery.
    Http::fake(['api.github.com/repos/*/releases/latest' => Http::response(null, 404)]);
});

it('syncs every version of the project matching its slug', function () {
    $project = Project::factory()->create(['slug' => 'example', 'github_repository' => 'foxws/example']);
    Version::factory()->create(['project_id' => $project->id, 'ref' => 'main']);

    Http::fake([
        'api.github.com/repos/foxws/example/git/trees/main*' => Http::response([
            'sha' => 'root-tree-sha',
            'tree' => [
                ['path' => 'docs/installation.md', 'mode' => '100644', 'type' => 'blob', 'sha' => 'blob-sha-a', 'size' => 512, 'url' => '...'],
            ],
            'truncated' => false,
        ], 200),
        'raw.githubusercontent.com/foxws/example/main/docs/installation.md' => Http::response("---\ntitle: Installation\n---\n\n# Installation", 200),
    ]);

    $this->app->call([new SyncProjectDocuments('example'), 'handle']);

    $this->assertDatabaseCount('documents', 1);

    $document = Document::query()->where('source_path', 'docs/installation.md')->firstOrFail();
    expect($document->title)->toBe('Installation');
});

it('no-ops when the project slug no longer resolves to a registered project', function () {
    $this->app->call([new SyncProjectDocuments('missing'), 'handle']);

    $this->assertDatabaseCount('documents', 0);
});

it('is keyed for overlap protection by its own project slug, not shared with other projects', function () {
    $middleware = (new SyncProjectDocuments('example'))->middleware();

    expect($middleware)->toHaveCount(1);
    expect($middleware[0])->toBeInstanceOf(WithoutOverlapping::class);
    expect($middleware[0]->key)->toBe('example');

    $otherMiddleware = (new SyncProjectDocuments('other'))->middleware();

    expect($otherMiddleware[0]->key)->toBe('other');
});
