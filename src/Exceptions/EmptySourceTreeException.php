<?php

declare(strict_types=1);

namespace Foxws\Docs\Exceptions;

use Foxws\Docs\Models\Version;
use RuntimeException;

/**
 * Thrown by SyncVersionDocuments when a source client returns a completely
 * empty git tree for a version's ref — almost always a transiently stale
 * read right after a tag/branch was just pushed, not a repository that
 * genuinely has zero files (see SyncVersionDocuments::handle()). Callers
 * should catch this specifically, report it, and move on to the next
 * version/project rather than let it abort a whole sync run the way an
 * unexpected failure should.
 */
final class EmptySourceTreeException extends RuntimeException
{
    public function __construct(Version $version)
    {
        parent::__construct(
            "docs:sync got an empty git tree for [{$version->project->slug}@{$version->name}] (ref \"{$version->ref}\") — skipping instead of pruning its documents; it will retry on the next docs:sync.",
        );
    }
}
