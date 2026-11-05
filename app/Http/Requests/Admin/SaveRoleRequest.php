<?php

namespace App\Http\Requests\Admin;

use App\Enums\Ability;
use App\Support\RoleRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class SaveRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->route('role');

        return $this->user()->can(Ability::RoleManage->value) && ! ($role instanceof Role && RoleRules::isLocked($role));
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => Str::lower(trim($this->input('name')))]);
        }

        $this->merge(['abilities' => $this->input('abilities', [])]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $role = $this->route('role');

        return [
            'name' => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-z0-9][a-z0-9 ()-]*$/',
                Rule::notIn(RoleRules::lockedNames()),
                Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($role instanceof Role ? $role->id : null),
            ],
            'abilities' => ['array'],
            'abilities.*' => [Rule::enum(Ability::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama peran wajib diisi.',
            'name.max' => 'Nama peran paling banyak 50 huruf.',
            'name.regex' => 'Nama peran hanya boleh huruf kecil, angka, spasi, tanda hubung, dan tanda kurung.',
            'name.not_in' => 'Nama ini dipakai peran bawaan. Pilih nama lain.',
            'name.unique' => 'Sudah ada peran dengan nama ini.',
            'abilities.*.enum' => 'Ada izin yang tidak dikenal.',
        ];
    }
}
