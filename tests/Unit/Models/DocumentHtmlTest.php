<?php

declare(strict_types=1);

use Foxws\Docs\Models\Document;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Cache;

it('implements Htmlable so Blade renders it unescaped', function () {
    $document = Document::factory()->make(['body' => '# Installation']);

    expect($document)->toBeInstanceOf(Htmlable::class);
    expect(e($document))->toBe($document->toHtml());
});

it('renders the markdown body to html', function () {
    $document = Document::factory()->make([
        'body' => "# Installation\n\nRun `composer require`.",
        'blob_sha' => 'sha-a',
    ]);

    expect($document->toHtml())->toContain('<h1>Installation</h1>');
});

it('caches the rendered html per blob sha', function () {
    $document = Document::factory()->make([
        'body' => '# Original',
        'blob_sha' => 'sha-a',
    ]);

    $document->toHtml();

    $document->body = '# Changed';

    expect($document->toHtml())->toContain('Original')
        ->and($document->toHtml())->not->toContain('Changed');
});

it('re-renders once the blob sha changes', function () {
    $document = Document::factory()->make([
        'body' => '# Original',
        'blob_sha' => 'sha-a',
    ]);

    $document->toHtml();

    $document->body = '# Changed';
    $document->blob_sha = 'sha-b';

    expect($document->toHtml())->toContain('Changed');
});

it('bypasses the cache when asked not to cache', function () {
    Cache::spy();

    $document = Document::factory()->make(['body' => '# Uncached']);

    expect($document->toHtml(shouldCache: false))->toContain('Uncached');

    Cache::shouldNotHaveReceived('store');
});

it('bypasses the cache when caching is disabled in config', function () {
    config()->set('docs.cache.enabled', false);
    Cache::spy();

    $document = Document::factory()->make(['body' => '# Uncached']);

    expect($document->toHtml())->toContain('Uncached');

    Cache::shouldNotHaveReceived('store');
});

it('reflects the docs.cache.enabled config via shouldCache', function () {
    $document = Document::factory()->make();

    config()->set('docs.cache.enabled', true);
    expect($document->shouldCache())->toBeTrue();

    config()->set('docs.cache.enabled', false);
    expect($document->shouldCache())->toBeFalse();
});

it('lets an explicit shouldCache argument override a disabled config', function () {
    config()->set('docs.cache.enabled', false);

    $document = Document::factory()->make([
        'body' => '# Original',
        'blob_sha' => 'sha-a',
    ]);

    $document->toHtml(shouldCache: true);

    $document->body = '# Changed';

    expect($document->toHtml(shouldCache: true))->toContain('Original');
});
