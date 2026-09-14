<?php

declare(strict_types=1);

use Foxws\Docs\Support\MarkdownDocumentParser;

it('extracts front matter and the raw markdown body', function () {
    $raw = <<<'MD'
---
title: Installation
order: 1
section: Getting Started
---

# Installation

Run `composer require`.
MD;

    $parsed = (new MarkdownDocumentParser)->parse($raw);

    expect($parsed->frontMatter)->toMatchArray([
        'title' => 'Installation',
        'order' => 1,
        'section' => 'Getting Started',
    ]);
    expect($parsed->markdown)->toContain('# Installation');
    expect($parsed->markdown)->not->toContain('title: Installation');
});

it('treats documents without front matter as having no overrides', function () {
    $parsed = (new MarkdownDocumentParser)->parse("# Just a heading\n\nNo front matter here.");

    expect($parsed->frontMatter)->toBe([]);
    expect($parsed->markdown)->toContain('# Just a heading');
});

it('renders markdown to html', function () {
    $html = (new MarkdownDocumentParser)->toHtml("# Installation\n\nRun `composer require`.");

    expect($html)->toContain('<h1>Installation</h1>');
    expect($html)->toContain('<code>composer require</code>');
});
