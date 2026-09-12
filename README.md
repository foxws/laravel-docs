<div align="center">
    <h1>Laravel Docs</h1>
</div>

<p align="center">
    <a href="https://packagist.org/packages/foxws/laravel-docs"><img src="https://img.shields.io/packagist/v/foxws/laravel-docs.svg?style=flat-square" alt="Packagist"></a>
    <a href="https://packagist.org/packages/foxws/laravel-docs"><img src="https://img.shields.io/packagist/php-v/foxws/laravel-docs.svg?style=flat-square" alt="PHP from Packagist"></a>
    <a href="https://github.com/foxws/laravel-docs/actions"><img alt="GitHub Workflow Status (main)" src="https://img.shields.io/github/actions/workflow/status/foxws/laravel-docs/run-tests.yml?branch=main&label=Tests&style=flat-square"></a>
    <a href="https://packagist.org/packages/foxws/laravel-docs"><img src="https://img.shields.io/packagist/dt/foxws/laravel-docs.svg?style=flat-square" alt="Total Downloads"></a>
</p>

Pulls a package's `docs/*.md` folder from GitHub into queryable Eloquent models —
no bundled frontend or theme. The consuming application owns all rendering; this
package is just the data layer.

## Installation

You can install the package via Composer:

```bash
composer require foxws/laravel-docs
```

Publish the config file:

```bash
php artisan vendor:publish --tag="laravel-docs-config"
```

Migrations run automatically. Register at least one project in
`config/docs.php`, then sync:

```bash
php artisan docs:sync
```

See the [documentation](docs/index.md) for configuration, registering
projects, scheduling syncs, and search.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Contributions are welcome. Please open an issue or pull request.

## Security Vulnerabilities

Please review [our security policy](https://github.com/foxws/laravel-docs/security/policy) on how to report security vulnerabilities.

## Credits

- [foxws](https://github.com/foxws)
- [All Contributors](../../contributors)

## License

Laravel Docs is open-sourced software licensed under the [MIT license](LICENSE.md).
