<?php

namespace App\Http\Requests\Admin;

use App\Concerns\PersonValidationRules;
use App\Models\Person;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePersonRequest extends FormRequest
{
    use PersonValidationRules;

    public function authorize(): bool
    {
        return $this->user()->can('create', Person::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nik' => ['nullable', 'digits:16'],
            ...$this->personRules(),
        ];
    }
}
