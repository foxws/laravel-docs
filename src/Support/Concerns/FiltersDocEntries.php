<?php

declare(strict_types=1);

namespace Foxws\Docs\Support\Concerns;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Shared by every DocsClient implementation — a tree's entries are always
 * filtered down to markdown files under the docs path the same way,
 * regardless of where the tree came from.
 */
trait FiltersDocEntries
{
    /**
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
}
