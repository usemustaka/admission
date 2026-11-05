<?php

namespace Database\Factories;

use App\Models\Wave;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Wave>
 */
class WaveFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Gelombang '.fake()->unique()->numberBetween(1, 999),
            'starts_on' => null,
            'ends_on' => null,
            'is_open' => true,
        ];
    }
}
