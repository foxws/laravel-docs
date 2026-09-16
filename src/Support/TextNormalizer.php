<?php

declare(strict_types=1);

namespace Foxws\Docs\Support;

use Illuminate\Support\Str;

/**
 * Collapses whitespace in text pulled from front matter or rendered HTML —
 * a multi-line YAML scalar, or block-level HTML tags once stripped, can
 * otherwise leave stray newlines/runs of spaces in the resolved value.
 */
final class TextNormalizer
{
    public static function normalize(string $value, bool $stripTags = false, ?int $limit = null): string
    {
        $text = Str::of($value);

        if ($stripTags) {
            $text = $text->stripTags();
        }

        $text = $text->squish();

        return $limit === null ? $text->toString() : $text->limit($limit)->toString();
    }
}
