<?php

declare(strict_types=1);

use Foxws\Docs\Models\Document;
use Foxws\Docs\Models\Project;
use Foxws\Docs\Models\Version;

function makeDocumentForSeo(?array $documentSeo, ?array $projectSeo): Document
{
    $project = Project::factory()->make(['seo' => $projectSeo]);

    $version = Version::factory()->make();
    $version->setRelation('project', $project);

    $document = Document::factory()->make(['title' => 'Installation', 'seo' => $documentSeo]);
    $document->setRelation('version', $version);

    return $document;
}

it('resolves the seo title from the document override first', function () {
    $document = makeDocumentForSeo(
        documentSeo: ['title' => 'Custom Title'],
        projectSeo: ['title_pattern' => '%s — Should Not Win'],
    );

    expect($document->resolveSeoTitle())->toBe('Custom Title');
});

it('falls back to the project title pattern', function () {
    $document = makeDocumentForSeo(
        documentSeo: null,
        projectSeo: ['title_pattern' => '%s — Laravel Podman — Foxws'],
    );

    expect($document->resolveSeoTitle())->toBe('Installation — Laravel Podman — Foxws');
});

it('falls back to the global config title pattern', function () {
    config()->set('docs.seo.title_pattern', '%s — Foxws');

    $document = makeDocumentForSeo(documentSeo: null, projectSeo: null);

    expect($document->resolveSeoTitle())->toBe('Installation — Foxws');
});

it('falls back to the bare title when no pattern is configured', function () {
    config()->set('docs.seo.title_pattern', null);

    $document = makeDocumentForSeo(documentSeo: null, projectSeo: null);

    expect($document->resolveSeoTitle())->toBe('Installation');
});
