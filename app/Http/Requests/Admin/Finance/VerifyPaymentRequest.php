<?php

namespace App\Http\Requests\Admin\Finance;

use App\Enums\Ability;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class VerifyPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Ability::PaymentVerify->value);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'allocations' => ['nullable', 'array', 'min:1'],
            'allocations.*.bill_item_id' => ['required', 'integer'],
            'allocations.*.amount' => ['required', 'integer', 'min:0', 'max:1000000000000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['allocations.*.amount' => 'Alokasi'];
    }
}
