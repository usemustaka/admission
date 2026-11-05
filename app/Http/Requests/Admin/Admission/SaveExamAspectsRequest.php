<?php

namespace App\Http\Requests\Admin\Admission;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveExamAspectsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('ruleset'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'aspects' => ['present', 'array'],
            'aspects.*.id' => ['nullable', 'integer'],
            'aspects.*.name' => ['required', 'string', 'max:255', 'distinct:ignore_case'],
            'aspects.*.passing_score' => ['required', 'integer', 'min:0', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['aspects.*.name' => 'Nama aspek', 'aspects.*.passing_score' => 'KKM'];
    }
}
