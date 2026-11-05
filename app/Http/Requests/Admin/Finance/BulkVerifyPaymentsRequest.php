<?php

namespace App\Http\Requests\Admin\Finance;

use App\Models\Payment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BulkVerifyPaymentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('verify', Payment::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['required', 'integer', 'distinct'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['ids' => 'Pembayaran yang dipilih'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ids.required' => 'Pilih minimal satu pembayaran.',
            'ids.min' => 'Pilih minimal satu pembayaran.',
            'ids.max' => 'Pilih paling banyak 200 pembayaran sekali jalan.',
        ];
    }
}
