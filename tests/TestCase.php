<?php

declare(strict_types=1);

namespace Foxws\Docs\Tests;

use Foxws\Docs\DocsServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Fail loudly on any un-faked HTTP call instead of silently hitting
        // the real network (and GitHub's rate limit) when a test's fake is
        // incomplete.
        Http::preventStrayRequests();
    }

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

        // In-memory Scout engine, no network-backed driver needed for tests.
        config()->set('scout.driver', 'collection');
    }
}
