<?php

namespace Database\Factories;

use App\Enums\BillType;
use App\Models\Ruleset;
use App\Models\RulesetBill;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RulesetBill>
 */
class RulesetBillFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ruleset_id' => Ruleset::factory(),
            'type' => BillType::Registration,
        ];
    }
}
