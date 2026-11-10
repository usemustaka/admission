<?php

namespace App\Http\Requests\Admin;

use App\Enums\Ability;
use App\Enums\OrganizationCategory;
use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Ability::OrganizationManage->value);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'code' => is_string($this->input('code')) ? Str::lower(trim($this->input('code'))) : $this->input('code'),
            'category' => is_string($this->input('category')) && trim($this->input('category')) !== '' ? trim($this->input('category')) : null,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $organization = $this->route('organization');

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required', 'string', 'max:50', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                Rule::unique(Organization::class, 'code')->ignore($organization instanceof Organization ? $organization->id : null),
            ],
            'category' => ['nullable', Rule::enum(OrganizationCategory::class)],
            'is_active' => ['boolean'],
            'brochure' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'remove_brochure' => ['boolean'],
        ];
    }

    /**
     * The code is how an organization is told apart elsewhere, so it can only change while nothing uses it.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $organization = $this->route('organization');

                if ($organization instanceof Organization
                    && ! $validator->errors()->has('code')
                    && $this->string('code')->toString() !== $organization->code
                    && $organization->isInUse()) {
                    $validator->errors()->add('code', 'Kode tidak bisa diubah karena lembaga ini sudah dipakai aturan, pendaftar, siswa, atau pegawai.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama lembaga wajib diisi.',
            'code.required' => 'Kode lembaga wajib diisi.',
            'code.regex' => 'Kode hanya boleh huruf kecil, angka, dan tanda hubung, misalnya smp-1.',
            'code.unique' => 'Kode ini sudah dipakai lembaga lain.',
            'brochure.mimes' => 'Brosur harus berupa file PDF, JPG, atau PNG.',
            'brochure.max' => 'Ukuran brosur paling besar 10 MB.',
            'brochure.uploaded' => 'Brosur gagal diunggah. Cek ukurannya (maksimal 10 MB) lalu coba lagi.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['code' => 'Kode', 'category' => 'Kategori', 'is_active' => 'Status', 'name' => 'Nama', 'brochure' => 'Brosur'];
    }
}
