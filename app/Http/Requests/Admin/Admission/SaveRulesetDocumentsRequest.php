<?php

namespace App\Http\Requests\Admin\Admission;

use App\Enums\DocumentType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRulesetDocumentsRequest extends FormRequest
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
        $rules = ['documents' => ['required', 'array']];

        foreach (DocumentType::core() as $type) {
            $rules["documents.{$type->value}"] = ['required', Rule::in(['none', 'optional', 'required'])];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [];

        foreach (DocumentType::core() as $type) {
            $attributes["documents.{$type->value}"] = $type->label();
        }

        return $attributes;
    }
}
