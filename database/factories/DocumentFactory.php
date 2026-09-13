<?php

declare(strict_types=1);

namespace Foxws\Docs\Database\Factories;

use Foxws\Docs\Models\Document;
use Foxws\Docs\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

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
            'project_id' => Project::factory(),
            'slug' => $slug,
            'title' => ucwords(str_replace('-', ' ', $slug)),
            'body' => '<p>'.$this->faker->paragraph().'</p>',
            'order' => 0,
            'section' => null,
            'source_path' => "docs/{$slug}.md",
            'blob_sha' => $this->faker->sha1(),
            'searchable' => true,
            'seo' => null,
        ];
    }
}
