<?php

declare(strict_types=1);

use Foxws\Docs\Models\Document;
use Illuminate\Support\Facades\Cache;

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
