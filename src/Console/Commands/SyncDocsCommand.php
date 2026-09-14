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
    protected $signature = 'docs:sync';

    protected $description = 'Sync every registered project\'s documentation from its source (GitHub or a local folder).';

    public function handle(
        SyncVersionDocuments $syncVersionDocuments,
        DiscoverLatestVersion $discoverLatestVersion,
        PruneOldVersions $pruneOldVersions,
    ): int {
        Project::eachRegistered(function (Project $project) use ($syncVersionDocuments, $discoverLatestVersion, $pruneOldVersions) {
            $discoverLatestVersion->handle($project);
            $pruneOldVersions->handle($project);

            $project->versions->each(function (Version $version) use ($project, $syncVersionDocuments) {
                $this->components->task(
                    "{$project->slug}@{$version->name}",
                    fn () => $syncVersionDocuments->handle($version),
                );
            });
        });

        Document::syncSearchIndex();

        return self::SUCCESS;
    }
}
