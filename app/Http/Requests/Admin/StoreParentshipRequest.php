<?php

namespace App\Http\Requests\Admin;

use App\Enums\Ability;
use App\Enums\ParentKind;
use App\Enums\ParentRelation;
use App\Models\Parentship;
use App\Models\Person;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreParentshipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Ability::FamilyManage->value);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Person $person */
        $person = $this->route('person');

        return [
            'related_person_id' => [
                'required',
                'integer',
                Rule::exists(Person::class, 'id'),
                Rule::notIn([$person->id]),
                Rule::unique(Parentship::class, 'related_person_id')->where('person_id', $person->id),
            ],
            'relation' => ['required', Rule::enum(ParentRelation::class)],
            'kind' => [
                Rule::requiredIf(fn (): bool => in_array($this->input('relation'), [ParentRelation::Father->value, ParentRelation::Mother->value], true)),
                'nullable',
                Rule::enum(ParentKind::class),
            ],
            'is_primary_contact' => ['boolean'],
            'confirm_duplicate' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'related_person_id.not_in' => 'Seseorang tidak bisa jadi orang tua atau wali untuk dirinya sendiri.',
            'related_person_id.unique' => 'Orang ini sudah tercatat sebagai orang tua atau wali.',
            'kind.required' => 'Pilih kandung atau tiri.',
        ];
    }
}
