<?php

namespace Database\Factories;

use App\Enums\RegistrantStatus;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Registrant;
use App\Models\Wave;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Registrant>
 */
class RegistrantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'organization_id' => Organization::factory(),
            'wave_id' => Wave::factory(),
            'ruleset_id' => null,
            'ruleset_snapshot' => null,
            'registered_by_person_id' => Person::factory(),
            'status' => RegistrantStatus::Draft,
            'needs_identity_review' => false,
        ];
    }
}
