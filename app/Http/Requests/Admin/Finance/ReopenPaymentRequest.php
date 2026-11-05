<?php

namespace App\Http\Requests\Admin\Finance;

use App\Models\Payment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReopenPaymentRequest extends FormRequest
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
            'reason' => ['required', 'string', 'max:480'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['reason' => 'Alasan'];
    }
}
