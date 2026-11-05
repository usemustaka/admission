<?php

namespace App\Concerns;

use App\Enums\Gender;
use App\Support\PhoneNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait PersonValidationRules
{
    /**
     * Phone numbers and card numbers are typed with spaces and dashes (0812-3456-7890); keep only the digits (and a leading +)
     * before validating and saving, so the same number is always stored the same way.
     */
    protected function normalizePersonInput(): void
    {
        $changes = [];

        if ($this->has('phone')) {
            $changes['phone'] = PhoneNumber::clean($this->input('phone'));
        }

        if ($this->has('family_card_number')) {
            $changes['family_card_number'] = preg_replace('/\D+/', '', (string) $this->input('family_card_number')) ?: null;
        }

        $this->merge($changes);
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    protected function personRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'birth_place' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'phone' => ['nullable', 'string', 'regex:/^\+?[0-9]{8,15}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'family_card_number' => ['nullable', 'digits:16'],
            'is_alive' => ['boolean'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:1024'],
            'remove_photo' => ['boolean'],
        ];
    }
}
