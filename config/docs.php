<?php

declare(strict_types=1);

return [

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
