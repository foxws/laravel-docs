<?php

declare(strict_types=1);

use Foxws\Docs\Models\Document;

it('is searchable when search is enabled and the searchable flag is true', function () {
    config()->set('docs.search.enabled', true);

    $document = Document::factory()->make(['searchable' => true]);

    expect($document->shouldBeSearchable())->toBeTrue();
});

it('is not searchable when search is globally disabled', function () {
    config()->set('docs.search.enabled', false);

    $document = Document::factory()->make(['searchable' => true]);

    expect($document->shouldBeSearchable())->toBeFalse();
});

it('is not searchable when the document opts out via its searchable flag', function () {
    config()->set('docs.search.enabled', true);

    $document = Document::factory()->make(['searchable' => false]);

    expect($document->shouldBeSearchable())->toBeFalse();
});

it('prefixes the search index name from config', function () {
    config()->set('docs.search.index_prefix', 'acme_');

    $document = Document::factory()->make();

    expect($document->searchableAs())->toBe('acme_documents');
});
