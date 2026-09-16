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

it('treats a blank seo title as unset and falls through to the next tier', function () {
    $document = makeDocumentForSeo(
        documentSeo: ['title' => '   '],
        projectSeo: ['title_pattern' => '%s — Foxws'],
    );

    expect($document->resolveSeoTitle())->toBe('Installation — Foxws');
});

it('collapses stray whitespace in an overridden seo title', function () {
    $document = makeDocumentForSeo(
        documentSeo: ['title' => "Custom\n  Title"],
        projectSeo: null,
    );

    expect($document->resolveSeoTitle())->toBe('Custom Title');
});

it('resolves the seo description from the document override first', function () {
    $document = makeDocumentForSeo(
        documentSeo: ['description' => 'Document description'],
        projectSeo: ['description' => 'Should not win'],
    );

    expect($document->resolveSeoDescription())->toBe('Document description');
});

it('falls back to the project seo description', function () {
    $document = makeDocumentForSeo(
        documentSeo: null,
        projectSeo: ['description' => 'Project description'],
    );

    expect($document->resolveSeoDescription())->toBe('Project description');
});

it('falls back to the global config seo description', function () {
    config()->set('docs.seo.description', 'Global description');

    $document = makeDocumentForSeo(documentSeo: null, projectSeo: null);

    expect($document->resolveSeoDescription())->toBe('Global description');
});

it('treats a blank seo description as unset and falls through to the next tier', function () {
    $document = makeDocumentForSeo(
        documentSeo: ['description' => '   '],
        projectSeo: ['description' => 'Project description'],
    );

    expect($document->resolveSeoDescription())->toBe('Project description');
});

it('collapses stray whitespace in an overridden description', function () {
    $document = makeDocumentForSeo(
        documentSeo: ['description' => "Install the package,\n  then publish   the config."],
        projectSeo: null,
    );

    expect($document->resolveSeoDescription())->toBe('Install the package, then publish the config.');
});

it('falls back to an excerpt of the rendered body when no description is configured', function () {
    config()->set('docs.seo.description', null);

    $document = makeDocumentForSeo(documentSeo: null, projectSeo: null);
    $document->body = 'Install the package, then publish the config file.';

    expect($document->resolveSeoDescription())->toBe('Install the package, then publish the config file.');
});

it('excerpts the given html instead of toHtml() when no description is configured', function () {
    config()->set('docs.seo.description', null);

    $document = makeDocumentForSeo(documentSeo: null, projectSeo: null);
    $document->body = 'This body should be ignored in favor of the given html.';

    $result = $document->resolveSeoDescription(html: '<p>Pre-rendered excerpt source.</p>');

    expect($result)->toBe('Pre-rendered excerpt source.');
});

it('truncates a long excerpt to the given length', function () {
    config()->set('docs.seo.description', null);

    $document = makeDocumentForSeo(documentSeo: null, projectSeo: null);
    $document->body = str_repeat('word ', 60);

    $result = $document->resolveSeoDescription(excerptLength: 20);

    expect($result)->toEndWith('...');
});
