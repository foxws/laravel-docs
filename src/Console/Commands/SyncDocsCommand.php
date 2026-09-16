<?php

declare(strict_types=1);

namespace Foxws\Docs\Console\Commands;

use Foxws\Docs\Actions\DiscoverLatestVersion;
use Foxws\Docs\Actions\PruneOldVersions;
use Foxws\Docs\Actions\SyncVersionDocuments;
use Foxws\Docs\Jobs\SyncDocsSearchIndex;
use Foxws\Docs\Jobs\SyncProjectDocuments;
use Foxws\Docs\Models\Document;
use Foxws\Docs\Models\Project;
use Foxws\Docs\Models\Version;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;

class SyncDocsCommand extends Command
{
    protected $signature = 'docs:sync
        {--project= : Only sync the project with this slug; omit to sync every registered project}
        {--queue : Dispatch each project\'s sync as a chained queue job instead of running inline; overrides docs.sync.queue.enabled}
        {--sync : Force an inline run even when docs.sync.queue.enabled is true}';

    protected $description = 'Sync every registered project\'s documentation from its source (GitHub or a local folder).';

    public function handle(
        SyncVersionDocuments $syncVersionDocuments,
        DiscoverLatestVersion $discoverLatestVersion,
        PruneOldVersions $pruneOldVersions,
    ): int {
        $projectSlug = $this->option('project');
        $project = null;

        if (is_string($projectSlug)) {
            $project = Project::findBySlug($projectSlug);

            if (! $project) {
                $this->components->error("No project registered with slug [{$projectSlug}].");

                return self::FAILURE;
            }
        }

        if ($this->shouldQueue()) {
            $slugs = $project
                ? Collection::make([$project->slug])
                : Project::modelClass()::query()->pluck('slug');

            return $this->queueSync($slugs);
        }

        if ($project) {
            $this->syncProject($project, $syncVersionDocuments, $discoverLatestVersion, $pruneOldVersions);
        } else {
            Project::eachRegistered(fn (Project $project) => $this->syncProject(
                $project, $syncVersionDocuments, $discoverLatestVersion, $pruneOldVersions,
            ));
        }

        Document::syncSearchIndex();

        return self::SUCCESS;
    }

    private function syncProject(
        Project $project,
        SyncVersionDocuments $syncVersionDocuments,
        DiscoverLatestVersion $discoverLatestVersion,
        PruneOldVersions $pruneOldVersions,
    ): void {
        $discoverLatestVersion->handle($project);
        $pruneOldVersions->handle($project);

        $project->versions->each(function (Version $version) use ($project, $syncVersionDocuments) {
            $this->components->task(
                "{$project->slug}@{$version->name}",
                fn () => $syncVersionDocuments->handle($version),
            );
        });
    }

    /**
     * Whether to dispatch queued jobs instead of syncing inline —
     * `--queue`/`--sync` take precedence over `docs.sync.queue.enabled` so
     * either can be forced for a single run regardless of the configured
     * default.
     */
    private function shouldQueue(): bool
    {
        if ($this->option('sync')) {
            return false;
        }

        return $this->option('queue') || (bool) config('docs.sync.queue.enabled');
    }

    /**
     * Chains one job per project, keyed by slug and protected against
     * overlapping with itself (see SyncProjectDocuments::middleware()),
     * followed by a job that rebuilds the search index once every project
     * has synced. Chained jobs run one at a time, in order — never in
     * parallel — regardless of how many queue workers are running.
     *
     * @param  Collection<int, string>  $projectSlugs
     */
    private function queueSync(Collection $projectSlugs): int
    {
        $jobs = $projectSlugs
            ->map(fn (string $slug) => new SyncProjectDocuments($slug))
            ->all();

        $jobs[] = new SyncDocsSearchIndex;

        Bus::chain($jobs)
            ->onConnection(config('docs.sync.queue.connection'))
            ->onQueue(config('docs.sync.queue.queue'))
            ->dispatch();

        $this->components->info(match (true) {
            $projectSlugs->isEmpty() => 'Queued search index sync (no registered projects).',
            $projectSlugs->count() === 1 => "Queued sync for [{$projectSlugs->first()}].",
            default => "Queued sync for {$projectSlugs->count()} project(s).",
        });

        return self::SUCCESS;
    }
}
