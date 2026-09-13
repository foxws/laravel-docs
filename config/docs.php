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

        /*
         * When enabled, docs:sync checks each project's latest GitHub
         * release before syncing and registers it as the default version
         * automatically, if it isn't already registered.
         */
        'auto_discover_versions' => env('DOCS_AUTO_DISCOVER_VERSIONS', true),

        /*
         * How many non-default versions to keep per project, most recently
         * created first — older ones are deleted on the next docs:sync. The
         * default version is never pruned, regardless of its age. Set to 0
         * to keep everything.
         */
        'keep_versions' => env('DOCS_KEEP_VERSIONS', 5),

        /*
         * Maximum number of old versions to delete per docs:sync run, once
         * keep_versions is exceeded. Caps how much a single run prunes if a
         * project has accumulated a large backlog.
         */
        'prune_chunk_size' => env('DOCS_PRUNE_CHUNK_SIZE', 50),
    ],

    'github' => [
        'token' => env('DOCS_GITHUB_TOKEN'),
    ],

];
