<?php

declare(strict_types=1);

namespace Foxws\Docs\Database\Factories;

use Foxws\Docs\Models\Project;
use Foxws\Docs\Models\ProjectFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectFile>
 */
class ProjectFileFactory extends Factory
{
    protected $model = ProjectFile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'path' => 'README.md',
            'content' => "# {$this->faker->word()}\n\n{$this->faker->sentence()}",
            'blob_sha' => $this->faker->sha1(),
        ];
    }
}
