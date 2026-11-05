<?php

namespace App\Http\Requests\Settings;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Get the validation rules that apply to the request. Changing the email address asks for the current
     * password, since the address is how the account is recovered.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->profileRules($this->user()->id),
            'current_password' => $this->emailIsChanging() ? $this->currentPasswordRules() : ['nullable'],
        ];
    }

    public function emailIsChanging(): bool
    {
        return $this->input('email') !== $this->user()->email;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['current_password' => 'Kata sandi saat ini'];
    }
}
