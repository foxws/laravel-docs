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
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;

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
     * @throws HttpClientException if GitHub can't be reached or refuses the request.
     * @throws RuntimeException if a local project's file can't be read.
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

        // A truncated tree (GitHub caps very large recursive listings) is
        // missing entries that still exist at the source — don't prune
        // documents just because they fell outside the listing.
        if (! ($tree['truncated'] ?? false)) {
            $this->pruneMissing($version, $entries->pluck('path'));
        }

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

        Document::deleteMany(
            $version->documents()->whereNotIn('source_path', $remotePaths)->get(),
        );
    }

    /**
     * @param  Collection<int, array{path: string, sha: string}>  $entries
     */
    private function upsertChanged(Version $version, DocsClient $client, Collection $entries): void
    {
        $project = $version->project;
        $indexPath = $project->indexDocumentPath();
        $storedShas = $version->documents()->pluck('blob_sha', 'source_path');

        foreach ($entries as $entry) {
            if ($storedShas->get($entry['path']) === $entry['sha']) {
                continue;
            }

            $raw = $client->fetchRawContent($project->sourceLocation(), $version->ref, $entry['path']);
            $parsed = $this->parser->parse($raw);
            $stem = Str::of($entry['path'])->afterLast('/')->beforeLast('.md');

            $frontMatter = $parsed->frontMatter;

            $version->documents()->updateOrCreate(
                ['source_path' => $entry['path']],
                [
                    'slug' => $this->resolveSlug($frontMatter['slug'] ?? null, $stem->toString()),
                    'title' => $this->frontMatterText($frontMatter, 'title') ?? $stem->headline()->toString(),
                    'body' => $parsed->markdown,
                    'order' => is_numeric($frontMatter['order'] ?? null) ? (int) $frontMatter['order'] : 0,
                    'section' => $this->frontMatterText($frontMatter, 'section'),
                    'blob_sha' => $entry['sha'],
                    'searchable' => $frontMatter['searchable'] ?? true,
                    'seo' => $this->frontMatterArray($frontMatter, 'seo'),
                ],
            );

            if ($entry['path'] === $indexPath) {
                $project->update(['metadata' => $this->frontMatterArray($frontMatter, 'metadata')]);
            }
        }
    }

    /**
     * A front matter value as clean text, or null if it's blank or not a
     * scalar. YAML types bare values, so `title: 2024` arrives as an int —
     * cast it rather than fail the sync. A folded/literal scalar can carry
     * stray newlines or runs of spaces, so squish it before it's stored and
     * every consumer (page <title>, nav, search index) sees clean text.
     *
     * @param  array<string, mixed>  $frontMatter
     */
    private function frontMatterText(array $frontMatter, string $key): ?string
    {
        $value = $frontMatter[$key] ?? null;

        if (! is_scalar($value) || is_bool($value) || blank($value)) {
            return null;
        }

        return TextNormalizer::normalize((string) $value);
    }

    /**
     * A front matter mapping, or null if it's missing or not a mapping —
     * a scalar would be stored fine but break the AsArrayObject cast on read.
     *
     * @param  array<string, mixed>  $frontMatter
     * @return array<array-key, mixed>|null
     */
    private function frontMatterArray(array $frontMatter, string $key): ?array
    {
        $value = $frontMatter[$key] ?? null;

        return is_array($value) ? $value : null;
    }

    /**
     * Front matter slugs are often written as site paths for a static site
     * generator — Docusaurus uses `slug: /` for the docs root — so trim
     * the slashes, and fall back to the file name when nothing is left.
     */
    private function resolveSlug(mixed $slug, string $fileName): string
    {
        $slug = is_scalar($slug) ? trim((string) $slug, '/') : '';

        return $slug !== '' ? $slug : $fileName;
    }
}
