<?php

declare(strict_types=1);

namespace Foxws\Docs\Collections;

use Foxws\Docs\Models\Version;
use Illuminate\Database\Eloquent\Collection;

/**
 * @template TKey of array-key
 * @template TModel of Version
 *
 * @extends Collection<TKey, TModel>
 */
class VersionCollection extends Collection
{
    /**
     * The highest version by name, compared as version numbers (so "1.10.0"
     * ranks above "1.9.0", and "1.0.0" above "0.0.9") rather than by
     * registration order. A leading "v" is ignored. Names that aren't
     * version numbers (e.g. "latest" tracking a branch) rank below any
     * that are, most recently registered first among themselves.
     *
     * @return TModel|null
     */
    public function newest(): ?Version
    {
        return $this->sort(function (Version $a, Version $b): int {
            $aNumber = $a->versionNumber();
            $bNumber = $b->versionNumber();

            if ($aNumber !== null && $bNumber !== null) {
                return version_compare($bNumber, $aNumber) ?: $b->id <=> $a->id;
            }

            if ($aNumber !== null || $bNumber !== null) {
                return $aNumber !== null ? -1 : 1;
            }

            return $b->id <=> $a->id;
        })->first();
    }
}
