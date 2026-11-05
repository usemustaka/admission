<?php

namespace Database\Factories;

use App\Enums\ParentKind;
use App\Enums\ParentRelation;
use App\Models\Parentship;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Parentship>
 */
class ParentshipFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'related_person_id' => Person::factory(),
            'relation' => ParentRelation::Father,
            'kind' => ParentKind::Biological,
            'is_primary_contact' => false,
        ];
    }

    public function primaryContact(): static
    {
        return $this->state(fn (): array => ['is_primary_contact' => true]);
    }
}
