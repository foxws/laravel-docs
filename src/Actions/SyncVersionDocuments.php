<?php

declare(strict_types=1);

namespace Foxws\Docs\Actions;

use Foxws\Docs\Models\Document;
use Foxws\Docs\Models\Version;
use Foxws\Docs\Support\GitHubDocsClient;
use Foxws\Docs\Support\MarkdownDocumentParser;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class SyncVersionDocuments
{
    public function __construct(
        private readonly GitHubDocsClient $client,
        private readonly MarkdownDocumentParser $parser,
    ) {}

    /**
     * Prunes documents no longer in the remote tree, then upserts
     * changed/new ones. Only marks the version synced if both succeed.
     */
    public function handle(Version $version): void
    {
        $project = $version->project;

        $tree = $this->client->fetchTree($project->github_repository, $version->ref);
        $entries = $this->client->filterDocEntries($tree['tree'], $project->docs_path);

        $this->pruneMissing($version, $entries->pluck('path'));
        $this->upsertChanged($version, $entries);

        $version->update([
            'last_synced_at' => now(),
            'last_synced_sha' => $tree['sha'],
        ]);
    }

    /**
     * @param  Collection<int, string>  $remotePaths
     */
    private function pruneMissing(Version $version, Collection $remotePaths): void
    {
        if (! config('docs.sync.prune_missing')) {
            return;
        }

        $version->documents()
            ->whereNotIn('source_path', $remotePaths)
            ->get()
            ->each(function (Document $document) {
                $document->unsearchable();
                $document->delete();
            });
    }

    /**
     * @param  Collection<int, array{path: string, sha: string}>  $entries
     */
    private function upsertChanged(Version $version, Collection $entries): void
    {
        $project = $version->project;

        foreach ($entries as $entry) {
            $existing = $version->documents()->firstWhere('source_path', $entry['path']);

            if ($existing?->blob_sha === $entry['sha']) {
                continue;
            }

            $raw = $this->client->fetchRawContent($project->github_repository, $version->ref, $entry['path']);
            $parsed = $this->parser->parse($raw);
            $stem = Str::of($entry['path'])->afterLast('/')->beforeLast('.md');

            $version->documents()->updateOrCreate(
                ['source_path' => $entry['path']],
                [
                    'slug' => $parsed->frontMatter['slug'] ?? $stem->toString(),
                    'title' => $parsed->frontMatter['title'] ?? $stem->headline()->toString(),
                    'body' => $parsed->markdown,
                    'order' => $parsed->frontMatter['order'] ?? 0,
                    'section' => $parsed->frontMatter['section'] ?? null,
                    'blob_sha' => $entry['sha'],
                    'searchable' => $parsed->frontMatter['searchable'] ?? true,
                    'seo' => $parsed->frontMatter['seo'] ?? null,
                ],
            );
        }
    }
}
