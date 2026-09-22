<?php

declare(strict_types=1);

use Foxws\Docs\Support\GitHubDocsClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('docs.github.retry.times', 3);
    config()->set('docs.github.retry.sleep_milliseconds', 0);
});

it('retries a raw content fetch that briefly 404s before the CDN catches up', function () {
    Http::fake([
        'raw.githubusercontent.com/*' => Http::sequence()
            ->push('', 404)
            ->push('# Installation', 200),
    ]);

    $content = (new GitHubDocsClient)->fetchRawContent('foxws/example', 'v2.0.0', 'docs/installation.md');

    expect($content)->toBe('# Installation');

    Http::assertSentCount(2);
});

it('retries a tree fetch that briefly 404s before the API catches up', function () {
    $tree = ['sha' => 'root-sha', 'tree' => [], 'truncated' => false];

    Http::fake([
        'api.github.com/*' => Http::sequence()
            ->push('', 404)
            ->push($tree, 200),
    ]);

    $result = (new GitHubDocsClient)->fetchTree('foxws/example', 'v2.0.0');

    expect($result)->toBe($tree);

    Http::assertSentCount(2);
});

it('gives up once the configured retry attempts are exhausted', function () {
    config()->set('docs.github.retry.times', 2);

    Http::fake([
        'raw.githubusercontent.com/*' => Http::response('', 404),
    ]);

    expect(fn () => (new GitHubDocsClient)->fetchRawContent('foxws/example', 'v2.0.0', 'docs/installation.md'))
        ->toThrow(RequestException::class);

    Http::assertSentCount(2);
});

it('does not retry a successful raw content fetch', function () {
    Http::fake([
        'raw.githubusercontent.com/*' => Http::response('# Installation', 200),
    ]);

    (new GitHubDocsClient)->fetchRawContent('foxws/example', 'v2.0.0', 'docs/installation.md');

    Http::assertSentCount(1);
});
