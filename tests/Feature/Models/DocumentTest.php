<?php

declare(strict_types=1);

use Foxws\Docs\Models\Document;

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
