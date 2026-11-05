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

class SaveYearRequest extends FormRequest
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

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique(Year::class, 'name')->ignore($year instanceof Year ? $year->id : null)],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
            'is_active' => ['boolean'],
            'copy_periods_from' => ['nullable', 'integer', Rule::exists(Year::class, 'id')],
        ];
    }

    /**
     * Years never overlap, and a year cannot shrink past the periods it already holds.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['starts_on', 'ends_on'])) {
                    return;
                }

                $starts = CarbonImmutable::parse($this->string('starts_on')->toString())->startOfDay();
                $ends = CarbonImmutable::parse($this->string('ends_on')->toString())->startOfDay();
                $year = $this->route('year');
                $yearId = $year instanceof Year ? $year->id : null;

                $clash = Year::query()
                    ->when($yearId, fn ($query) => $query->whereKeyNot($yearId))
                    ->get()
                    ->first(fn (Year $other): bool => $other->starts_on->lte($ends) && $other->ends_on->gte($starts));

                if ($clash !== null) {
                    $validator->errors()->add('starts_on', "Tanggalnya bertabrakan dengan tahun ajaran {$clash->name}. Tahun ajaran tidak boleh tumpang tindih.");

                    return;
                }

                if ($year instanceof Year) {
                    $outside = $year->periods()->get()->first(fn (Period $period): bool => $period->starts_on->lt($starts) || $period->ends_on->gt($ends));

                    if ($outside !== null) {
                        $validator->errors()->add('ends_on', "Periode {$outside->name} akan keluar dari tanggal tahun ajaran. Ubah periodenya dulu.");
                    }
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
            'name.required' => 'Nama tahun ajaran wajib diisi.',
            'name.unique' => 'Nama tahun ajaran ini sudah ada.',
            'starts_on.required' => 'Tanggal mulai wajib diisi.',
            'ends_on.required' => 'Tanggal selesai wajib diisi.',
            'ends_on.after' => 'Tanggal selesai harus setelah tanggal mulai.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['starts_on' => 'Tanggal mulai', 'ends_on' => 'Tanggal selesai', 'is_active' => 'Status', 'name' => 'Nama'];
    }
}
