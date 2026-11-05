<?php

use App\Enums\DefaultRole;
use App\Models\Note;
use App\Models\Parentship;
use App\Models\Person;
use App\Models\Registrant;
use App\Models\User;

test('guests and users without staff access cannot open the quick view', function () {
    $person = Person::factory()->create();

    $this->getJson(route('admin.people.quick-view', $person))->assertUnauthorized();

    $this->actingAs(User::factory()->create())->getJson(route('admin.people.quick-view', $person))->assertForbidden();
});

test('the quick view summarizes identity, family, contact, notes and registrations', function () {
    $staff = userWithRole(DefaultRole::Operator);
    $this->actingAs($staff);

    $child = Person::factory()->create(['name' => 'Anak Uji', 'nik' => '3201111111111111', 'phone' => null]);
    $mother = Person::factory()->create(['name' => 'Ibu Uji', 'phone' => '081234567890']);
    Parentship::factory()->primaryContact()->create(['person_id' => $child->id, 'related_person_id' => $mother->id]);
    $registrant = Registrant::factory()->create(['person_id' => $child->id]);
    Note::factory()->public()->create(['noteable_id' => $child->id, 'body' => 'Berkas KK belum jelas.']);

    $this->getJson(route('admin.people.quick-view', $child))
        ->assertOk()
        ->assertJsonPath('data.person.name', 'Anak Uji')
        ->assertJsonPath('data.person.nik', '3201111111111111')
        ->assertJsonPath('data.roles', ['Calon siswa'])
        ->assertJsonPath('data.primary_contact.name', 'Ibu Uji')
        ->assertJsonPath('data.primary_contact.phone', '081234567890')
        ->assertJsonPath('data.parents.0.relation', 'Ayah')
        ->assertJsonPath('data.notes.0.body', 'Berkas KK belum jelas.')
        ->assertJsonPath('data.registrations.0.id', $registrant->id)
        ->assertJsonPath('data.registrations.0.is_self', true);

    $this->getJson(route('admin.people.quick-view', $mother))
        ->assertOk()
        ->assertJsonPath('data.roles', ['Orang tua/wali'])
        ->assertJsonPath('data.dependents.0.name', 'Anak Uji')
        ->assertJsonPath('data.registrations.0.child_name', 'Anak Uji')
        ->assertJsonPath('data.registrations.0.is_self', false);
});

test('the quick view only shows notes the viewer is allowed to read', function () {
    $this->actingAs(userWithRole(DefaultRole::Operator));
    $person = Person::factory()->create();
    Note::factory()->create(['noteable_id' => $person->id, 'body' => 'Rahasia penulis lain']);
    Note::factory()->public()->create(['noteable_id' => $person->id, 'body' => 'Boleh dibaca panitia']);

    $this->getJson(route('admin.people.quick-view', $person))
        ->assertOk()
        ->assertJsonCount(1, 'data.notes')
        ->assertJsonPath('data.notes.0.body', 'Boleh dibaca panitia');
});

test('the detail page lists the parts a person plays and their related registrations', function () {
    $this->actingAs(userWithRole(DefaultRole::Operator));
    $registrant = Registrant::factory()->create();

    $this->get(route('admin.people.show', $registrant->person))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('roles', ['Calon siswa'])
            ->where('registrations.0.id', $registrant->id));
});
