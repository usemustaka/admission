<?php

namespace App\Http\Requests\Admin;

use App\Enums\Ability;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CancelPlacementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Ability::StudentManage->value);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:1000']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Tulis alasan pembatalan penempatan.',
            'reason.max' => 'Alasan terlalu panjang (maksimal 1000 karakter).',
        ];
    }
}
