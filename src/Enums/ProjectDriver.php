<?php

declare(strict_types=1);

namespace Foxws\Docs\Enums;

enum ProjectDriver: string
{
    case Github = 'github';
    case Local = 'local';
}
