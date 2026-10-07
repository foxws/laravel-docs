<?php

declare(strict_types=1);

namespace Foxws\Docs\Support\Markdown;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\ExtensionInterface;

/**
 * Adds `:::note`, `:::tip`, `:::warning` (any `:::type`) container blocks,
 * closed by a `:::` line.
 */
final class CalloutExtension implements ExtensionInterface
{
    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment
            ->addBlockStartParser(new CalloutStartParser, 75)
            ->addRenderer(Callout::class, new CalloutRenderer);
    }
}
