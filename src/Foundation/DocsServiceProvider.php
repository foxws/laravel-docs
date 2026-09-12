<?php

declare(strict_types=1);

namespace Foxws\Docs\Foundation;

use Foxws\Docs\Foundation\Console\Commands\SyncDocsCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class DocsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-docs')
            ->hasConfigFile('docs')
            ->hasMigrations('create_projects_table', 'create_documents_table')
            ->runsMigrations()
            ->hasCommands(SyncDocsCommand::class);
    }

    /**
     * spatie/laravel-package-tools resolves the package root from this
     * provider's own directory, special-casing only a directory literally
     * named "Providers" one level below it. This provider lives at
     * src/Foundation/DocsServiceProvider.php (DDD layering), so that
     * heuristic resolves one level too shallow — override it explicitly.
     */
    protected function getPackageBaseDir(): string
    {
        return dirname(__DIR__);
    }
}
