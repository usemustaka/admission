<?php

namespace App\Http\Requests\Admin\Admission;

use App\Support\RegistrationFields;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRulesetRequiredFieldsRequest extends FormRequest
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
            'fields' => ['present', 'array'],
            'fields.*' => ['string', Rule::in(RegistrationFields::allKeys())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['fields' => 'Data wajib'];
    }
}
