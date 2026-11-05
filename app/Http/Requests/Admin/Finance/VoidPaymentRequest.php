<?php

namespace App\Http\Requests\Admin\Finance;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VoidPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('void', Payment::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'to' => ['required', Rule::in([PaymentStatus::Rejected->value, PaymentStatus::Pending->value])],
            'reason' => ['required', 'string', 'max:480'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['to' => 'Tujuan pembatalan', 'reason' => 'Alasan'];
    }
}
