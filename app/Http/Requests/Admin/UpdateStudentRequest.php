<?php

namespace App\Http\Requests\Admin;

use App\Enums\Ability;
use App\Models\Student;
use App\Models\Year;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Ability::StudentManage->value);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('nis'))) {
            $this->merge(['nis' => trim($this->input('nis'))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $student = $this->route('student');

        return [
            'nis' => ['required', 'string', 'max:50', Rule::unique(Student::class, 'nis')->ignore($student instanceof Student ? $student->id : null)],
            'year_id' => ['required', 'integer', Rule::exists(Year::class, 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nis.required' => 'Isi nomor induk siswa.',
            'nis.unique' => 'Nomor induk ini sudah dipakai siswa lain.',
            'nis.max' => 'Nomor induk terlalu panjang (maksimal 50 karakter).',
            'year_id.required' => 'Pilih tahun ajaran.',
            'year_id.exists' => 'Tahun ajaran yang dipilih tidak ada.',
        ];
    }
}
