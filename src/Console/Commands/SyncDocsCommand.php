<?php

declare(strict_types=1);

namespace Foxws\Docs\Console\Commands;

use Foxws\Docs\Actions\SyncProjectDocuments;
use Foxws\Docs\Models\Document;
use Foxws\Docs\Models\Project;
use Illuminate\Console\Command;

class SyncDocsCommand extends Command
{
    protected $signature = 'docs:sync';

    protected $description = 'Sync project documentation from GitHub.';

    public function handle(SyncProjectDocuments $syncDocuments): int
    {
        Project::eachRegistered(function (Project $project) use ($syncDocuments) {
            $this->components->task($project->slug, fn () => $syncDocuments->handle($project));
        });

        Document::syncSearchIndex();

        return self::SUCCESS;
    }
}
