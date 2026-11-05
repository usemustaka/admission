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

    protected function prepareForValidation(): void
    {
        $this->normalizePersonInput();
    }

    /**
     * `confirm_duplicate` means "yes, I saw the people who look the same and this is another person".
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nik' => ['nullable', 'digits:16'],
            ...$this->personRules(),
            'confirm_duplicate' => ['nullable', 'boolean'],
        ];
    }
}
