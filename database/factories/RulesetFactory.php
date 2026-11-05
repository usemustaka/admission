<?php

namespace Database\Factories;

use App\Models\Ruleset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ruleset>
 */
class RulesetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'wave_id' => null,
            'organization_id' => null,
            'gender' => null,
            'priority' => fake()->unique()->numberBetween(1, 100000),
            'is_active' => true,
        ];
    }
}
