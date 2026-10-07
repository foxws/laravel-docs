<?php

declare(strict_types=1);

namespace Foxws\Docs\Support\Markdown;

use League\CommonMark\Node\Block\AbstractBlock;

final class Callout extends AbstractBlock
{
    public function __construct(
        public readonly string $type,
        public readonly ?string $title = null,
    ) {
        parent::__construct();
    }
}
