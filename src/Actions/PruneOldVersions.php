<?php

declare(strict_types=1);

namespace Foxws\Docs\Actions;

use Foxws\Docs\Models\Document;
use Foxws\Docs\Models\Project;
use Foxws\Docs\Models\Version;

final class PruneOldVersions
{
    /**
     * Delete old, non-default versions beyond `docs.sync.keep_versions`
     * (most recently created first), at most `docs.sync.prune_chunk_size`
     * per run. A value of 0 keeps everything. The default version is never
     * pruned, regardless of its age.
     */
    public function handle(Project $project): void
    {
        $keep = (int) config('docs.sync.keep_versions', 0);

        if ($keep <= 0) {
            return;
        }

        $project->versions()
            ->where('is_default', false)
            ->orderByDesc('id')
            ->skip($keep)
            ->take((int) config('docs.sync.prune_chunk_size', 50))
            ->get()
            ->each(function (Version $version) {
                $version->documents->each(function (Document $document) {
                    $document->unsearchable();
                    $document->delete();
                });

                $version->delete();
            });
    }
}
