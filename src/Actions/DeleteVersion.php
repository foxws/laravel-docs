<?php

declare(strict_types=1);

namespace Foxws\Docs\Actions;

use Foxws\Docs\Models\Document;
use Foxws\Docs\Models\Version;

final class DeleteVersion
{
    /**
     * Delete a version along with its documents, removing each document
     * from the search index first.
     */
    public function handle(Version $version): void
    {
        $version->documents->each(function (Document $document) {
            $document->unsearchable();
            $document->delete();
        });

        $version->delete();
    }
}
