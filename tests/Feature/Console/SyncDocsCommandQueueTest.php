<?php

declare(strict_types=1);

use Foxws\Docs\Jobs\SyncDocsSearchIndex;
use Foxws\Docs\Jobs\SyncProjectDocuments;
use Foxws\Docs\Models\Project;
use Illuminate\Support\Facades\Bus;

it('chains one job per registered project, followed by a search index job, when --queue is passed', function () {
    Bus::fake();

    Project::factory()->create(['slug' => 'example-a']);
    Project::factory()->create(['slug' => 'example-b']);

    $this->artisan('docs:sync', ['--queue' => true])->assertSuccessful();

    Bus::assertChained([
        fn (SyncProjectDocuments $job) => $job->projectSlug === 'example-a',
        fn (SyncProjectDocuments $job) => $job->projectSlug === 'example-b',
        SyncDocsSearchIndex::class,
    ]);
});

it('only chains the project matching --project when queuing', function () {
    Bus::fake();

    Project::factory()->create(['slug' => 'example-a']);
    Project::factory()->create(['slug' => 'example-b']);

    $this->artisan('docs:sync', ['--project' => 'example-a', '--queue' => true])->assertSuccessful();

    Bus::assertChained([
        fn (SyncProjectDocuments $job) => $job->projectSlug === 'example-a',
        SyncDocsSearchIndex::class,
    ]);
});

it('queues instead of running inline when docs.sync.queue.enabled is true', function () {
    config()->set('docs.sync.queue.enabled', true);

    Bus::fake();

    Project::factory()->create(['slug' => 'example']);

    $this->artisan('docs:sync')->assertSuccessful();

    Bus::assertChained([
        fn (SyncProjectDocuments $job) => $job->projectSlug === 'example',
        SyncDocsSearchIndex::class,
    ]);
});

it('runs inline via --sync even when docs.sync.queue.enabled is true', function () {
    config()->set('docs.sync.queue.enabled', true);

    Bus::fake();

    $this->artisan('docs:sync', ['--sync' => true])->assertSuccessful();

    Bus::assertNothingDispatched();
});

it('fails when --project does not match a registered slug, without dispatching anything', function () {
    Bus::fake();

    $this->artisan('docs:sync', ['--project' => 'missing', '--queue' => true])->assertFailed();

    Bus::assertNothingDispatched();
});
