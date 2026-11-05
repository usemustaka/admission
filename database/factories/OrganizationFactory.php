<?php

namespace Database\Factories;

use App\Enums\OrganizationCategory;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'SMP '.fake()->unique()->company(),
            'code' => fake()->unique()->slug(2),
            'category' => OrganizationCategory::School,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
