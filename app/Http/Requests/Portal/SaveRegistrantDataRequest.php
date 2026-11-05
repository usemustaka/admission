<?php

namespace App\Http\Requests\Portal;

use App\Enums\Gender;
use App\Enums\ParentKind;
use App\Enums\ParentRelation;
use App\Support\PersonOptions;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRegistrantDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('registrant'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'child.gender' => ['nullable', Rule::enum(Gender::class)],
            'child.birth_place' => ['nullable', 'string', 'max:255'],
            'child.birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'child.nisn' => ['nullable', 'digits:10'],
            'child.family_card_number' => ['nullable', 'digits:16'],
            'child.religion' => ['nullable', Rule::in(PersonOptions::RELIGIONS)],
            'child.blood_type' => ['nullable', Rule::in(PersonOptions::BLOOD_TYPES)],
            'child.child_order' => ['nullable', 'integer', 'min:1', 'max:30'],
            'child.siblings_count' => ['nullable', 'integer', 'min:0', 'max:30'],
            'child.address' => ['nullable', 'string', 'max:1000'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:1024'],
            'remove_photo' => ['boolean'],
        ];

        foreach (ParentRelation::cases() as $relation) {
            $key = "parents.{$relation->value}";
            $others = array_map(fn (string $field): string => "{$key}.{$field}", ['nik', 'phone', 'job', 'last_education', 'monthly_income', 'address']);

            $rules["{$key}.name"] = ['nullable', 'string', 'max:255', 'required_with:'.implode(',', $others)];
            $rules["{$key}.nik"] = ['nullable', 'digits:16'];
            $rules["{$key}.phone"] = ['nullable', 'string', 'regex:/^\+?[0-9]{8,15}$/'];
            $rules["{$key}.job"] = ['nullable', 'string', 'max:255'];
            $rules["{$key}.last_education"] = ['nullable', Rule::in(PersonOptions::EDUCATIONS)];
            $rules["{$key}.monthly_income"] = ['nullable', Rule::in(PersonOptions::INCOMES)];
            $rules["{$key}.address"] = ['nullable', 'string', 'max:1000'];
            $rules["{$key}.is_alive"] = ['boolean'];
            $rules["{$key}.kind"] = ['nullable', Rule::enum(ParentKind::class)];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $labels = ['father' => 'ayah', 'mother' => 'ibu', 'guardian' => 'wali'];
        $attributes = [
            'child.gender' => 'Jenis kelamin',
            'child.birth_place' => 'Tempat lahir',
            'child.birth_date' => 'Tanggal lahir',
            'child.nisn' => 'NISN',
            'child.family_card_number' => 'Nomor KK',
            'child.religion' => 'Agama',
            'child.blood_type' => 'Golongan darah',
            'child.child_order' => 'Anak ke-',
            'child.siblings_count' => 'Jumlah saudara',
            'child.address' => 'Alamat',
        ];

        foreach ($labels as $relation => $label) {
            $attributes["parents.{$relation}.name"] = "Nama {$label}";
            $attributes["parents.{$relation}.nik"] = "NIK {$label}";
            $attributes["parents.{$relation}.phone"] = "Nomor HP {$label}";
            $attributes["parents.{$relation}.job"] = "Pekerjaan {$label}";
            $attributes["parents.{$relation}.last_education"] = "Pendidikan {$label}";
            $attributes["parents.{$relation}.monthly_income"] = "Penghasilan {$label}";
            $attributes["parents.{$relation}.address"] = "Alamat {$label}";
        }

        return $attributes;
    }
}
