<?php

namespace App\Http\Requests\Admin\Admission;

use App\Enums\ExamScale;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveExamAspectsRequest extends FormRequest
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
            'aspects' => ['present', 'array'],
            'aspects.*.id' => ['nullable', 'integer'],
            'aspects.*.name' => ['required', 'string', 'max:255', 'distinct:ignore_case'],
            'aspects.*.scale' => ['required', Rule::enum(ExamScale::class)],
            'aspects.*.passing_score' => ['required', 'integer', 'min:0', 'max:100'],
        ];
    }

    /**
     * A level scale needs a KKM that is one of its levels (and not "Belum").
     *
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach ((array) $this->input('aspects', []) as $index => $row) {
                $scale = ExamScale::tryFrom((string) ($row['scale'] ?? ''));

                if ($scale === null || $scale->isNumeric() || ! is_numeric($row['passing_score'] ?? null)) {
                    continue;
                }

                if (! in_array((int) $row['passing_score'], $scale->passingValues(), true)) {
                    $validator->errors()->add("aspects.{$index}.passing_score", 'Pilih tingkat minimal yang dianggap lulus.');
                }
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['aspects.*.name' => 'Nama aspek', 'aspects.*.scale' => 'Jenis penilaian', 'aspects.*.passing_score' => 'KKM'];
    }
}
