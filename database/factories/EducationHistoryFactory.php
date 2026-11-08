<?php

namespace Database\Factories;

use App\Enums\EducationLevel;
use App\Models\EducationHistory;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EducationHistory>
 */
class EducationHistoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'level' => EducationLevel::Elementary,
            'school_name' => 'SDN '.fake()->numberBetween(1, 99).' '.fake()->city(),
            'npsn' => (string) fake()->numerify('########'),
            'school_address' => fake()->address(),
            'sort_order' => 0,
        ];
    }
}
