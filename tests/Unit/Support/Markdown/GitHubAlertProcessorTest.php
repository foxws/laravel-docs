<?php

declare(strict_types=1);

use Foxws\Docs\Support\MarkdownDocumentParser;

it('renders a github alert as a callout', function () {
    $html = (new MarkdownDocumentParser)->renderAsHtml("> [!WARNING]\n> Keep **backups**.\n\nAfter.");

    expect($html)->toContain('<div class="callout callout-warning" data-callout="warning">')
        ->toContain('<p>Keep <strong>backups</strong>.</p>')
        ->toContain('<p>After.</p>')
        ->not->toContain('[!WARNING]')
        ->not->toContain('<blockquote>');
});

it('keeps every paragraph and block of the alert inside the callout', function () {
    $html = (new MarkdownDocumentParser)->renderAsHtml("> [!note]\n> First.\n>\n> - one\n> - two");

    expect($html)->toMatch('/<div class="callout callout-note"[^>]*>\s*<p>First.<\/p>\s*<ul>.*<\/ul>\s*<\/div>/s');
});

it('renders an alert with only a marker as an empty callout', function () {
    $html = (new MarkdownDocumentParser)->renderAsHtml('> [!TIP]');

    expect($html)->toMatch('/<div class="callout callout-tip" data-callout="tip">\s*<\/div>/');
});

it('leaves other blockquotes alone', function (string $markdown) {
    $html = (new MarkdownDocumentParser)->renderAsHtml($markdown);

    expect($html)->toContain('<blockquote>')
        ->not->toContain('callout');
})->with([
    'a plain quote' => '> Just a quote.',
    'an unknown type' => "> [!DANGER]\n> Not one of GitHub's.",
    'a marker followed by text on the same line' => '> [!NOTE] Inline text.',
    'a marker that is not on the first line' => "> Intro.\n> [!NOTE]",
]);
