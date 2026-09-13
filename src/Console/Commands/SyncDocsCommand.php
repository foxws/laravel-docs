<?php

declare(strict_types=1);

namespace Foxws\Docs\Console\Commands;

use Foxws\Docs\Actions\SyncVersionDocuments;
use Foxws\Docs\Models\Document;
use Foxws\Docs\Models\Project;
use Foxws\Docs\Models\Version;
use Illuminate\Console\Command;

class SyncDocsCommand extends Command
{
    protected $signature = 'docs:sync';

    protected $description = 'Sync project documentation from GitHub.';

    public function handle(SyncVersionDocuments $syncVersionDocuments): int
    {
        Project::eachRegistered(function (Project $project) use ($syncVersionDocuments) {
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
