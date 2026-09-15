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

it('ignores a --- line that appears later in the body, not at the very start', function () {
    // A doc that documents front matter syntax, showing an example inside a
    // fenced code block — the example's own delimiters must never be read
    // as this document's real front matter.
    $raw = <<<'MD'
    # Front matter

    Example:

    ```
    ---
    title: Example
    ---
    ```

    That's it.
    MD;

    $parsed = (new MarkdownDocumentParser)->parse($raw);

    expect($parsed->frontMatter)->toBe([]);
    expect($parsed->markdown)->toBe($raw);
});

it('treats documents without front matter as having no overrides', function () {
    $parsed = (new MarkdownDocumentParser)->parse("# Just a heading\n\nNo front matter here.");

    expect($parsed->frontMatter)->toBe([]);
    expect($parsed->markdown)->toContain('# Just a heading');
});

it('renders markdown to html', function () {
    $html = (new MarkdownDocumentParser)->renderAsHtml("# Installation\n\nRun `composer require`.");

    expect($html)->toContain('<h1>Installation</h1>');
    expect($html)->toContain('<code>composer require</code>');
});

it('strips raw html and disallows unsafe links by default', function () {
    $parser = new MarkdownDocumentParser;

    expect($parser->renderAsHtml('<script>alert(1)</script>'))->not->toContain('<script>');
    expect($parser->renderAsHtml('[click me](javascript:alert(1))'))->not->toContain('href=');
});

it('merges per-call options over the configured defaults', function () {
    config()->set('docs.markdown.options', ['html_input' => 'strip']);

    $html = (new MarkdownDocumentParser)->renderAsHtml('<em>hi</em>', ['html_input' => 'allow']);

    expect($html)->toContain('<em>hi</em>');
});
