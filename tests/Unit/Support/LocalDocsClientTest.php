<?php

declare(strict_types=1);

use Foxws\Docs\Support\LocalDocsClient;
use Illuminate\Support\Facades\File;
use League\Flysystem\PathTraversalDetected;

/**
 * Builds a fresh fixture directory and hands it to $callback, deleting it
 * afterwards regardless of the test's outcome.
 */
function withLocalDocsFixture(Closure $callback): mixed
{
    $base = sys_get_temp_dir().'/laravel-docs-tests-'.uniqid();

    File::ensureDirectoryExists("{$base}/docs");
    File::put("{$base}/docs/installation.md", "---\ntitle: Installation\n---\n\n# Installation");
    File::put("{$base}/docs/usage.md", '# Usage');
    File::put("{$base}/README.md", '# Not under docs/, should be ignored');

    try {
        return $callback($base);
    } finally {
        File::deleteDirectory($base);
    }
}

it('reports every file under the base path as a tree entry', function () {
    withLocalDocsFixture(function (string $base) {
        $tree = (new LocalDocsClient)->fetchTree($base, 'unused');

        $paths = collect($tree['tree'])->pluck('path')->sort()->values();

        expect($paths->all())->toBe(['README.md', 'docs/installation.md', 'docs/usage.md']);
        expect($tree['tree'][0]['type'])->toBe('blob');
    });
});

it('filters the tree down to markdown files under the docs path, like GitHubDocsClient', function () {
    withLocalDocsFixture(function (string $base) {
        $client = new LocalDocsClient;

        $tree = $client->fetchTree($base, 'unused');
        $entries = $client->filterDocEntries($tree['tree'], 'docs');

        expect($entries->pluck('path')->sort()->values()->all())->toBe([
            'docs/installation.md',
            'docs/usage.md',
        ]);
    });
});

it('gives two files the same content the same sha, and different content different shas', function () {
    withLocalDocsFixture(function (string $base) {
        File::put("{$base}/docs/duplicate.md", '# Usage');

        $client = new LocalDocsClient;
        $tree = $client->fetchTree($base, 'unused');
        $byPath = collect($tree['tree'])->keyBy('path');

        expect($byPath['docs/duplicate.md']['sha'])->toBe($byPath['docs/usage.md']['sha']);
        expect($byPath['docs/installation.md']['sha'])->not->toBe($byPath['docs/usage.md']['sha']);
    });
});

it('changes the root sha when a file changes, so re-syncing is detected', function () {
    withLocalDocsFixture(function (string $base) {
        $client = new LocalDocsClient;

        $before = $client->fetchTree($base, 'unused');

        File::put("{$base}/docs/usage.md", '# Usage, updated');

        $after = $client->fetchTree($base, 'unused');

        expect($after['sha'])->not->toBe($before['sha']);
    });
});

it('reads a file\'s raw content by its relative path', function () {
    withLocalDocsFixture(function (string $base) {
        $content = (new LocalDocsClient)->fetchRawContent($base, 'unused', 'docs/usage.md');

        expect($content)->toBe('# Usage');
    });
});

it('rejects a path attempting to traverse outside the base path', function () {
    withLocalDocsFixture(function (string $base) {
        (new LocalDocsClient)->fetchRawContent($base, 'unused', '../../etc/passwd');
    });
})->throws(PathTraversalDetected::class);

it('resolves a non-absolute repository relative to the application base path', function () {
    $relative = 'docs-fixture-'.uniqid();

    File::ensureDirectoryExists(base_path("{$relative}/docs"));
    File::put(base_path("{$relative}/docs/index.md"), '# Index');

    try {
        $tree = (new LocalDocsClient)->fetchTree($relative, 'unused');

        expect(collect($tree['tree'])->pluck('path')->all())->toBe(['docs/index.md']);
    } finally {
        File::deleteDirectory(base_path($relative));
    }
});

it('returns an empty tree for a repository that does not exist', function () {
    $tree = (new LocalDocsClient)->fetchTree(sys_get_temp_dir().'/does-not-exist-'.uniqid(), 'unused');

    expect($tree['tree'])->toBe([]);
});
