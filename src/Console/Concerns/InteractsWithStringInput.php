<?php

declare(strict_types=1);

namespace Foxws\Docs\Console\Concerns;

use RuntimeException;

trait InteractsWithStringInput
{
    private function stringArgument(string $key): string
    {
        $value = $this->argument($key);

        if (! is_string($value)) {
            throw new RuntimeException("The [{$key}] argument must be a string.");
        }

        return $value;
    }

    private function stringOption(string $key): string
    {
        $value = $this->option($key);

        if (! is_string($value)) {
            throw new RuntimeException("The [--{$key}] option must be a string.");
        }

        return $value;
    }
}
