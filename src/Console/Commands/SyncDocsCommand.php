<?php

declare(strict_types=1);

namespace Foxws\Docs\Console\Commands;

use Foxws\Docs\Actions\DiscoverLatestVersion;
use Foxws\Docs\Actions\PruneOldVersions;
use Foxws\Docs\Actions\SyncVersionDocuments;
use Foxws\Docs\Models\Document;
use Foxws\Docs\Models\Project;
use Foxws\Docs\Models\Version;
use Illuminate\Console\Command;

class SyncDocsCommand extends Command
{
    protected $signature = 'docs:sync
        {--project= : Only sync the project with this slug; omit to sync every registered project}';

    protected $description = 'Sync every registered project\'s documentation from its source (GitHub or a local folder).';

    public function handle(
        SyncVersionDocuments $syncVersionDocuments,
        DiscoverLatestVersion $discoverLatestVersion,
        PruneOldVersions $pruneOldVersions,
    ): int {
        $projectSlug = $this->option('project');

        if (is_string($projectSlug)) {
            $project = Project::findBySlug($projectSlug);

            if (! $project) {
                $this->components->error("No project registered with slug [{$projectSlug}].");

                return self::FAILURE;
            }

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
}
