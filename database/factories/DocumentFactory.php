<?php

declare(strict_types=1);

namespace Foxws\Docs\Database\Factories;

use Foxws\Docs\Enums\DocumentType;
use Foxws\Docs\Models\Document;
use Foxws\Docs\Models\Project;
use Foxws\Docs\Models\Version;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $slug = $this->faker->unique()->slug(2);

        return [
            'type' => DocumentType::Page,
            'version_id' => Version::factory(),
            'slug' => $slug,
            'title' => ucwords(str_replace('-', ' ', $slug)),
            'body' => $this->faker->paragraph(),
            'order' => 0,
            'section' => null,
            'source_path' => "docs/{$slug}.md",
            'blob_sha' => $this->faker->sha1(),
            'searchable' => true,
            'seo' => null,
        ];
    }

    /**
     * A root file of the project, such as its README.
     */
    public function file(string $path = 'README.md'): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => DocumentType::File,
            'project_id' => $attributes['project_id'] ?? Project::factory(),
            'version_id' => null,
            'slug' => Str::of($path)->beforeLast('.')->slug()->toString(),
            'title' => Str::of($path)->beforeLast('.')->lower()->headline()->toString(),
            'source_path' => $path,
            'searchable' => false,
        ]);
    }
}
