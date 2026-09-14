<?php

declare(strict_types=1);

namespace Foxws\Docs\Support;

use Illuminate\Support\Str;
use Spatie\YamlFrontMatter\YamlFrontMatter;

final class MarkdownDocumentParser
{
    public function parse(string $raw): ParsedDocument
    {
        $document = YamlFrontMatter::parse($raw);

        return new ParsedDocument(
            frontMatter: $document->matter(),
            markdown: $document->body(),
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
