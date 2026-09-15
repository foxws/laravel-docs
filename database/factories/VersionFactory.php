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
            // Unique, not just per-project — `numerify('#.#.#')` alone only
            // has 1,000 possible values, and tests that create several
            // versions for one project hit real collisions against the
            // (project_id, name) unique constraint often enough to flake CI.
            'name' => $this->faker->unique()->numerify('#.#.#'),
            'ref' => 'main',
            'is_default' => false,
            'last_synced_at' => null,
            'last_synced_sha' => null,
        ];
    }
}
