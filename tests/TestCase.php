<?php

declare(strict_types=1);

namespace Foxws\Docs\Tests;

use Foxws\Docs\Foundation\DocsServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            DocsServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');

        // In-memory Scout engine: exercises shouldBeSearchable()/searchable()/
        // unsearchable() for real, without hitting a network-backed driver.
        config()->set('scout.driver', 'collection');
    }
}
