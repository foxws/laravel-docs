<?php

declare(strict_types=1);

use Foxws\Docs\Models\Document;
use Foxws\Docs\Models\Project;

class TestProject extends Project {}

class TestDocument extends Document {}

it('resolves the base project class by default', function () {
    expect(Project::getProjectClassName())->toBe(Project::class);
});

it('resolves the base document class by default', function () {
    expect(Document::getDocumentClassName())->toBe(Document::class);
});

it('resolves a configured project class', function () {
    config()->set('docs.models.project', TestProject::class);

    expect(Project::getProjectClassName())->toBe(TestProject::class);
});

it('resolves a configured document class', function () {
    config()->set('docs.models.document', TestDocument::class);

    expect(Document::getDocumentClassName())->toBe(TestDocument::class);
});

it("uses the configured project class for the document's project relation", function () {
    config()->set('docs.models.project', TestProject::class);

    $document = Document::factory()->make();

    expect($document->project()->getRelated())->toBeInstanceOf(TestProject::class);
});

it("uses the configured document class for the project's documents relation", function () {
    config()->set('docs.models.document', TestDocument::class);

    $project = Project::factory()->make();

    expect($project->documents()->getRelated())->toBeInstanceOf(TestDocument::class);
});

it('creates the configured project class via docs:projects:add', function () {
    config()->set('docs.models.project', TestProject::class);

    $this->artisan('docs:projects:add', [
        'slug' => 'laravel-podman',
        'title' => 'Laravel Podman',
        'github_repository' => 'foxws/laravel-podman',
    ])->assertSuccessful();

    $project = TestProject::query()->where('slug', 'laravel-podman')->firstOrFail();

    expect($project)->toBeInstanceOf(TestProject::class);
});
