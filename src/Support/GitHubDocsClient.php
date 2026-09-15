<?php

declare(strict_types=1);

namespace Foxws\Docs\Support;

use Foxws\Docs\Contracts\DocsClient;
use Foxws\Docs\Support\Concerns\FiltersDocEntries;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

final class GitHubDocsClient implements DocsClient
{
    use FiltersDocEntries;

    /**
     * Fetch the full recursive git tree for a repository at a ref (branch,
     * tag, or SHA) via the GitHub Trees API (authenticated, for the higher
     * rate limit).
     *
     * @return array{sha: string, tree: array<int, array{path: string, sha: string, type: string}>}
     */
    public function fetchTree(string $repository, string $ref): array
    {
        return Http::when(
            config('docs.github.token'),
            fn (PendingRequest $http, string $token) => $http->withToken($token),
        )
            ->get("https://api.github.com/repos/{$repository}/git/trees/{$ref}", [
                'recursive' => 1,
            ])
            ->throw()
            ->json();
    }

    /**
     * Fetch a file's raw content from the CDN-cached, unauthenticated
     * raw.githubusercontent.com endpoint (doesn't touch the API rate limit).
     */
    public function fetchRawContent(string $repository, string $ref, string $path): string
    {
        return Http::get("https://raw.githubusercontent.com/{$repository}/{$ref}/{$path}")
            ->throw()
            ->body();
    }

    /**
     * Fetch the repository's latest non-prerelease GitHub release. Returns
     * null if the repository has no releases.
     *
     * @return array{tag_name: string}|null
     */
    public function fetchLatestRelease(string $repository): ?array
    {
        $response = Http::when(
            config('docs.github.token'),
            fn (PendingRequest $http, string $token) => $http->withToken($token),
        )->get("https://api.github.com/repos/{$repository}/releases/latest");

        if ($response->notFound()) {
            return null;
        }

        return $response->throw()->json();
    }
}
