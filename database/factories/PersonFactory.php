<?php

namespace Database\Factories;

use App\Enums\Gender;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Person>
 */
class PersonFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nik' => fake()->unique()->numerify('32##############'),
            'name' => fake()->name(),
            'gender' => fake()->randomElement(Gender::cases()),
            'birth_place' => fake()->city(),
            'birth_date' => fake()->date(),
            'phone' => fake()->numerify('08##########'),
            'email' => fake()->safeEmail(),
            'is_alive' => true,
        ];
    }

    public function withoutNik(): static
    {
        return $this->state(fn (): array => ['nik' => null]);
    }
}
