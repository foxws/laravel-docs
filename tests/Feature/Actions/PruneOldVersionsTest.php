<?php

declare(strict_types=1);

use Foxws\Docs\Actions\PruneOldVersions;
use Foxws\Docs\Models\Project;
use Foxws\Docs\Models\Version;

it('keeps everything when keep_versions is 0', function () {
    config()->set('docs.sync.keep_versions', 0);

    $project = Project::factory()->create();
    Version::factory()->count(5)->create(['project_id' => $project->id]);

    app(PruneOldVersions::class)->handle($project);

    $this->assertDatabaseCount('versions', 5);
});

it('keeps only the most recently created non-default versions up to the limit', function () {
    config()->set('docs.sync.keep_versions', 2);

    $project = Project::factory()->create();
    $oldest = Version::factory()->create(['project_id' => $project->id, 'name' => 'oldest']);
    $middle = Version::factory()->create(['project_id' => $project->id, 'name' => 'middle']);
    $newest = Version::factory()->create(['project_id' => $project->id, 'name' => 'newest']);

    app(PruneOldVersions::class)->handle($project);

    $this->assertDatabaseCount('versions', 2);
    $this->assertModelMissing($oldest);
    $this->assertModelExists($middle);
    $this->assertModelExists($newest);
});

it('never prunes the default version, even if it is the oldest', function () {
    config()->set('docs.sync.keep_versions', 1);

    $project = Project::factory()->create();
    $oldDefault = Version::factory()->create([
        'project_id' => $project->id,
        'name' => 'old-default',
        'is_default' => true,
    ]);
    $newer = Version::factory()->create(['project_id' => $project->id, 'name' => 'newer']);

    app(PruneOldVersions::class)->handle($project);

    $this->assertModelExists($oldDefault);
    $this->assertModelExists($newer);
    $this->assertDatabaseCount('versions', 2);
});

it('deletes the pruned version\'s documents along with it', function () {
    config()->set('docs.sync.keep_versions', 1);

    $project = Project::factory()->create();
    $old = Version::factory()->create(['project_id' => $project->id]);
    $old->documents()->create([
        'slug' => 'installation',
        'title' => 'Installation',
        'body' => '<p>old</p>',
        'source_path' => 'docs/installation.md',
        'blob_sha' => 'sha',
    ]);
    Version::factory()->create(['project_id' => $project->id]);

    app(PruneOldVersions::class)->handle($project);

    $this->assertDatabaseCount('documents', 0);
});

it('prunes at most prune_chunk_size versions per run', function () {
    config()->set('docs.sync.keep_versions', 1);
    config()->set('docs.sync.prune_chunk_size', 2);

    $project = Project::factory()->create();
    Version::factory()->count(5)->create(['project_id' => $project->id]);

    app(PruneOldVersions::class)->handle($project);

    // 5 total, keep 1 most recent, chunk caps deletion to 2 per run: 5 - 2 = 3 remain.
    $this->assertDatabaseCount('versions', 3);
});

it('only prunes versions belonging to the given project', function () {
    config()->set('docs.sync.keep_versions', 0);

    $projectA = Project::factory()->create();
    $projectB = Project::factory()->create();
    Version::factory()->count(3)->create(['project_id' => $projectA->id]);
    Version::factory()->count(3)->create(['project_id' => $projectB->id]);

    config()->set('docs.sync.keep_versions', 1);
    app(PruneOldVersions::class)->handle($projectA);

    $this->assertDatabaseCount('versions', 4);
    expect(Version::query()->where('project_id', $projectB->id)->count())->toBe(3);
});
