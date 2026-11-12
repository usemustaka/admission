<?php

namespace Database\Factories;

use App\Models\ExamAspect;
use App\Models\Ruleset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamAspect>
 */
class ExamAspectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ruleset_id' => Ruleset::factory(),
            'name' => fake()->unique()->word(),
            'scale' => 'number',
            'passing_score' => 60,
            'sort_order' => 0,
        ];
    }
}
