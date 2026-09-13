# Installation

```bash
composer require foxws/laravel-docs
```

Publish the config file:

```bash
php artisan vendor:publish --tag="docs-config"
```

Migrations for the `projects` and `documents` tables run automatically — no
`vendor:publish` step is required for them, but they can still be published
(and customized) via:

```bash
php artisan vendor:publish --tag="docs-migrations"
```

Once at least one project is registered (see
[registering-projects.md](registering-projects.md)), run:

```bash
php artisan docs:sync
```
