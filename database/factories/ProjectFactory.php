<?php

declare(strict_types=1);

namespace Foxws\Docs\Database\Factories;

use Foxws\Docs\Domain\Projects\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $slug = $this->faker->unique()->slug(2);

        return [
            'slug' => $slug,
            'title' => ucwords(str_replace('-', ' ', $slug)),
            'github_repository' => "foxws/{$slug}",
            'docs_path' => 'docs',
            'branch' => 'main',
            'seo' => null,
            'last_synced_at' => null,
            'last_synced_sha' => null,
        ];
    }
}
