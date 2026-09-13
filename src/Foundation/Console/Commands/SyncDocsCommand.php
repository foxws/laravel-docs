<?php

declare(strict_types=1);

namespace Foxws\Docs\Foundation\Console\Commands;

use Foxws\Docs\Domain\Documents\Models\Document;
use Foxws\Docs\Domain\Projects\Actions\SyncProjectDocuments;
use Foxws\Docs\Domain\Projects\Models\Project;
use Illuminate\Console\Command;

class SyncDocsCommand extends Command
{
    protected $signature = 'docs:sync';

    protected $description = 'Sync project documentation from GitHub.';

    public function handle(SyncProjectDocuments $syncDocuments): int
    {
        Project::query()->each(function (Project $project) use ($syncDocuments) {
            $this->components->task($project->slug, function () use ($syncDocuments, $project) {
                $syncDocuments->handle($project);
            });
        });

        if (config('docs.search.enabled')) {
            Document::makeAllSearchable();
        }

        return self::SUCCESS;
    }
}
