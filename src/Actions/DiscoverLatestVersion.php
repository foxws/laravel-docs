<?php

declare(strict_types=1);

namespace Foxws\Docs\Actions;

use Foxws\Docs\Models\Project;
use Foxws\Docs\Models\Version;
use Foxws\Docs\Support\GitHubDocsClient;

final class DiscoverLatestVersion
{
    public function __construct(
        private readonly GitHubDocsClient $client,
    ) {}

    /**
     * Register the project's latest GitHub release as its default version.
     * No-op if auto-discovery is disabled or the repository has no releases.
     */
    public function handle(Project $project): ?Version
    {
        if (! config('docs.sync.auto_discover_versions')) {
            return null;
        }

        $release = $this->client->fetchLatestRelease($project->github_repository);

        if ($release === null) {
            return null;
        }

        $ref = $release['tag_name'];
        $name = ltrim($ref, 'vV');

        $version = Version::findOrCreate($project->id, $name, ['ref' => $ref])
            ->updateRegistration(['ref' => $ref]);

        return $version->markAsDefault();
    }
}
