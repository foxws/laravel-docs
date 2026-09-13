<?php

declare(strict_types=1);

namespace Foxws\Docs;

use Foxws\Docs\Console\Commands\AddProjectCommand;
use Foxws\Docs\Console\Commands\SyncDocsCommand;
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
            ->hasCommands(SyncDocsCommand::class, AddProjectCommand::class);
    }
}
