<?php

declare(strict_types=1);

namespace Foxws\Docs\Support;

use League\CommonMark\CommonMarkConverter;
use Spatie\YamlFrontMatter\YamlFrontMatter;

final class MarkdownDocumentParser
{
    public function __construct(
        private readonly CommonMarkConverter $converter = new CommonMarkConverter,
    ) {}

    public function parse(string $raw): ParsedDocument
    {
        $document = YamlFrontMatter::parse($raw);

        return new ParsedDocument(
            frontMatter: $document->matter(),
            markdown: $document->body(),
        );
    }

    public function toHtml(string $markdown): string
    {
        return $this->converter->convert($markdown)->getContent();
    }
}
