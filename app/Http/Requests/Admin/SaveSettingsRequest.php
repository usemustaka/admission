<?php

namespace App\Http\Requests\Admin;

use App\Enums\Ability;
use App\Enums\SettingGroup;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Ability::SettingManage->value) ?? false;
    }

    public function group(): SettingGroup
    {
        return SettingGroup::from($this->route('group'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = $this->group()->rules();

        if ($this->group() === SettingGroup::Identity) {
            $rules += [
                'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
                'remove_logo' => ['boolean'],
                'favicon' => ['nullable', 'image', 'mimes:png,ico,jpg,jpeg,webp', 'max:512'],
                'remove_favicon' => ['boolean'],
            ];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->group()->attributes();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'brand.accent_color.regex' => 'Warna utama harus berupa kode warna seperti #a3e635.',
        ];
    }
}
