<?php

use App\Actions\Admission\SaveRuleset;
use App\Enums\DefaultRole;
use App\Enums\ParentRelation;
use App\Models\EducationHistory;
use App\Models\Organization;
use App\Models\Parentship;
use App\Models\Person;
use App\Models\Registrant;
use App\Models\Ruleset;
use App\Models\User;
use App\Support\RegistrantReadiness;
use App\Support\RegistrationFields;

/**
 * A registration made under a ruleset that requires exactly `$required`, with the child's basics filled in.
 *
 * @param  list<string>|null  $required
 */
function registrantRequiring(?array $required): Registrant
{
    $registrant = Registrant::factory()->create(['ruleset_snapshot' => ['ruleset_id' => 1, 'name' => 'Aturan', 'bills' => [], 'exam_aspects' => [], 'documents' => [], 'required_fields' => $required]]);
    $registrant->person->update(['gender' => 'male', 'birth_place' => 'Bandung', 'birth_date' => '2014-01-01', 'religion' => 'Islam', 'address' => 'Jl. Melati', 'family_card_number' => '3201234567890123']);
    Parentship::factory()->create(['person_id' => $registrant->person_id, 'relation' => ParentRelation::Mother, 'related_person_id' => Person::factory()->create(['name' => 'Bu Ani', 'phone' => null, 'nik' => null])->id]);

    return $registrant->refresh();
}

test('a registration without its own list still requires only the basics of the child', function () {
    $registrant = registrantRequiring(null);

    expect(RegistrationFields::requiredFor($registrant))->toBe(RegistrationFields::BASELINE)
        ->and(RegistrantReadiness::missing($registrant))->toBe([]);

    $registrant->person->update(['religion' => null]);

    expect(RegistrantReadiness::missing($registrant->refresh()))->toBe(['Agama anak']);
});

test('each lembaga can require different details, and the missing ones are named in plain words', function () {
    $pondok = registrantRequiring(['child.nisn', 'child.siblings_count', 'education.npsn', 'father.nik', 'mother.phone', 'mother.nik']);
    $madrasah = registrantRequiring(['child.nisn']);

    expect(RegistrantReadiness::missing($pondok))->toBe(['NISN anak', 'Jumlah saudara anak', 'NPSN sekolah asal', 'NIK ayah', 'NIK ibu', 'Nomor HP / WA ibu'])
        ->and(RegistrantReadiness::missing($madrasah))->toBe(['NISN anak']);
});

test('details that are filled in stop being listed as missing', function () {
    $registrant = registrantRequiring(['child.nisn', 'education.npsn', 'education.school_address', 'mother.phone', 'mother.job']);

    $registrant->person->update(['nisn' => '0012345678']);
    EducationHistory::factory()->create(['person_id' => $registrant->person_id, 'npsn' => '20219001', 'school_address' => null]);
    $registrant->person->parentships()->first()->relatedPerson->update(['phone' => '081234567890']);

    expect(RegistrantReadiness::missing($registrant->refresh()))->toBe(['Alamat sekolah asal', 'Pekerjaan ibu']);
});

test('a field that no longer exists in the catalog is ignored', function () {
    $registrant = registrantRequiring(['child.nisn', 'child.tidak-ada', 'wali.nik']);

    expect(RegistrationFields::requiredFor($registrant))->toBe(['child.nisn']);
});

test('a new ruleset starts with the default details, which include the ones the committees asked for', function () {
    $ruleset = app(SaveRuleset::class)->handle(null, 'Aturan baru', null, Organization::factory()->create()->id, null, true);

    expect($ruleset->refresh()->required_fields)->toBe(RegistrationFields::defaults())
        ->and($ruleset->required_fields)->toContain('child.nisn', 'child.child_order', 'child.siblings_count', 'education.npsn', 'education.school_address', 'father.nik', 'mother.phone', 'mother.address')
        ->and($ruleset->toSnapshot()['required_fields'])->toBe(RegistrationFields::defaults());
});

test('an admin saves which details a ruleset requires', function () {
    $this->withoutVite();
    $ruleset = Ruleset::factory()->create();

    $this->actingAs(userWithRole(DefaultRole::Admin))
        ->put(route('admin.rulesets.required-fields', $ruleset), ['fields' => ['child.nisn', 'father.nik']])
        ->assertSessionHasNoErrors();

    expect($ruleset->refresh()->required_fields)->toBe(['child.nisn', 'father.nik']);

    $this->put(route('admin.rulesets.required-fields', $ruleset), ['fields' => []])->assertSessionHasNoErrors();
    expect($ruleset->refresh()->required_fields)->toBe([]);

    $this->put(route('admin.rulesets.required-fields', $ruleset), ['fields' => ['child.bukan-kolom']])->assertSessionHasErrors('fields.0');
    $this->put(route('admin.rulesets.required-fields', $ruleset), [])->assertSessionHasErrors('fields');
});

test('only staff who can edit rulesets save required details, and the page shows the choices', function () {
    $this->withoutVite();
    $ruleset = Ruleset::factory()->create(['required_fields' => ['child.nisn']]);

    $this->actingAs(userWithRole(DefaultRole::Operator))->put(route('admin.rulesets.required-fields', $ruleset), ['fields' => []])->assertForbidden();
    expect($ruleset->refresh()->required_fields)->toBe(['child.nisn']);

    $this->actingAs(userWithRole(DefaultRole::Admin))->get(route('admin.rulesets.show', $ruleset))->assertInertia(fn ($page) => $page
        ->where('requiredFields.selected', ['child.nisn'])
        ->has('requiredFields.groups', 4));
});

test('the data form tells the parent which details are required', function () {
    $this->withoutVite();
    $registrant = registrantRequiring(['child.nisn', 'father.nik']);
    $parent = $registrant->person->parentships()->first()->relatedPerson;
    $parent->user ?? User::factory()->create(['person_id' => $parent->id]);

    $this->actingAs($parent->user()->firstOrFail())->get(route('portal.children.data.edit', $registrant))
        ->assertInertia(fn ($page) => $page->where('options.requiredFields', ['child.nisn', 'father.nik']));
});
