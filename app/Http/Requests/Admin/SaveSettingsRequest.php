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
                // No `image` rule: Laravel's image check rejects .ico. The extension and content type lists still keep SVG out.
                'favicon' => ['nullable', 'file', 'mimes:png,ico,jpg,jpeg,webp', 'mimetypes:image/png,image/x-icon,image/vnd.microsoft.icon,image/jpeg,image/webp', 'max:512'],
                'remove_favicon' => ['boolean'],
            ];
        }

        return $rules;
    }

    /**
     * People type phone numbers with spaces, dashes, and brackets; store the WhatsApp number as bare digits with an
     * optional leading +.
     */
    protected function prepareForValidation(): void
    {
        $whatsapp = $this->input('contact.whatsapp');

        if ($this->group() === SettingGroup::Contact && is_string($whatsapp)) {
            $this->merge(['contact' => [
                ...(array) $this->input('contact'),
                'whatsapp' => preg_replace('/[\s\-().]+/', '', $whatsapp),
            ]]);
        }
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
            'contact.whatsapp.regex' => 'WhatsApp hanya boleh berisi angka, boleh diawali +, misalnya 0812 3456 7890.',
            'contact.website.url' => 'Situs web harus berupa alamat yang diawali http:// atau https://.',
        ];
    }
}
