<?php

declare(strict_types=1);

namespace Foxws\Docs\Support\Markdown;

use League\CommonMark\Parser\Block\BlockStart;
use League\CommonMark\Parser\Block\BlockStartParserInterface;
use League\CommonMark\Parser\Cursor;
use League\CommonMark\Parser\MarkdownParserStateInterface;

/**
 * Opens a callout on a `:::type` line, optionally followed by a title
 * (`:::warning Breaking change`), as used by VitePress and Nuxt Content.
 */
final class CalloutStartParser implements BlockStartParserInterface
{
    public function tryStart(Cursor $cursor, MarkdownParserStateInterface $parserState): ?BlockStart
    {
        if ($cursor->isIndented()) {
            return BlockStart::none();
        }

        if (! preg_match('/^:::\s*([a-z][a-z0-9-]*)(?:\s+(.+?))?\s*$/i', $cursor->getRemainder(), $matches)) {
            return BlockStart::none();
        }

        $cursor->advanceToEnd();

        return BlockStart::of(new CalloutParser(strtolower($matches[1]), $matches[2] ?? null))->at($cursor);
    }
}
