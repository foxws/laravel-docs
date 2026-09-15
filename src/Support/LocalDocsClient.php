<?php

declare(strict_types=1);

namespace Foxws\Docs\Support;

use Foxws\Docs\Contracts\DocsClient;
use Foxws\Docs\Support\Concerns\FiltersDocEntries;
use Illuminate\Support\Str;
use League\Flysystem\WhitespacePathNormalizer;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

/**
 * A DocsClient for docs that live in a folder instead of a GitHub repository
 * — e.g. a project with no repository of its own yet, or one you'd rather
 * author docs for locally. $repository is a base path, absolute or relative
 * to the application root; $ref is unused (a folder has no concept of a git
 * ref/version) but kept so this stays interchangeable with GitHubDocsClient.
 */
final class LocalDocsClient implements DocsClient
{
    use FiltersDocEntries;

    public function __construct(
        private readonly WhitespacePathNormalizer $normalizer = new WhitespacePathNormalizer,
    ) {}

    /**
     * Walk every file under $repository and report it as a tree entry, with
     * a content hash standing in for GitHub's blob sha so SyncVersionDocuments'
     * change detection keeps working unmodified.
     *
     * @return array{sha: string, tree: array<int, array{path: string, sha: string, type: string}>}
     */
    public function fetchTree(string $repository, string $ref): array
    {
        $base = $this->resolveBasePath($repository);

        $tree = [];

        foreach ($this->files($base) as $file) {
            $tree[] = [
                'path' => $this->relativePath($base, $file),
                'sha' => $this->hashFile($file->getPathname()),
                'type' => 'blob',
            ];
        }

        // No git tree to hash here, so the "root sha" is derived from the
        // entries themselves — it changes whenever a file's content, name,
        // or presence changes, which is all last_synced_sha needs from it.
        usort($tree, fn (array $a, array $b) => $a['path'] <=> $b['path']);

        return [
            'sha' => hash('sha1', json_encode($tree, JSON_THROW_ON_ERROR)),
            'tree' => $tree,
        ];
    }

    public function fetchRawContent(string $repository, string $ref, string $path): string
    {
        $file = $this->resolveBasePath($repository).'/'.$this->normalizer->normalizePath($path);
        $contents = file_get_contents($file);

        if ($contents === false) {
            throw new RuntimeException("Unable to read local doc file [{$file}].");
        }

        return $contents;
    }

    private function hashFile(string $path): string
    {
        $hash = hash_file('sha1', $path);

        if ($hash === false) {
            throw new RuntimeException("Unable to hash local doc file [{$path}].");
        }

        return $hash;
    }

    private function resolveBasePath(string $repository): string
    {
        $path = $this->isAbsolutePath($repository) ? $repository : base_path($repository);

        return rtrim($path, '/\\');
    }

    /**
     * Str::startsWith($path, '/') alone misses Windows paths (`C:\...`,
     * `C:/...`, UNC `\\server\share`), wrongly treating them as relative and
     * prefixing them with base_path().
     */
    private function isAbsolutePath(string $path): bool
    {
        return Str::startsWith($path, ['/', '\\']) || (bool) preg_match('#^[A-Za-z]:[/\\\\]#', $path);
    }

    /**
     * @return iterable<SplFileInfo>
     */
    private function files(string $base): iterable
    {
        if (! is_dir($base)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                yield $file;
            }
        }
    }

    private function relativePath(string $base, SplFileInfo $file): string
    {
        // SplFileInfo::getPathname() uses backslashes on Windows —
        // normalizePath() converts those to forward slashes (among other
        // things), so source_path always matches the GitHub driver's
        // convention regardless of platform.
        return $this->normalizer->normalizePath(Str::after($file->getPathname(), $base));
    }
}
