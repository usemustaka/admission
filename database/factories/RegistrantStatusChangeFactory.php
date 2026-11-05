<?php

namespace Database\Factories;

use App\Enums\RegistrantStatus;
use App\Models\Registrant;
use App\Models\RegistrantStatusChange;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RegistrantStatusChange>
 */
class RegistrantStatusChangeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'registrant_id' => Registrant::factory(),
            'from_status' => RegistrantStatus::Draft,
            'to_status' => RegistrantStatus::Review,
        ];
    }
}
