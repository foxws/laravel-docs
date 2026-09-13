<?php

declare(strict_types=1);

use Foxws\Docs\Support\MarkdownDocumentParser;

it('extracts front matter and renders the markdown body to html', function () {
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
    expect($parsed->html)->toContain('<h1>Installation</h1>');
    expect($parsed->html)->toContain('<code>composer require</code>');
});

it('treats documents without front matter as having no overrides', function () {
    $parsed = (new MarkdownDocumentParser)->parse("# Just a heading\n\nNo front matter here.");

    expect($parsed->frontMatter)->toBe([]);
    expect($parsed->html)->toContain('<h1>Just a heading</h1>');
});
