<?php

declare(strict_types=1);

namespace Foxws\Docs\Database\Factories;

use Foxws\Docs\Enums\ProjectDriver;
use Foxws\Docs\Models\Project;
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
            'driver' => ProjectDriver::Github,
            'github_repository' => "foxws/{$slug}",
            'docs_path' => 'docs',
            'seo' => null,
        ];
    }

    public function local(string $localPath = 'docs'): static
    {
        return $this->state(fn (array $attributes) => [
            'driver' => ProjectDriver::Local,
            'github_repository' => null,
            'local_path' => $localPath,
        ]);
    }
}
