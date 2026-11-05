<?php

namespace Database\Factories;

use App\Models\RulesetBill;
use App\Models\RulesetBillItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RulesetBillItem>
 */
class RulesetBillItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ruleset_bill_id' => RulesetBill::factory(),
            'name' => fake()->words(2, true),
            'amount' => fake()->numberBetween(1, 50) * 50000,
            'sort_order' => 0,
        ];
    }
}
