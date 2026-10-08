<?php

declare(strict_types=1);

namespace Foxws\Docs\Actions;

use Foxws\Docs\Models\Project;
use Foxws\Docs\Support\DocsClientResolver;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use RuntimeException;

/**
 * Stores the files listed in `docs.files.paths` (README, CHANGELOG, ...)
 * from the root of the project's repository, read at the default
 * version's ref, or HEAD while the project has no versions. Paths match
 * case-insensitively; only files whose blob sha changed are fetched, and
 * files gone from the repository are deleted. A failed fetch is reported
 * and keeps what was stored, so it never fails the whole sync.
 */
final class SyncProjectFiles
{
    public function __construct(private readonly DocsClientResolver $clients) {}

    public function handle(Project $project): void
    {
        if (! config('docs.files.enabled')) {
            return;
        }

        $client = $this->clients->forProject($project);
        $ref = $project->defaultVersion()->ref ?? 'HEAD';

        try {
            $tree = $client->fetchTree($project->sourceLocation(), $ref);
        } catch (RequestException|RuntimeException $e) {
            report($e);

            return;
        }

        $entries = $this->matchingEntries($tree['tree']);

        $project->files()->whereNotIn('path', $entries->pluck('path'))->delete();

        $stored = $project->files()->pluck('blob_sha', 'path');

        $entries
            ->reject(fn (array $entry): bool => $stored->get($entry['path']) === $entry['sha'])
            ->each(function (array $entry) use ($client, $project, $ref): void {
                try {
                    $content = $client->fetchRawContent($project->sourceLocation(), $ref, $entry['path']);
                } catch (RequestException|RuntimeException $e) {
                    report($e);

                    return;
                }

                $project->files()->updateOrCreate(
                    ['path' => $entry['path']],
                    ['content' => $content, 'blob_sha' => $entry['sha']],
                );
            });
    }

    /**
     * @param  array<int, array<string, mixed>>  $tree
     * @return Collection<int, array{path: string, sha: string}>
     */
    private function matchingEntries(array $tree): Collection
    {
        $paths = collect(Config::array('docs.files.paths'))
            ->filter(fn (mixed $path): bool => is_string($path))
            ->map(fn (string $path): string => strtolower(ltrim($path, '/')));

        return collect($tree)
            ->filter(fn (array $entry): bool => ($entry['type'] ?? null) === 'blob'
                && $paths->contains(strtolower((string) $entry['path'])))
            ->map(fn (array $entry): array => ['path' => (string) $entry['path'], 'sha' => (string) $entry['sha']])
            ->values();
    }
}
