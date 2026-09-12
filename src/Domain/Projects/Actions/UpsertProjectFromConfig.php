<?php

declare(strict_types=1);

namespace Foxws\Docs\Domain\Projects\Actions;

use Foxws\Docs\Domain\Projects\Models\Project;
use Illuminate\Support\Arr;

final class UpsertProjectFromConfig
{
    /**
     * Upsert a project's static fields from a `config('docs.projects')` entry,
     * matched by slug. Never touches sync bookkeeping columns
     * (last_synced_at/last_synced_sha) — those are only written after a
     * successful sync.
     *
     * @param  array<string, mixed>  $config
     */
    public function handle(array $config): Project
    {
        return Project::query()->updateOrCreate(
            ['slug' => $config['slug']],
            Arr::only($config, ['title', 'github_repository', 'docs_path', 'branch', 'seo']),
        );
    }
}
