<?php

declare(strict_types=1);

namespace Foxws\Docs\Contracts;

use Illuminate\Support\Collection;

/**
 * A source SyncVersionDocuments can pull a version's markdown files from —
 * implemented by GitHubDocsClient (a remote repository) and LocalDocsClient
 * (a folder on disk). $repository is whatever a Project's driver considers
 * its location (a GitHub "owner/repo" slug, or a local base path).
 */
interface DocsClient
{
    /**
     * Fetch every file under $repository at $ref as a flat tree, so
     * filterDocEntries() can narrow it down to markdown files.
     *
     * @return array{sha: string, tree: array<int, array{path: string, sha: string, type: string}>}
     */
    public function fetchTree(string $repository, string $ref): array;

    /**
     * Filter a tree's entries down to markdown files under the given docs path.
     *
     * @param  array<int, array{path: string, sha: string, type: string}>  $tree
     * @return Collection<int, array{path: string, sha: string}>
     */
    public function filterDocEntries(array $tree, string $docsPath): Collection;

    /**
     * Fetch a single file's raw content.
     */
    public function fetchRawContent(string $repository, string $ref, string $path): string;
}
