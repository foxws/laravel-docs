<?php

declare(strict_types=1);

namespace Foxws\Docs\Support\Markdown;

use InvalidArgumentException;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use League\CommonMark\Util\Xml;

/**
 * Renders unstyled markup — the consuming application styles it through
 * the `callout` and `callout-{type}` classes or the `data-callout` attribute.
 */
final class CalloutRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): HtmlElement
    {
        if (! $node instanceof Callout) {
            throw new InvalidArgumentException('Incompatible node type: '.$node::class);
        }

        $title = $node->title === null
            ? ''
            : new HtmlElement('p', ['class' => 'callout-title'], Xml::escape($node->title)).$childRenderer->getBlockSeparator();

        return new HtmlElement(
            'div',
            ['class' => "callout callout-{$node->type}", 'data-callout' => $node->type],
            $childRenderer->getBlockSeparator().$title.$childRenderer->renderNodes($node->children()).$childRenderer->getBlockSeparator(),
        );
    }
}
