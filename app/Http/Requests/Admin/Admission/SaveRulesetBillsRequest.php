<?php

namespace App\Http\Requests\Admin\Admission;

use App\Enums\BillType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveRulesetBillsRequest extends FormRequest
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
        $rules = ['bills' => ['required', 'array']];

        foreach (BillType::cases() as $type) {
            $rules["bills.{$type->value}"] = ['present', 'array'];
            $rules["bills.{$type->value}.*.id"] = ['nullable', 'integer'];
            $rules["bills.{$type->value}.*.name"] = ['required', 'string', 'max:255'];
            $rules["bills.{$type->value}.*.amount"] = ['required', 'integer', 'min:0', 'max:1000000000000'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return array_merge(...array_map(fn (BillType $type): array => [
            "bills.{$type->value}.*.name" => 'Nama rincian',
            "bills.{$type->value}.*.amount" => 'Nominal',
        ], BillType::cases()));
    }
}
