<?php

namespace App\Http\Requests\Admin\Admission;

use App\Enums\DocumentType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRulesetDocumentsRequest extends FormRequest
{
    /** How many custom files one ruleset can ask for. */
    public const MAX_CUSTOM = 15;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('ruleset'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'documents' => ['required', 'array'],
            'custom' => ['sometimes', 'array', 'max:'.self::MAX_CUSTOM],
            'custom.*.key' => ['nullable', 'string', 'max:40'],
            'custom.*.label' => ['required', 'string', 'max:100', 'distinct:ignore_case'],
            'custom.*.max_files' => ['required', 'integer', 'min:1', 'max:10'],
            'custom.*.choice' => ['required', Rule::in(['optional', 'required'])],
        ];

        foreach (DocumentType::configurable() as $type) {
            $rules["documents.{$type->value}"] = [$type->isSupporting() ? 'sometimes' : 'required', Rule::in(['none', 'optional', 'required'])];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = ['custom.*.label' => 'Nama berkas', 'custom.*.max_files' => 'Jumlah berkas', 'custom.*.choice' => 'Sifat berkas'];

        foreach (DocumentType::configurable() as $type) {
            $attributes["documents.{$type->value}"] = $type->label();
        }

        return $attributes;
    }
}
