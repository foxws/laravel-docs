---
title: Introduction
metadata:
  role: Documentation
  group: foundations
  eyebrow: "Markdown · GitHub · Eloquent"
  desc: "Pull a package's docs/*.md folder from GitHub into queryable Eloquent models."
  lead: "Turn a package's docs/ folder on GitHub into Eloquent models you can query, search and render your own way. It powers foxws.nl."
  requires: "PHP ^8.3"
  laravel: "12.x / 13.x"
  licence: MIT
  used_by:
    name: foxws.nl
    desc: "This site. Every package's docs here come from it."
    href: "https://foxws.nl"
---

# Introduction

Laravel Docs pulls the Markdown files in a package's `docs/` folder into your Laravel app, as Eloquent models you can query and search. It reads them from GitHub, or from a local folder for a project without a repository yet.

It has no frontend or theme. You build the pages yourself, with the `Project`, `Version` and `Document` models it provides. This is how [foxws.nl](https://foxws.nl) shows the docs of every package.

Register a project, then sync it. With the default config, `docs:sync` finds the project's latest GitHub release and pulls its docs:

```bash
php artisan docs:projects:add laravel-podman "Laravel Podman" --github=foxws/laravel-podman
php artisan docs:sync
```

Then read its documents like any other model:

```php
use Foxws\Docs\Models\Project;

$project = Project::findBySlug('laravel-podman', with: ['versions']);

foreach ($project->defaultVersion()->orderedDocuments() as $document) {
    echo $document->title;
}
```

## Features

- Sync docs from GitHub, or from a local folder.
- Keep several versions of each project's docs, found automatically from GitHub releases.
- Only download files that changed since the last sync.
- Read titles, ordering, sections and project details from each file's front matter.
- Render Markdown to HTML, and search documents with Laravel Scout.

## Installation

```bash
composer require foxws/laravel-docs
php artisan vendor:publish --tag="docs-config"
```

## Learn more

- [Installation](installation.md)
- [Registering projects](registering-projects.md): projects, versions and front matter.
- [Syncing](syncing.md)
- [Models](models.md)
- [Search](search.md)
- [Configuration](configuration.md)
