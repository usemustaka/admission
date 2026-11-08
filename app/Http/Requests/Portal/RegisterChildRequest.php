<?php

namespace App\Http\Requests\Portal;

use App\Enums\AdmissionType;
use App\Enums\Gender;
use App\Enums\ParentKind;
use App\Enums\ParentRelation;
use App\Models\Organization;
use App\Models\Wave;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterChildRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->person_id !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'nik' => ['required', 'digits:16'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'organization_id' => ['required', 'integer', Rule::exists(Organization::class, 'id')->where('is_active', true)],
            'wave_id' => [
                'required',
                'integer',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! Wave::query()->open()->whereKey($value)->exists()) {
                        $fail('Gelombang ini sedang tidak menerima pendaftar.');
                    }
                },
            ],
            'admission_type' => ['nullable', Rule::enum(AdmissionType::class)],
            'relation' => ['required', Rule::enum(ParentRelation::class)],
            'kind' => [
                Rule::requiredIf(fn (): bool => in_array($this->input('relation'), [ParentRelation::Father->value, ParentRelation::Mother->value], true)),
                'nullable',
                Rule::enum(ParentKind::class),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'Nama anak',
            'nik' => 'NIK anak',
            'gender' => 'Jenis kelamin',
            'birth_date' => 'Tanggal lahir',
            'organization_id' => 'Lembaga',
            'wave_id' => 'Gelombang',
            'admission_type' => 'Status pendaftar',
            'relation' => 'Hubunganmu dengan anak',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['kind.required' => 'Pilih kandung atau tiri.'];
    }
}
