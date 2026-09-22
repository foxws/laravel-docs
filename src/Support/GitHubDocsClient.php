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
     * Retried with backoff (see fetchRawContent()'s note on why) — a ref
     * that was only just pushed/tagged can briefly 404 here too.
     *
     * @return array{sha: string, tree: array<int, array{path: string, sha: string, type: string}>}
     */
    public function fetchTree(string $repository, string $ref): array
    {
        return Http::when(
            config('docs.github.token'),
            fn (PendingRequest $http, string $token) => $http->withToken($token),
        )
            ->retry(config('docs.github.retry.times'), config('docs.github.retry.sleep_milliseconds'))
            ->get("https://api.github.com/repos/{$repository}/git/trees/{$ref}", [
                'recursive' => 1,
            ])
            ->throw()
            ->json();
    }

    /**
     * Fetch a file's raw content from the CDN-cached, unauthenticated
     * raw.githubusercontent.com endpoint (doesn't touch the API rate limit).
     *
     * Retried with backoff: right after a new ref is pushed/tagged, the edge
     * cache serving this endpoint can lag behind and 404 a file that exists
     * at the source, which would otherwise get synced in as missing/empty
     * and then never retried — the git blob sha upsertChanged() compares
     * against next time doesn't change for an immutable ref.
     */
    public function fetchRawContent(string $repository, string $ref, string $path): string
    {
        return Http::retry(config('docs.github.retry.times'), config('docs.github.retry.sleep_milliseconds'))
            ->get("https://raw.githubusercontent.com/{$repository}/{$ref}/{$path}")
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
