<?php

declare(strict_types=1);

return [

    /*
     * Registry of packages whose docs/*.md folder gets pulled in by `docs:sync`.
     * Each entry: slug, title, github_repository ("owner/repo"), docs_path
     * (default "docs"), branch (default "main"), seo (optional title_pattern/description).
     */
    'projects' => [
        // [
        //     'slug' => 'laravel-podman',
        //     'title' => 'Laravel Podman',
        //     'github_repository' => 'foxws/laravel-podman',
        //     'docs_path' => 'docs',
        //     'branch' => 'main',
        //     'seo' => [
        //         'title_pattern' => '%s — Laravel Podman — Foxws',
        //         'description' => 'Podman Quadlet tooling for Laravel.',
        //     ],
        // ],
    ],

    /*
     * Package-wide default SEO, used when a project/document doesn't override it.
     */
    'seo' => [
        'title_pattern' => '%s — Foxws',
        'description' => '',
    ],

    'search' => [
        'enabled' => env('DOCS_SEARCH_ENABLED', true),
        'index_prefix' => env('DOCS_SEARCH_PREFIX', 'foxws_'),
    ],

    'sync' => [
        'prune_missing' => env('DOCS_PRUNE_MISSING', true),
    ],

    'github' => [
        'token' => env('DOCS_GITHUB_TOKEN'),
    ],

];
