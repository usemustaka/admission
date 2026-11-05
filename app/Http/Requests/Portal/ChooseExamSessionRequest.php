<?php

namespace App\Http\Requests\Portal;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ChooseExamSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->routeIs('admin.*') ? 'manage' : 'submit', $this->route('registrant'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'exam_session_id' => ['required', 'integer', 'exists:exam_sessions,id'],
            'override_full' => ['sometimes', 'boolean'],
            'override_reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['exam_session_id' => 'Sesi ujian', 'override_reason' => 'Alasan'];
    }
}
