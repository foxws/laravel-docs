<?php

declare(strict_types=1);

namespace Foxws\Docs\Jobs;

use Foxws\Docs\Actions\DiscoverLatestVersion;
use Foxws\Docs\Actions\PruneOldVersions;
use Foxws\Docs\Actions\SyncVersionDocuments;
use Foxws\Docs\Exceptions\EmptySourceTreeException;
use Foxws\Docs\Models\Project;
use Foxws\Docs\Models\Version;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

final class SyncProjectDocuments implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $projectSlug,
    ) {}

    /**
     * Keyed by project slug, so two syncs of the same project never run at
     * the same time — a second dispatch (e.g. an overlapping schedule run)
     * is released back onto the queue to retry instead of processing
     * alongside the first.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->projectSlug))
                ->releaseAfter((int) config('docs.sync.queue.overlap_release_after', 30))
                ->expireAfter((int) config('docs.sync.queue.overlap_expires_after', 600)),
        ];
    }

    public function handle(
        SyncVersionDocuments $syncVersionDocuments,
        DiscoverLatestVersion $discoverLatestVersion,
        PruneOldVersions $pruneOldVersions,
    ): void {
        $project = Project::findBySlug($this->projectSlug);

        // The project may have been removed between dispatch and processing.
        if (! $project) {
            return;
        }

        $discoverLatestVersion->handle($project);
        $pruneOldVersions->handle($project);

        $project->versions->each(function (Version $version) use ($syncVersionDocuments) {
            try {
                $syncVersionDocuments->handle($version);
            } catch (EmptySourceTreeException $e) {
                report($e);
            }
        });
    }
}
