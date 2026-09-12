<?php

declare(strict_types=1);

namespace Foxws\Docs\Support\GitHub;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class GitHubDocsClient
{
    /**
     * Fetch the full recursive git tree for a repository/branch via the
     * GitHub Trees API (authenticated, for the higher rate limit).
     *
     * @return array{sha: string, tree: array<int, array{path: string, sha: string, type: string}>}
     */
    public function fetchTree(string $repository, string $branch): array
    {
        return Http::when(
            config('docs.github.token'),
            fn ($http, string $token) => $http->withToken($token),
        )
            ->get("https://api.github.com/repos/{$repository}/git/trees/{$branch}", [
                'recursive' => 1,
            ])
            ->throw()
            ->json();
    }

    /**
     * Filter a tree's entries down to markdown files under the given docs path.
     *
     * @param  array<int, array{path: string, sha: string, type: string}>  $tree
     * @return Collection<int, array{path: string, sha: string}>
     */
    public function filterDocEntries(array $tree, string $docsPath): Collection
    {
        $prefix = Str::finish($docsPath, '/');

        return collect($tree)
            ->filter(fn (array $entry) => $entry['type'] === 'blob'
                && Str::startsWith($entry['path'], $prefix)
                && Str::endsWith($entry['path'], '.md'))
            ->map(fn (array $entry) => [
                'path' => $entry['path'],
                'sha' => $entry['sha'],
            ])
            ->values();
    }

    /**
     * Fetch a file's raw content from the CDN-cached, unauthenticated
     * raw.githubusercontent.com endpoint (doesn't touch the API rate limit).
     */
    public function fetchRawContent(string $repository, string $branch, string $path): string
    {
        return Http::get("https://raw.githubusercontent.com/{$repository}/{$branch}/{$path}")
            ->throw()
            ->body();
    }
}
