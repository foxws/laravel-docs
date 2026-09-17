<?php

declare(strict_types=1);

namespace Foxws\Docs\Actions;

use Foxws\Docs\Contracts\DocsClient;
use Foxws\Docs\Exceptions\EmptySourceTreeException;
use Foxws\Docs\Models\Document;
use Foxws\Docs\Models\Version;
use Foxws\Docs\Support\DocsClientResolver;
use Foxws\Docs\Support\MarkdownDocumentParser;
use Foxws\Docs\Support\TextNormalizer;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class SyncVersionDocuments
{
    public function __construct(
        private readonly DocsClientResolver $clients,
        private readonly MarkdownDocumentParser $parser,
    ) {}

    /**
     * Prunes documents no longer present at the source, then upserts
     * changed/new ones. Only marks the version synced if both succeed.
     *
     * @throws EmptySourceTreeException if the source returns a completely
     *                                  empty raw tree — deliberately narrower than "no *docs* entries
     *                                  matched" (see filterDocEntries): a repo can legitimately remove
     *                                  all of its own docs/*.md files while still returning a populated
     *                                  tree. Callers decide the policy (skip and continue vs. abort);
     *                                  see SyncDocsCommand/SyncProjectDocuments.
     */
    public function handle(Version $version): void
    {
        $project = $version->project;
        $client = $this->clients->forProject($project);

        $tree = $client->fetchTree($project->sourceLocation(), $version->ref);

        if ($tree['tree'] === []) {
            throw new EmptySourceTreeException($version);
        }

        $entries = $client->filterDocEntries($tree['tree'], $project->docs_path);

        $this->pruneMissing($version, $entries->pluck('path'));
        $this->upsertChanged($version, $client, $entries);

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
    private function upsertChanged(Version $version, DocsClient $client, Collection $entries): void
    {
        $project = $version->project;
        $indexPath = $project->indexDocumentPath();

        foreach ($entries as $entry) {
            $existing = $version->documents()->firstWhere('source_path', $entry['path']);

            if ($existing?->blob_sha === $entry['sha']) {
                continue;
            }

            $raw = $client->fetchRawContent($project->sourceLocation(), $version->ref, $entry['path']);
            $parsed = $this->parser->parse($raw);
            $stem = Str::of($entry['path'])->afterLast('/')->beforeLast('.md');

            // A YAML folded/literal scalar in front matter can carry stray
            // newlines or runs of spaces — squish before it's stored, so
            // every downstream consumer (page <title>, nav, breadcrumbs,
            // search index) sees clean text without normalizing it itself.
            $title = filled($parsed->frontMatter['title'] ?? null)
                ? TextNormalizer::normalize($parsed->frontMatter['title'])
                : $stem->headline()->toString();

            $section = filled($parsed->frontMatter['section'] ?? null)
                ? TextNormalizer::normalize($parsed->frontMatter['section'])
                : null;

            $version->documents()->updateOrCreate(
                ['source_path' => $entry['path']],
                [
                    'slug' => $parsed->frontMatter['slug'] ?? $stem->toString(),
                    'title' => $title,
                    'body' => $parsed->markdown,
                    'order' => $parsed->frontMatter['order'] ?? 0,
                    'section' => $section,
                    'blob_sha' => $entry['sha'],
                    'searchable' => $parsed->frontMatter['searchable'] ?? true,
                    'seo' => $parsed->frontMatter['seo'] ?? null,
                ],
            );

            if ($entry['path'] === $indexPath) {
                $project->update(['metadata' => $parsed->frontMatter['metadata'] ?? null]);
            }
        }
    }
}
