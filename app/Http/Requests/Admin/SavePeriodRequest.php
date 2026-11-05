<?php

namespace App\Http\Requests\Admin;

use App\Enums\Ability;
use App\Models\Period;
use App\Models\Year;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SavePeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Ability::YearManage->value);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $year = $this->route('year');
        $period = $this->route('period');

        return [
            'name' => ['required', 'string', 'max:100'],
            'sequence' => [
                'required', 'integer', 'between:1,20',
                Rule::unique(Period::class, 'sequence')
                    ->where('year_id', $year instanceof Year ? $year->id : 0)
                    ->ignore($period instanceof Period ? $period->id : null),
            ],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * A period stays inside its year and never overlaps another period of that year.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $year = $this->route('year');

                if (! $year instanceof Year || $validator->errors()->hasAny(['starts_on', 'ends_on'])) {
                    return;
                }

                $starts = CarbonImmutable::parse($this->string('starts_on')->toString())->startOfDay();
                $ends = CarbonImmutable::parse($this->string('ends_on')->toString())->startOfDay();

                if ($starts->lt($year->starts_on) || $ends->gt($year->ends_on)) {
                    $validator->errors()->add('starts_on', "Periode harus berada di dalam tahun ajaran {$year->name} ({$year->starts_on->format('d/m/Y')} sampai {$year->ends_on->format('d/m/Y')}).");

                    return;
                }

                $period = $this->route('period');

                $clash = $year->periods()
                    ->when($period instanceof Period, fn ($query) => $query->whereKeyNot($period->id))
                    ->get()
                    ->first(fn (Period $other): bool => $other->starts_on->lte($ends) && $other->ends_on->gte($starts));

                if ($clash !== null) {
                    $validator->errors()->add('starts_on', "Tanggalnya bertabrakan dengan periode {$clash->name}.");
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
            'name.required' => 'Nama periode wajib diisi.',
            'sequence.required' => 'Isi urutan periode.',
            'sequence.unique' => 'Urutan ini sudah dipakai periode lain di tahun ajaran ini.',
            'sequence.between' => 'Urutan periode antara 1 sampai 20.',
            'starts_on.required' => 'Tanggal mulai wajib diisi.',
            'ends_on.required' => 'Tanggal selesai wajib diisi.',
            'ends_on.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['sequence' => 'Urutan', 'starts_on' => 'Tanggal mulai', 'ends_on' => 'Tanggal selesai', 'is_active' => 'Status', 'name' => 'Nama'];
    }
}
