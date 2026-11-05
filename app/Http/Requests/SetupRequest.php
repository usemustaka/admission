<?php

namespace App\Http\Requests;

use App\Enums\OrganizationCategory;
use App\Enums\SettingGroup;
use App\Models\Organization;
use App\Models\Year;
use App\Support\SetupState;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class SetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! SetupState::isComplete();
    }

    /**
     * The WhatsApp number may be typed with spaces, dashes or brackets; only the digits and a leading plus are kept.
     */
    protected function prepareForValidation(): void
    {
        $whatsapp = data_get($this->input('contact'), 'whatsapp');

        if (is_string($whatsapp)) {
            $this->merge(['contact' => [...(array) $this->input('contact'), 'whatsapp' => preg_replace('/[\s().-]+/', '', $whatsapp)]]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $organizationsExist = Organization::query()->exists();
        $yearsExist = Year::query()->exists();

        return [
            'setup_token' => [Rule::requiredIf(filled(config('setup.token'))), 'nullable', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                $token = (string) config('setup.token');

                if ($token !== '' && ! hash_equals($token, (string) $value)) {
                    $fail('Kode setup tidak cocok. Lihat nilai SETUP_TOKEN di file .env.');
                }
            }],

            'brand.name' => SettingGroup::Identity->rules()['brand.name'],
            'brand.accent_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],

            ...SettingGroup::Contact->rules(),
            ...SettingGroup::Payment->rules(),

            'admin.name' => ['required', 'string', 'max:120'],
            'admin.email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'admin.password' => ['required', 'confirmed:admin.password_confirmation', Password::defaults()],

            'organizations' => [Rule::requiredIf(! $organizationsExist), 'nullable', 'array', 'max:20'],
            'organizations.*.name' => ['required', 'string', 'max:255', 'distinct:ignore_case'],
            'organizations.*.category' => ['nullable', Rule::enum(OrganizationCategory::class)],

            'year' => [Rule::requiredIf(! $yearsExist), 'nullable', 'array'],
            'year.name' => ['required_with:year.starts_on,year.ends_on', Rule::requiredIf(! $yearsExist), 'nullable', 'string', 'max:255', Rule::unique('years', 'name')],
            'year.starts_on' => ['required_with:year.name', Rule::requiredIf(! $yearsExist), 'nullable', 'date_format:Y-m-d'],
            'year.ends_on' => ['required_with:year.name', Rule::requiredIf(! $yearsExist), 'nullable', 'date_format:Y-m-d', 'after:year.starts_on'],
            'year.with_periods' => ['nullable', 'boolean'],

            'wave' => ['nullable', 'array'],
            'wave.name' => ['nullable', 'string', 'max:255'],
            'wave.starts_on' => ['required_with:wave.name', 'nullable', 'date_format:Y-m-d'],
            'wave.ends_on' => ['required_with:wave.name', 'nullable', 'date_format:Y-m-d', 'after_or_equal:wave.starts_on'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            ...SettingGroup::Identity->attributes(),
            ...SettingGroup::Contact->attributes(),
            ...SettingGroup::Payment->attributes(),
            'setup_token' => 'Kode setup',
            'logo' => 'Logo',
            'admin.name' => 'Nama admin',
            'admin.email' => 'Email admin',
            'admin.password' => 'Kata sandi',
            'organizations' => 'Lembaga',
            'organizations.*.name' => 'Nama lembaga',
            'organizations.*.category' => 'Kategori lembaga',
            'year.name' => 'Nama tahun ajaran',
            'year.starts_on' => 'Tanggal mulai tahun ajaran',
            'year.ends_on' => 'Tanggal selesai tahun ajaran',
            'wave.name' => 'Nama gelombang',
            'wave.starts_on' => 'Tanggal mulai gelombang',
            'wave.ends_on' => 'Tanggal selesai gelombang',
        ];
    }
}
