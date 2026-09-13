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
        $name = $this->deriveVersionName($ref);

        $version = Version::findOrCreate($project->id, $name, ['ref' => $ref])
            ->updateRegistration(['ref' => $ref]);

        return $version->markAsDefault();
    }

    /**
     * Derive a display name from a release tag using
     * `docs.sync.version_name_pattern` — everything the pattern matches is
     * kept. Falls back to the raw tag if the pattern doesn't match (e.g. a
     * tag with no digits) or is set to null.
     */
    private function deriveVersionName(string $ref): string
    {
        $pattern = config('docs.sync.version_name_pattern');

        if ($pattern && preg_match($pattern, $ref, $matches) === 1) {
            return $matches[0];
        }

        return $ref;
    }
}
