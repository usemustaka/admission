<?php

namespace App\Http\Requests\Admin;

use App\Concerns\NoteValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateNoteRequest extends FormRequest
{
    use NoteValidationRules;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('note'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->noteRules();
    }
}
