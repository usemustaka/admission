<?php

namespace Database\Factories;

use App\Enums\BillType;
use App\Models\Bill;
use App\Models\Registrant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bill>
 */
class BillFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'registrant_id' => Registrant::factory(),
            'type' => BillType::Registration,
            'issued_at' => now(),
        ];
    }
}
