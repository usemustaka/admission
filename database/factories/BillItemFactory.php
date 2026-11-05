<?php

namespace Database\Factories;

use App\Models\Bill;
use App\Models\BillItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillItem>
 */
class BillItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bill_id' => Bill::factory(),
            'name' => fake()->words(2, true),
            'amount' => 100000,
            'sort_order' => 0,
        ];
    }
}
