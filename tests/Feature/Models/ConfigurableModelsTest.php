<?php

declare(strict_types=1);

use Foxws\Docs\Models\Document;
use Foxws\Docs\Models\Project;
use Foxws\Docs\Models\Version;

class TestProject extends Project {}

class TestVersion extends Version {}

class TestDocument extends Document {}

it('resolves the base project class by default', function () {
    expect(Project::modelClass())->toBe(Project::class);
});

it('resolves the base version class by default', function () {
    expect(Version::modelClass())->toBe(Version::class);
});

it('resolves the base document class by default', function () {
    expect(Document::modelClass())->toBe(Document::class);
});

it('resolves a configured project class', function () {
    config()->set('docs.models.project', TestProject::class);

    expect(Project::modelClass())->toBe(TestProject::class);
});

it('resolves a configured version class', function () {
    config()->set('docs.models.version', TestVersion::class);

    expect(Version::modelClass())->toBe(TestVersion::class);
});

it('resolves a configured document class', function () {
    config()->set('docs.models.document', TestDocument::class);

    expect(Document::modelClass())->toBe(TestDocument::class);
});

it("uses the configured version class for the project's versions relation", function () {
    config()->set('docs.models.version', TestVersion::class);

    $project = Project::factory()->make();

    expect($project->versions()->getRelated())->toBeInstanceOf(TestVersion::class);
});

it("uses the configured document class for the project's documents relation", function () {
    config()->set('docs.models.document', TestDocument::class);

    $project = Project::factory()->make();

    expect($project->documents()->getRelated())->toBeInstanceOf(TestDocument::class);
});

it("uses the configured project class for the version's project relation", function () {
    config()->set('docs.models.project', TestProject::class);

    $version = Version::factory()->make();

    expect($version->project()->getRelated())->toBeInstanceOf(TestProject::class);
});

it("uses the configured document class for the version's documents relation", function () {
    config()->set('docs.models.document', TestDocument::class);

    $version = Version::factory()->make();

    expect($version->documents()->getRelated())->toBeInstanceOf(TestDocument::class);
});

it("uses the configured version class for the document's version relation", function () {
    config()->set('docs.models.version', TestVersion::class);

    $document = Document::factory()->make();

    expect($document->version()->getRelated())->toBeInstanceOf(TestVersion::class);
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

it('creates the configured version class via docs:versions:add', function () {
    config()->set('docs.models.version', TestVersion::class);

    Project::factory()->create(['slug' => 'laravel-podman']);

    $this->artisan('docs:versions:add', [
        'project' => 'laravel-podman',
        'name' => '1.0.0',
        'ref' => 'v1.0.0',
    ])->assertSuccessful();

    $version = TestVersion::query()->where('name', '1.0.0')->firstOrFail();

    expect($version)->toBeInstanceOf(TestVersion::class);
});
