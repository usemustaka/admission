<?php

namespace Database\Factories;

use App\Models\ExamDay;
use App\Models\Ruleset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamDay>
 */
class ExamDayFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ruleset_id' => Ruleset::factory(),
            'date' => fake()->unique()->dateTimeBetween('+1 week', '+2 years')->format('Y-m-d'),
        ];
    }
}
