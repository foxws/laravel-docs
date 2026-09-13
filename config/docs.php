<?php

declare(strict_types=1);

use Foxws\Docs\Models\Document;
use Foxws\Docs\Models\Project;
use Foxws\Docs\Models\Version;

return [

    /*
     * Override these to use your own models. Yours must extend the
     * respective base model, e.g. `App\Models\Project extends
     * Foxws\Docs\Models\Project`.
     */
    'models' => [
        'project' => Project::class,
        'version' => Version::class,
        'document' => Document::class,
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
