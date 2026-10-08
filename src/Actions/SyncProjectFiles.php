<?php

declare(strict_types=1);

namespace Foxws\Docs\Actions;

use Foxws\Docs\Enums\DocumentType;
use Foxws\Docs\Models\Project;
use Foxws\Docs\Support\DocsClientResolver;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Stores the files listed in `docs.sync.additional_files` (README,
 * CHANGELOG, ...) from the root of the project's repository as documents
 * of type File, owned by the project rather than a version and never
 * searchable. They're read at the default version's ref, or HEAD while
 * the project has no versions. Paths match case-insensitively; only files
 * whose blob sha changed are fetched, and files gone from the repository
 * are deleted. A failed fetch is reported
 * and keeps what was stored, so it never fails the whole sync.
 */
final class SyncProjectFiles
{
    public function __construct(private readonly DocsClientResolver $clients) {}

    public function handle(Project $project): void
    {
        $paths = $this->configuredPaths();

        if ($paths->isEmpty()) {
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

        $entries = $this->matchingEntries($tree['tree'], $paths);

        $project->files()->whereNotIn('source_path', $entries->pluck('path'))->delete();

        $stored = $project->files()->pluck('blob_sha', 'source_path');

        $entries
            ->reject(fn (array $entry): bool => $stored->get($entry['path']) === $entry['sha'])
            ->each(function (array $entry) use ($client, $project, $ref): void {
                try {
                    $content = $client->fetchRawContent($project->sourceLocation(), $ref, $entry['path']);
                } catch (RequestException|RuntimeException $e) {
                    report($e);

                    return;
                }

                $name = Str::of($entry['path'])->beforeLast('.');

                $project->files()->updateOrCreate(
                    ['source_path' => $entry['path']],
                    [
                        'type' => DocumentType::File,
                        'slug' => $name->slug()->toString(),
                        'title' => $name->lower()->headline()->toString(),
                        'body' => $content,
                        'blob_sha' => $entry['sha'],
                        'searchable' => false,
                    ],
                );
            });
    }

    /**
     * @return Collection<int, lowercase-string>
     */
    private function configuredPaths(): Collection
    {
        return collect(Config::array('docs.sync.additional_files', []))
            ->filter(fn (mixed $path): bool => is_string($path) && $path !== '')
            ->map(fn (string $path): string => strtolower(ltrim($path, '/')))
            ->values();
    }

    /**
     * @param  array<int, array<string, mixed>>  $tree
     * @param  Collection<int, lowercase-string>  $paths
     * @return Collection<int, array{path: string, sha: string}>
     */
    private function matchingEntries(array $tree, Collection $paths): Collection
    {
        return collect($tree)
            ->filter(fn (array $entry): bool => ($entry['type'] ?? null) === 'blob'
                && $paths->contains(strtolower((string) $entry['path'])))
            ->map(fn (array $entry): array => ['path' => (string) $entry['path'], 'sha' => (string) $entry['sha']])
            ->values();
    }
}
