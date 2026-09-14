<?php

declare(strict_types=1);

namespace Foxws\Docs\Support;

use Illuminate\Support\Str;
use League\CommonMark\Extension\FrontMatter\Data\SymfonyYamlFrontMatterParser;
use League\CommonMark\Extension\FrontMatter\FrontMatterParser;

final class MarkdownDocumentParser
{
    /**
     * league/commonmark's own front matter parser (already a dependency —
     * used here directly, without building a CommonMark environment) anchors
     * to the true start of the string via PCRE's \G, unlike
     * spatie/yaml-front-matter's multiline-mode regex, which matched the
     * first `---` pair anywhere in the document — including one of these
     * docs showing a front matter example inside a code fence.
     */
    public function parse(string $raw): ParsedDocument
    {
        $parsed = (new FrontMatterParser(new SymfonyYamlFrontMatterParser))->parse($raw);
        $frontMatter = $parsed->getFrontMatter();

        return new ParsedDocument(
            frontMatter: is_array($frontMatter) ? $frontMatter : [],
            markdown: $parsed->getContent(),
        );
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function renderAsHtml(string $markdown, array $options = []): string
    {
        return Str::markdown($markdown, [...$this->markdownOptions(), ...$options]);
    }

    /**
     * @return array<string, mixed>
     */
    public function markdownOptions(): array
    {
        return config('docs.markdown.options', [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }
}
