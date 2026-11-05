<?php

use App\Enums\BillType;
use App\Enums\RegistrantStatus;
use App\Models\Parentship;
use App\Models\Registrant;
use App\Models\User;

function registrantOf(User $parent, array $overrides = []): Registrant
{
    $registrant = Registrant::factory()->create($overrides);
    Parentship::factory()->create(['person_id' => $registrant->person_id, 'related_person_id' => $parent->person_id]);

    return $registrant;
}

test('the portal lists only the children of the signed-in parent', function () {
    $parent = User::factory()->create();
    $mine = registrantOf($parent);
    registrantOf(User::factory()->create());

    $this->actingAs($parent)->get(route('portal'))->assertInertia(fn ($page) => $page
        ->component('portal/home')
        ->has('registrants', 1)
        ->where('registrants.0.id', $mine->id)
        ->where('registrants.0.child.name', $mine->person->name));
});

test('both parents of a child can follow the registration', function () {
    $father = User::factory()->create();
    $mother = User::factory()->create();
    $registrant = registrantOf($father);
    Parentship::factory()->create(['person_id' => $registrant->person_id, 'related_person_id' => $mother->person_id, 'relation' => 'mother']);

    $this->actingAs($mother)->get(route('portal.children.show', $registrant))->assertOk();
});

test('someone who is not a parent of the child cannot open the registration or its data', function () {
    $registrant = registrantOf(User::factory()->create());
    $stranger = User::factory()->create();

    $this->actingAs($stranger)->get(route('portal.children.show', $registrant))->assertForbidden();
    $this->actingAs($stranger)->get(route('portal.children.data.edit', $registrant))->assertForbidden();
    $this->actingAs($stranger)->post(route('portal.children.data.update', $registrant), [])->assertForbidden();
});

test('the switcher gets the children of the parent on portal pages only', function () {
    $parent = User::factory()->create();
    registrantOf($parent);
    registrantOf($parent);
    $this->actingAs($parent);

    $this->get(route('portal'))->assertInertia(fn ($page) => $page->has('portal.children', 2));
});

test('the steps skip bill stages the ruleset does not have', function () {
    $without = Registrant::factory()->make(['ruleset_snapshot' => ['ruleset_id' => 1, 'name' => 'x', 'bills' => [], 'exam_aspects' => []]]);
    $with = Registrant::factory()->make(['ruleset_snapshot' => ['ruleset_id' => 1, 'name' => 'x', 'exam_aspects' => [], 'bills' => [
        ['type' => BillType::Registration->value, 'items' => [['name' => 'Formulir', 'amount' => 1000]]],
        ['type' => BillType::Settlement->value, 'items' => [['name' => 'Uang pangkal', 'amount' => 5000]]],
    ]]]);

    expect($without->steps())->toBe(['registered', 'data', 'validation', 'announcement', 'placement'])
        ->and($with->steps())->toBe(['registered', 'registration-bill', 'data', 'validation', 'settlement-bill', 'announcement', 'placement']);
});

test('the current step follows the status', function () {
    expect(Registrant::factory()->make(['status' => RegistrantStatus::Draft])->currentStep())->toBe('data')
        ->and(Registrant::factory()->make(['status' => RegistrantStatus::Review])->currentStep())->toBe('validation')
        ->and(Registrant::factory()->make(['status' => RegistrantStatus::Validated])->currentStep())->toBe('announcement');
});

test('an unpaid registration bill keeps the registration at the payment step', function () {
    $registrant = Registrant::factory()->make(['status' => RegistrantStatus::Draft, 'ruleset_snapshot' => ['ruleset_id' => 1, 'name' => 'x', 'exam_aspects' => [], 'bills' => [
        ['type' => BillType::Registration->value, 'items' => [['name' => 'Formulir', 'amount' => 1000]]],
    ]]]);

    expect($registrant->currentStep())->toBe('registration-bill');
});

test('the child page shows how complete the draft is', function () {
    $parent = User::factory()->create();
    $registrant = registrantOf($parent);
    $registrant->person->update(['birth_place' => 'Bogor', 'birth_date' => '2014-01-01', 'gender' => 'male', 'religion' => 'Islam', 'address' => 'Jl. Melati', 'family_card_number' => '3201123456789012']);

    $this->actingAs($parent)->get(route('portal.children.show', $registrant))->assertInertia(fn ($page) => $page
        ->component('portal/children/show')
        ->where('completeness.filled', 7)
        ->where('completeness.total', 8)
        ->has('family', 1));
});
