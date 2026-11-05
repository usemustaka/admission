<?php

namespace App\Concerns;

use App\Enums\NoteUrgency;
use App\Enums\NoteVisibility;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

trait NoteValidationRules
{
    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    protected function noteRules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
            'urgency' => ['required', Rule::enum(NoteUrgency::class)],
            'visibility' => ['required', Rule::enum(NoteVisibility::class)],
            'shared_with_family' => ['boolean'],
            'user_ids' => ['array'],
            'user_ids.*' => ['integer', Rule::exists(User::class, 'id')],
            'role_ids' => ['array'],
            'role_ids.*' => ['integer', Rule::exists(Role::class, 'id')],
        ];
    }
}
