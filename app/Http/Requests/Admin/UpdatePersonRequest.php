<?php

namespace App\Http\Requests\Admin;

use App\Concerns\PersonValidationRules;
use App\Models\Person;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePersonRequest extends FormRequest
{
    use PersonValidationRules;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('person'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Person $person */
        $person = $this->route('person');

        return [
            'nik' => ['nullable', 'digits:16', Rule::unique(Person::class, 'nik')->ignore($person)],
            ...$this->personRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nik.unique' => 'NIK ini sudah dipakai orang lain. Satu NIK hanya untuk satu orang.',
        ];
    }
}
