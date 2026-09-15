<?php

declare(strict_types=1);

use Foxws\Docs\Models\Document;

it('finds a document by slug within a given collection', function () {
    $matching = Document::factory()->create(['slug' => 'installation']);
    $other = Document::factory()->create(['slug' => 'installation']);

    $found = Document::firstBySlug(collect([$matching, $other]), 'installation');

    expect($found->is($matching))->toBeTrue();
});

it('returns null when no document in the collection matches the slug', function () {
    $document = Document::factory()->create(['slug' => 'installation']);

    expect(Document::firstBySlug(collect([$document]), 'missing'))->toBeNull();
});

it('re-indexes documents when search is enabled', function () {
    config()->set('docs.search.enabled', true);

    $document = Document::factory()->create(['title' => 'Installation Guide']);

    Document::syncSearchIndex();

    expect(Document::search('Installation')->get()->pluck('id'))->toContain($document->id);
});

it('excludes opted-out documents when the caller adds a searchable where clause', function () {
    config()->set('docs.search.enabled', true);

    $document = Document::factory()->create(['title' => 'Installation Guide', 'searchable' => false]);

    Document::syncSearchIndex();

    expect(Document::search('Installation')->get()->pluck('id'))->toContain($document->id);
    expect(Document::search('Installation')->where('searchable', true)->get()->pluck('id'))->not->toContain($document->id);
});

it('scopes search results to a version via a where clause', function () {
    config()->set('docs.search.enabled', true);

    $matching = Document::factory()->create(['title' => 'Installation Guide']);
    $otherVersion = Document::factory()->create(['title' => 'Installation Guide']);

    Document::syncSearchIndex();

    $results = Document::search('Installation')->where('version_id', $matching->version_id)->get();

    expect($results->pluck('id'))->toContain($matching->id)
        ->and($results->pluck('id'))->not->toContain($otherVersion->id);
});
