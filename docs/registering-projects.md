# Registering projects

Add an entry per package to `config/docs.php`:

```php
'projects' => [
    [
        'slug' => 'laravel-podman',
        'title' => 'Laravel Podman',
        'github_repository' => 'foxws/laravel-podman',
        'docs_path' => 'docs',
        'branch' => 'main',
        'seo' => [
            'title_pattern' => '%s — Laravel Podman — Foxws',
            'description' => 'Podman Quadlet tooling for Laravel.',
        ],
    ],
],
```

`docs_path` and `branch` default to `docs` and `main` if omitted. Running
`docs:sync` upserts this entry into the `projects` table by `slug` — editing
title/repository/docs_path/branch/seo here and re-running the command is all
that's needed to change them later.

## Per-document front matter

Each `.md` file under a project's `docs_path` may declare:

```yaml
---
title: Installation
order: 1
section: Getting Started
searchable: true
seo:
  description: "Install and configure the thing."
---
```

All fields are optional. `slug`/`title` fall back to the file's name when
omitted; `order` defaults to `0`; `searchable` defaults to `true`.
