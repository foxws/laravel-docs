<?php

declare(strict_types=1);

namespace Foxws\Docs\Enums;

enum DocumentType: string
{
    /** A page from a version's docs folder. */
    case Page = 'page';

    /** A file from the repository root, such as the README, synced per project. */
    case File = 'file';
}
