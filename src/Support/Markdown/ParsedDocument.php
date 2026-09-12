<?php

declare(strict_types=1);

namespace Foxws\Docs\Support\Markdown;

final readonly class ParsedDocument
{
    /**
     * @param  array<string, mixed>  $frontMatter
     */
    public function __construct(
        public array $frontMatter,
        public string $html,
    ) {}
}
