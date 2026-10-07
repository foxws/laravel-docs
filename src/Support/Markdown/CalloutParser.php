<?php

declare(strict_types=1);

namespace Foxws\Docs\Support\Markdown;

use League\CommonMark\Extension\CommonMark\Parser\Block\FencedCodeParser;
use League\CommonMark\Node\Block\AbstractBlock;
use League\CommonMark\Parser\Block\AbstractBlockContinueParser;
use League\CommonMark\Parser\Block\BlockContinue;
use League\CommonMark\Parser\Block\BlockContinueParserInterface;
use League\CommonMark\Parser\Cursor;

final class CalloutParser extends AbstractBlockContinueParser
{
    private Callout $block;

    public function __construct(string $type, ?string $title)
    {
        $this->block = new Callout($type, $title);
    }

    public function getBlock(): Callout
    {
        return $this->block;
    }

    public function isContainer(): bool
    {
        return true;
    }

    public function canContain(AbstractBlock $childBlock): bool
    {
        return true;
    }

    /**
     * A `:::` line inside a fenced code block is code, not the closing fence.
     */
    public function tryContinue(Cursor $cursor, BlockContinueParserInterface $activeBlockParser): BlockContinue
    {
        if (! $activeBlockParser instanceof FencedCodeParser && preg_match('/^\s{0,3}:::\s*$/', $cursor->getLine())) {
            return BlockContinue::finished();
        }

        return BlockContinue::at($cursor);
    }
}
