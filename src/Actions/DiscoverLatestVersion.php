<?php

declare(strict_types=1);

namespace Foxws\Docs\Actions;

use Foxws\Docs\Enums\ProjectDriver;
use Foxws\Docs\Models\Project;
use Foxws\Docs\Models\Version;
use Foxws\Docs\Support\GitHubDocsClient;
use Illuminate\Http\Client\HttpClientException;

final class DiscoverLatestVersion
{
    public function __construct(
        private readonly GitHubDocsClient $client,
    ) {}

    /**
     * Register the project's latest GitHub release as its default version.
     * No-op if auto-discovery is disabled, the project isn't GitHub-driven
     * (a local folder has no "release" to discover) or has no repository set,
     * or the repository has no releases. A failed lookup is reported and
     * leaves the registered versions as they are, so it never stops a sync.
     */
    public function handle(Project $project): ?Version
    {
        if (! config('docs.sync.auto_discover_versions')) {
            return null;
        }

        if ($project->driver !== ProjectDriver::Github || blank($project->github_repository)) {
            return null;
        }

        try {
            $release = $this->client->fetchLatestRelease($project->github_repository);
        } catch (HttpClientException $e) {
            report($e);

            return null;
        }

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
