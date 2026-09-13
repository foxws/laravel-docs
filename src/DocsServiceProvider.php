<?php

declare(strict_types=1);

namespace Foxws\Docs;

use Foxws\Docs\Console\Commands\AddProjectCommand;
use Foxws\Docs\Console\Commands\SyncDocsCommand;
use Illuminate\Support\ServiceProvider;

class DocsServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/docs.php', 'docs');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/docs.php' => config_path('docs.php'),
        ], ['docs', 'docs-config']);

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], ['docs', 'docs-migrations']);

        $this->commands([
            SyncDocsCommand::class,
            AddProjectCommand::class,
        ]);
    }
}
