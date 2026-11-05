<?php

namespace App\Http\Requests\Admin\Admission;

use App\Enums\Gender;
use App\Models\Organization;
use App\Models\Ruleset;
use App\Models\Wave;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRulesetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ruleset = $this->route('ruleset');

        return $ruleset instanceof Ruleset
            ? $this->user()->can('update', $ruleset)
            : $this->user()->can('create', Ruleset::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $ruleset = $this->route('ruleset');
        $currentOrganizationId = $ruleset instanceof Ruleset ? $ruleset->organization_id : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'wave_id' => ['nullable', 'integer', Rule::exists(Wave::class, 'id')],
            'organization_id' => ['nullable', 'integer', Rule::exists(Organization::class, 'id')->where(fn ($query) => $query->where('is_active', true)->when($currentOrganizationId, fn ($query) => $query->orWhere('id', $currentOrganizationId)))],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['organization_id.exists' => 'Lembaga ini tidak aktif atau tidak ada. Pilih lembaga yang aktif.'];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['wave_id' => 'Gelombang', 'organization_id' => 'Lembaga', 'is_active' => 'Status'];
    }
}
