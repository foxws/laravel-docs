<?php

declare(strict_types=1);

namespace Foxws\Docs\Support\Markdown;

use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Block\BlockQuote;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;

/**
 * Turns GitHub's alert syntax, a blockquote whose first line is `[!NOTE]`,
 * `[!TIP]`, `[!IMPORTANT]`, `[!WARNING]` or `[!CAUTION]`, into a callout,
 * so docs written for GitHub render the same as `:::type` blocks.
 */
final class GitHubAlertProcessor
{
    private const array TYPES = ['note', 'tip', 'important', 'warning', 'caution'];

    public function __invoke(DocumentParsedEvent $event): void
    {
        $quotes = [];

        foreach ($event->getDocument()->iterator() as $node) {
            if ($node instanceof BlockQuote) {
                $quotes[] = $node;
            }
        }

        foreach ($quotes as $quote) {
            $this->convert($quote);
        }
    }

    private function convert(BlockQuote $quote): void
    {
        $paragraph = $quote->firstChild();

        if (! $paragraph instanceof Paragraph) {
            return;
        }

        $marker = [];
        $text = '';

        // CommonMark splits an unmatched `[` and `]` into text nodes of
        // their own, so the marker can span several of them.
        for ($node = $paragraph->firstChild(); $node instanceof Text; $node = $node->next()) {
            $marker[] = $node;
            $text .= $node->getLiteral();
        }

        $lineBreak = $node;

        if (! preg_match('/^\[!(\w+)\]\s*$/', $text, $matches) || ! in_array($type = strtolower($matches[1]), self::TYPES, true)) {
            return;
        }

        if ($lineBreak !== null && ! $lineBreak instanceof Newline) {
            return;
        }

        foreach ($marker as $node) {
            $node->detach();
        }

        $lineBreak?->detach();

        if ($paragraph->firstChild() === null) {
            $paragraph->detach();
        }

        $callout = new Callout($type);

        foreach ($this->children($quote) as $child) {
            $callout->appendChild($child);
        }

        $quote->replaceWith($callout);
    }

    /**
     * @return array<int, Node>
     */
    private function children(Node $node): array
    {
        $children = [];

        for ($child = $node->firstChild(); $child !== null; $child = $child->next()) {
            $children[] = $child;
        }

        return $children;
    }
}
