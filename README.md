<div align="center">
    <h1>Laravel Docs</h1>
</div>

<p align="center">
    <a href="https://packagist.org/packages/foxws/laravel-docs"><img src="https://img.shields.io/packagist/v/foxws/laravel-docs.svg?style=flat-square" alt="Packagist"></a>
    <a href="https://packagist.org/packages/foxws/laravel-docs"><img src="https://img.shields.io/packagist/php-v/foxws/laravel-docs.svg?style=flat-square" alt="PHP from Packagist"></a>
    <a href="https://github.com/foxws/laravel-docs/actions"><img alt="GitHub Workflow Status (main)" src="https://img.shields.io/github/actions/workflow/status/foxws/laravel-docs/tests.yml?branch=main&label=Tests&style=flat-square"></a>
    <a href="https://packagist.org/packages/foxws/laravel-docs"><img src="https://img.shields.io/packagist/dt/foxws/laravel-docs.svg?style=flat-square" alt="Total Downloads"></a>
</p>

Pulls a package's `docs/*.md` folder from GitHub or a local folder into queryable Eloquent models — no bundled frontend or theme. The consuming application owns all rendering; this package is just the data layer.

## Installation

You can install the package via Composer:

```bash
composer require foxws/laravel-docs
```

You may publish all of the package's resources at once:

```bash
php artisan vendor:publish --tag="docs"
```

Or, you may publish each resource individually:

### Publishing the Configuration File

```bash
php artisan vendor:publish --tag="docs-config"
```

### Publishing and Running the Migrations

Migrations run automatically without publishing, but they can still be published (and customized):

```bash
php artisan vendor:publish --tag="docs-migrations"
php artisan migrate
```

> [!WARNING]
> This package creates its own `projects`, `versions`, and `documents` tables via `loadMigrationsFrom()`. Don't add your own migrations for tables with these names — doing so will fail with a "relation already exists" error when both run. If you need custom columns, publish and edit these migrations instead (see above).

## Usage

Register a project and at least one version, then sync its documentation from GitHub:

```bash
php artisan docs:projects:add laravel-podman "Laravel Podman" foxws/laravel-podman
php artisan docs:versions:add laravel-podman latest main --default
php artisan docs:sync
```

```php
use Foxws\Docs\Models\Project;

$project = Project::where('slug', 'laravel-podman')->firstOrFail();
$version = $project->versions()->where('is_default', true)->firstOrFail();
$document = $version->documents()->where('slug', 'installation')->firstOrFail();

$document->resolveSeoTitle(); // "Installation — Laravel Podman — Foxws"
```

`Document::body` stores the raw markdown pulled from GitHub — nothing is pre-rendered at sync time, so you're free to render it however you like. Call `$document->toHtml()` for a GitHub-flavored, HTML-stripped, unsafe-link-free HTML string (tune the markdown options via `docs.markdown.options`); the result is cached per document, keyed by its `blob_sha`, so it re-renders automatically only when `docs:sync` pulls new content. Pass `toHtml(shouldCache: false)` to skip the cache, and tune it (or turn it off entirely) via the `docs.cache` config.

`Document` also implements `Htmlable`, so `{{ $document }}` in a Blade view renders its HTML unescaped, the same as calling `{!! $document->toHtml() !!}`.

See the [documentation](docs/index.md) for configuration, registering projects and versions, scheduling syncs, and search.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Thank you for considering contributing to Laravel Docs! Please review our [contributing guide](.github/CONTRIBUTING.md) to get started.

## Security Vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

## Credits

- [francoism90](https://github.com/francoism90)
- [All Contributors](../../contributors)

## License

Laravel Docs is open-sourced software licensed under the [MIT license](LICENSE.md).
