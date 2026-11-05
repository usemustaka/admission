<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payer_person_id' => Person::factory(),
            'amount' => 100000,
            'paid_on' => today(),
            'method' => PaymentMethod::Transfer,
            'status' => PaymentStatus::Pending,
        ];
    }
}
