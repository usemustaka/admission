<?php

namespace App\Http\Requests\Admin\Admission;

use App\Enums\ScholarshipKind;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GrantScholarshipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('grantScholarship', $this->route('registrant'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::enum(ScholarshipKind::class)],
            'items' => ['required', 'array', 'min:1'],
            'items.*.bill_item_id' => ['required', 'integer'],
            'items.*.amount' => ['required', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['kind' => 'Jenis beasiswa', 'items' => 'Rincian tagihan', 'items.*.amount' => 'Nominal', 'note' => 'Catatan'];
    }
}
