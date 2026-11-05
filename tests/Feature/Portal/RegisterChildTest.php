<?php

use App\Enums\BillType;
use App\Enums\NoteUrgency;
use App\Enums\NoteVisibility;
use App\Models\Note;
use App\Models\Organization;
use App\Models\Parentship;
use App\Models\Person;
use App\Models\Registrant;
use App\Models\Ruleset;
use App\Models\RulesetBill;
use App\Models\RulesetBillItem;
use App\Models\User;
use App\Models\Wave;

beforeEach(function () {
    $this->parent = User::factory()->create();
    $this->wave = Wave::factory()->create();
    $this->organization = Organization::factory()->create();
    $this->ruleset = Ruleset::factory()->create();
    $this->actingAs($this->parent);

    $this->register = fn (array $overrides = []) => $this->post(route('portal.children.store'), [
        'name' => 'Ahmad Fauzi',
        'nik' => '3201123456789012',
        'gender' => 'male',
        'birth_date' => '2014-05-06',
        'organization_id' => $this->organization->id,
        'wave_id' => $this->wave->id,
        'relation' => 'father',
        'kind' => 'biological',
        ...$overrides,
    ]);
});

test('guests are sent to the login page', function () {
    auth()->logout();

    $this->get(route('portal.children.create'))->assertRedirect(route('login'));
});

test('a parent can register a child and is linked as the primary contact', function () {
    $response = ($this->register)();

    $registrant = Registrant::firstOrFail();

    $response->assertRedirect(route('portal.children.show', $registrant));

    $child = $registrant->person;
    $link = Parentship::firstOrFail();

    expect($child->name)->toBe('Ahmad Fauzi')
        ->and($child->nik)->toBe('3201123456789012')
        ->and($child->gender->value)->toBe('male')
        ->and($registrant->status->value)->toBe('draft')
        ->and($registrant->registered_by_person_id)->toBe($this->parent->person_id)
        ->and($registrant->needs_identity_review)->toBeFalse()
        ->and($link->related_person_id)->toBe($this->parent->person_id)
        ->and($link->relation->value)->toBe('father')
        ->and($link->kind->value)->toBe('biological')
        ->and($link->is_primary_contact)->toBeTrue();
});

test('the ruleset that applied is copied into the registration', function () {
    $bill = RulesetBill::factory()->create(['ruleset_id' => $this->ruleset->id, 'type' => BillType::Registration]);
    RulesetBillItem::factory()->create(['ruleset_bill_id' => $bill->id, 'name' => 'Formulir', 'amount' => 150000]);

    ($this->register)();

    $registrant = Registrant::firstOrFail();
    $this->ruleset->bills()->delete();

    expect($registrant->ruleset_id)->toBe($this->ruleset->id)
        ->and($registrant->ruleset_snapshot['bills'][0]['items'][0])->toBe(['name' => 'Formulir', 'amount' => 150000]);
});

test('nothing is created when no ruleset applies', function () {
    $this->ruleset->update(['organization_id' => Organization::factory()->create()->id]);

    ($this->register)()->assertSessionHasErrors('organization_id');

    expect(Person::where('nik', '3201123456789012')->exists())->toBeFalse()->and(Registrant::count())->toBe(0);
});

test('only open waves and active organizations can be chosen', function () {
    $closed = Wave::factory()->create(['is_open' => false]);
    $inactive = Organization::factory()->create(['is_active' => false]);

    ($this->register)(['wave_id' => $closed->id])->assertSessionHasErrors('wave_id');
    ($this->register)(['organization_id' => $inactive->id])->assertSessionHasErrors('organization_id');
});

test('father and mother need a kind but a guardian does not', function () {
    ($this->register)(['kind' => null])->assertSessionHasErrors('kind');

    ($this->register)(['relation' => 'guardian', 'kind' => null])->assertSessionHasNoErrors();

    expect(Parentship::firstOrFail()->kind)->toBeNull();
});

test('the child needs a name, a 16 digit NIK, and a gender', function () {
    ($this->register)(['name' => '', 'nik' => '123', 'gender' => ''])->assertSessionHasErrors(['name', 'nik', 'gender']);
});

describe('a NIK that is already known', function () {
    test('is linked to the same person without a flag when the name matches', function () {
        $existing = Person::factory()->create(['nik' => '3201123456789012', 'name' => 'Ahmad Fauzi', 'gender' => 'male', 'birth_date' => '2014-01-01']);

        ($this->register)(['name' => '  ahmad   FAUZI ', 'gender' => 'female', 'birth_date' => '2015-01-01'])->assertSessionHasNoErrors();

        $registrant = Registrant::firstOrFail();

        expect(Person::where('nik', '3201123456789012')->count())->toBe(1)
            ->and($registrant->person_id)->toBe($existing->id)
            ->and($registrant->needs_identity_review)->toBeFalse()
            ->and(Note::count())->toBe(0)
            ->and($existing->refresh()->gender->value)->toBe('male')
            ->and($existing->birth_date->toDateString())->toBe('2014-01-01');
    });

    test('fills in details the person did not have yet', function () {
        $existing = Person::factory()->create(['nik' => '3201123456789012', 'name' => 'Ahmad Fauzi', 'gender' => null, 'birth_date' => null]);

        ($this->register)();

        expect($existing->refresh()->gender->value)->toBe('male')->and($existing->birth_date->toDateString())->toBe('2014-05-06');
    });

    test('is flagged for the committee with an urgent note when the name differs', function () {
        $existing = Person::factory()->create(['nik' => '3201123456789012', 'name' => 'Budi Santoso']);

        ($this->register)()->assertSessionHasNoErrors();

        $registrant = Registrant::firstOrFail();
        $note = Note::firstOrFail();

        expect($registrant->person_id)->toBe($existing->id)
            ->and($registrant->needs_identity_review)->toBeTrue()
            ->and($existing->refresh()->name)->toBe('Budi Santoso')
            ->and($note->noteable_id)->toBe($existing->id)
            ->and($note->person_id)->toBe($existing->id)
            ->and($note->author_id)->toBeNull()
            ->and($note->urgency)->toBe(NoteUrgency::High)
            ->and($note->visibility)->toBe(NoteVisibility::Public)
            ->and($note->shared_with_family)->toBeFalse()
            ->and($note->body)->toContain('Ahmad Fauzi')->toContain('Budi Santoso');
    });

    test('cannot be the parents own NIK', function () {
        $this->parent->person->update(['nik' => '3201123456789012']);

        ($this->register)()->assertSessionHasErrors('nik');
    });

    test('does not add a second link when the parent is already linked', function () {
        $existing = Person::factory()->create(['nik' => '3201123456789012', 'name' => 'Ahmad Fauzi']);
        Parentship::factory()->create(['person_id' => $existing->id, 'related_person_id' => $this->parent->person_id, 'relation' => 'mother']);

        ($this->register)();

        expect(Parentship::count())->toBe(1);
    });
});

test('the same child cannot be registered twice for the same organization and wave', function () {
    ($this->register)();

    ($this->register)()->assertSessionHasErrors('nik');

    expect(Registrant::count())->toBe(1);
});

test('the same child can register in another wave', function () {
    ($this->register)();

    ($this->register)(['wave_id' => Wave::factory()->create()->id])->assertSessionHasNoErrors();

    expect(Registrant::count())->toBe(2)->and(Person::where('nik', '3201123456789012')->count())->toBe(1);
});

test('the form offers open waves and active organizations and suggests the relation', function () {
    Wave::factory()->create(['is_open' => false]);
    Organization::factory()->create(['is_active' => false]);
    $this->parent->person->update(['gender' => 'female']);

    $this->get(route('portal.children.create'))->assertInertia(fn ($page) => $page
        ->component('portal/children/create')
        ->has('waves', 1)
        ->has('organizations', 1)
        ->where('suggestedRelation', 'mother'));
});
