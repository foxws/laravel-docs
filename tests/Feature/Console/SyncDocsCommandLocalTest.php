<?php

declare(strict_types=1);

use Foxws\Docs\Models\Document;
use Foxws\Docs\Models\Project;
use Foxws\Docs\Models\Version;
use Illuminate\Support\Facades\File;

/**
 * Builds a fresh local docs fixture, hands its base path to $callback, and
 * deletes it afterwards regardless of the test's outcome.
 */
function withLocalProjectFixture(Closure $callback): mixed
{
    $base = sys_get_temp_dir().'/laravel-docs-sync-tests-'.uniqid();

    File::ensureDirectoryExists("{$base}/docs");

    try {
        return $callback($base);
    } finally {
        File::deleteDirectory($base);
    }
}

it('syncs documents from a local project with no HTTP calls at all', function () {
    withLocalProjectFixture(function (string $base) {
        File::put("{$base}/docs/installation.md", "---\ntitle: Installation\norder: 1\n---\n\n# Installation");

        $project = Project::factory()->local($base)->create(['slug' => 'stry']);
        Version::factory()->create(['project_id' => $project->id, 'ref' => 'unused']);

        $this->artisan('docs:sync')->assertSuccessful();

        $this->assertDatabaseCount('documents', 1);

        $document = Document::query()->where('source_path', 'docs/installation.md')->firstOrFail();
        expect($document->title)->toBe('Installation')
            ->and($document->order)->toBe(1);
    });
});

it('updates a local document when its content changes', function () {
    withLocalProjectFixture(function (string $base) {
        File::put("{$base}/docs/installation.md", '# Old content');

        $project = Project::factory()->local($base)->create(['slug' => 'stry']);
        Version::factory()->create(['project_id' => $project->id, 'ref' => 'unused']);

        $this->artisan('docs:sync')->assertSuccessful();

        File::put("{$base}/docs/installation.md", '# New content');

        $this->artisan('docs:sync')->assertSuccessful();

        $this->assertDatabaseCount('documents', 1);

        $document = Document::query()->where('source_path', 'docs/installation.md')->firstOrFail();
        expect($document->body)->toContain('New content');
    });
});

it('prunes local documents whose file was deleted', function () {
    withLocalProjectFixture(function (string $base) {
        File::put("{$base}/docs/installation.md", '# Installation');
        File::put("{$base}/docs/usage.md", '# Usage');

        $project = Project::factory()->local($base)->create(['slug' => 'stry']);
        Version::factory()->create(['project_id' => $project->id, 'ref' => 'unused']);

        $this->artisan('docs:sync')->assertSuccessful();
        $this->assertDatabaseCount('documents', 2);

        File::delete("{$base}/docs/usage.md");

        $this->artisan('docs:sync')->assertSuccessful();

        $this->assertDatabaseCount('documents', 1);
        expect(Document::query()->where('source_path', 'docs/usage.md')->exists())->toBeFalse();
    });
});

it('does not auto-discover a version for a local project — it must be registered explicitly', function () {
    withLocalProjectFixture(function (string $base) {
        File::put("{$base}/docs/installation.md", '# Installation');

        Project::factory()->local($base)->create(['slug' => 'stry']);

        $this->artisan('docs:sync')->assertSuccessful();

        $this->assertDatabaseCount('versions', 0);
        $this->assertDatabaseCount('documents', 0);
    });
});
