<?php

namespace App\Http\Requests\Admin\Admission;

use App\Models\Wave;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveWaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        $wave = $this->route('wave');

        return $wave instanceof Wave
            ? $this->user()->can('update', $wave)
            : $this->user()->can('create', Wave::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'is_open' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['starts_on' => 'Tanggal mulai', 'ends_on' => 'Tanggal selesai', 'is_open' => 'Status'];
    }
}
