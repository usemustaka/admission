<?php

namespace App\Http\Requests\Admin;

use App\Enums\Ability;
use App\Support\RoleRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Ability::UserManage->value);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::exists('roles', 'name')->where('guard_name', 'web'), Rule::notIn(RoleRules::nonStaffNames())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'roles.required' => 'Pilih minimal satu peran.',
            'roles.min' => 'Pilih minimal satu peran.',
            'roles.*.exists' => 'Peran tidak dikenal.',
            'roles.*.not_in' => 'Peran ini tidak bisa dipilih di sini. Untuk menonaktifkan akun, pakai tombol Nonaktifkan.',
        ];
    }
}
