<?php

namespace App\Http\Requests\Admin\Admission;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveExamScheduleRequest extends FormRequest
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
        return [
            'days' => ['present', 'array'],
            'days.*.id' => ['nullable', 'integer'],
            'days.*.date' => ['required', 'date', 'distinct'],
            'days.*.sessions' => ['present', 'array'],
            'days.*.sessions.*.id' => ['nullable', 'integer'],
            'days.*.sessions.*.label' => ['required', 'string', 'max:100'],
            'days.*.sessions.*.start_time' => ['required', 'date_format:H:i'],
            'days.*.sessions.*.end_time' => ['required', 'date_format:H:i', 'after:days.*.sessions.*.start_time'],
            'days.*.sessions.*.quota' => ['required', 'integer', 'min:1', 'max:100000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'days.*.date' => 'Tanggal ujian',
            'days.*.sessions.*.label' => 'Nama sesi',
            'days.*.sessions.*.start_time' => 'Jam mulai',
            'days.*.sessions.*.end_time' => 'Jam selesai',
            'days.*.sessions.*.quota' => 'Kuota',
        ];
    }
}
