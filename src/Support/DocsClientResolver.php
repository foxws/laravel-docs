<?php

declare(strict_types=1);

namespace Foxws\Docs\Support;

use Foxws\Docs\Contracts\DocsClient;
use Foxws\Docs\Models\Project;

/**
 * Picks the DocsClient a project's driver calls for. The right client
 * depends on the project being synced, not on a single app-wide choice, so
 * this resolves it per-call rather than the container binding one fixed
 * implementation of DocsClient.
 */
final class DocsClientResolver
{
    public function __construct(
        private readonly GitHubDocsClient $github,
        private readonly LocalDocsClient $local,
    ) {}

    public function forProject(Project $project): DocsClient
    {
        return match ($project->driver) {
            'local' => $this->local,
            default => $this->github,
        };
    }
}
