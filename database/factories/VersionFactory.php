<?php

declare(strict_types=1);

namespace Foxws\Docs\Database\Factories;

use Foxws\Docs\Models\Project;
use Foxws\Docs\Models\Version;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Version>
 */
class VersionFactory extends Factory
{
    protected $model = Version::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'name' => $this->faker->numerify('#.#.#'),
            'ref' => 'main',
            'is_default' => false,
            'last_synced_at' => null,
            'last_synced_sha' => null,
        ];
    }
}
