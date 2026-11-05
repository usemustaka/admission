<?php

namespace App\Http\Requests\Admin;

use App\Concerns\NoteValidationRules;
use App\Models\Note;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNoteRequest extends FormRequest
{
    use NoteValidationRules;

    /**
     * Aliases of entities that notes can be attached to. Add an alias here when a new entity supports notes.
     *
     * @var list<string>
     */
    public const NOTEABLE_ALIASES = ['person'];

    public function authorize(): bool
    {
        return $this->user()->can('create', Note::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'noteable_type' => ['required', Rule::in(self::NOTEABLE_ALIASES)],
            'noteable_id' => ['required', 'integer'],
            ...$this->noteRules(),
        ];
    }
}
