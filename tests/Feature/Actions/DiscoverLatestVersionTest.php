<?php

declare(strict_types=1);

use Foxws\Docs\Actions\DiscoverLatestVersion;
use Foxws\Docs\Models\Project;
use Foxws\Docs\Models\Version;
use Illuminate\Support\Facades\Http;

it('registers the latest release as the default version', function () {
    config()->set('docs.sync.auto_discover_versions', true);

    $project = Project::factory()->create(['github_repository' => 'foxws/example']);

    Http::fake([
        'api.github.com/repos/foxws/example/releases/latest' => Http::response(['tag_name' => 'v2.0.0'], 200),
    ]);

    $version = app(DiscoverLatestVersion::class)->handle($project);

    expect($version->name)->toBe('2.0.0')
        ->and($version->ref)->toBe('v2.0.0')
        ->and($version->is_default)->toBeTrue();

    $this->assertDatabaseCount('versions', 1);
});

it('unmarks the previous default when a new release is discovered', function () {
    config()->set('docs.sync.auto_discover_versions', true);

    $project = Project::factory()->create(['github_repository' => 'foxws/example']);
    $previousDefault = Version::factory()->create(['project_id' => $project->id, 'name' => '1.0.0', 'is_default' => true]);

    Http::fake([
        'api.github.com/repos/foxws/example/releases/latest' => Http::response(['tag_name' => 'v2.0.0'], 200),
    ]);

    app(DiscoverLatestVersion::class)->handle($project);

    expect($previousDefault->refresh()->is_default)->toBeFalse();
    $this->assertDatabaseCount('versions', 2);
});

it('updates the ref when the latest release already exists as a version', function () {
    config()->set('docs.sync.auto_discover_versions', true);

    $project = Project::factory()->create(['github_repository' => 'foxws/example']);
    $existing = Version::factory()->create(['project_id' => $project->id, 'name' => '2.0.0', 'ref' => 'old-sha']);

    Http::fake([
        'api.github.com/repos/foxws/example/releases/latest' => Http::response(['tag_name' => 'v2.0.0'], 200),
    ]);

    app(DiscoverLatestVersion::class)->handle($project);

    expect($existing->refresh()->ref)->toBe('v2.0.0');
    $this->assertDatabaseCount('versions', 1);
});

it('does nothing when auto-discovery is disabled', function () {
    config()->set('docs.sync.auto_discover_versions', false);

    $project = Project::factory()->create();

    Http::fake();

    $version = app(DiscoverLatestVersion::class)->handle($project);

    expect($version)->toBeNull();
    Http::assertNothingSent();
});

it('does nothing when the repository has no releases', function () {
    config()->set('docs.sync.auto_discover_versions', true);

    $project = Project::factory()->create(['github_repository' => 'foxws/example']);

    Http::fake([
        'api.github.com/repos/foxws/example/releases/latest' => Http::response(null, 404),
    ]);

    $version = app(DiscoverLatestVersion::class)->handle($project);

    expect($version)->toBeNull();
    $this->assertDatabaseCount('versions', 0);
});
