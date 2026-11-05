<?php

use App\Enums\RegistrantStatus;
use App\Models\Parentship;
use App\Models\Person;
use App\Models\Registrant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->parent = User::factory()->create();
    $this->registrant = Registrant::factory()->create();
    $this->child = $this->registrant->person;
    $this->link = Parentship::factory()->primaryContact()->create([
        'person_id' => $this->child->id,
        'related_person_id' => $this->parent->person_id,
        'relation' => 'father',
    ]);
    $this->actingAs($this->parent);

    $this->save = fn (array $data = []) => $this->post(route('portal.children.data.update', $this->registrant), $data);
});

test('the child details and photo are saved as a draft', function () {
    ($this->save)([
        'child' => ['gender' => 'female', 'birth_place' => 'Bogor', 'birth_date' => '2014-05-06', 'nisn' => '0123456789', 'family_card_number' => '3201123456789012', 'religion' => 'Islam', 'blood_type' => 'O', 'child_order' => 2, 'siblings_count' => 3, 'address' => 'Jl. Melati No. 12'],
        'photo' => UploadedFile::fake()->image('profil.jpg'),
    ])->assertRedirect(route('portal.children.data.edit', $this->registrant));

    $child = $this->child->refresh();

    expect($child->birth_place)->toBe('Bogor')
        ->and($child->nisn)->toBe('0123456789')
        ->and($child->religion)->toBe('Islam')
        ->and($child->child_order)->toBe(2)
        ->and($child->address)->toBe('Jl. Melati No. 12')
        ->and($this->registrant->refresh()->status)->toBe(RegistrantStatus::Draft);

    Storage::disk('public')->assertExists($child->photo_path);
});

test('the saved draft comes back when the parent returns', function () {
    ($this->save)(['child' => ['birth_place' => 'Bogor', 'address' => 'Jl. Melati']]);

    $this->get(route('portal.children.data.edit', $this->registrant))->assertInertia(fn ($page) => $page
        ->component('portal/children/data')
        ->where('child.birth_place', 'Bogor')
        ->where('child.name', $this->child->name)
        ->where('parents.father.name', $this->parent->person->name)
        ->where('parents.mother', null)
        ->where('canEdit', true));
});

test('the name and NIK of the child cannot be changed from the portal', function () {
    $name = $this->child->name;
    $nik = $this->child->nik;

    ($this->save)(['child' => ['name' => 'Nama Lain', 'nik' => '3299999999999999', 'birth_place' => 'Bogor']]);

    expect($this->child->refresh()->name)->toBe($name)->and($this->child->nik)->toBe($nik);
});

test('the photo can be replaced and removed, and old files are cleaned up', function () {
    ($this->save)(['photo' => UploadedFile::fake()->image('a.jpg')]);
    $oldPhoto = $this->child->refresh()->photo_path;

    ($this->save)(['photo' => UploadedFile::fake()->image('c.jpg')]);

    Storage::disk('public')->assertMissing($oldPhoto);
    $newPhoto = $this->child->refresh()->photo_path;

    ($this->save)(['remove_photo' => true]);

    Storage::disk('public')->assertMissing($newPhoto);
    expect($this->child->refresh()->photo_path)->toBeNull();
});

test('invalid details are refused', function () {
    ($this->save)(['child' => ['nisn' => '123', 'family_card_number' => '12', 'religion' => 'Lainnya-lain', 'birth_date' => '2999-01-01']])
        ->assertSessionHasErrors(['child.nisn', 'child.family_card_number', 'child.religion', 'child.birth_date']);
});

test('data can no longer be changed once it was submitted for review', function () {
    $this->registrant->update(['status' => RegistrantStatus::Review]);

    ($this->save)(['child' => ['birth_place' => 'Bogor']])->assertForbidden();

    $this->get(route('portal.children.data.edit', $this->registrant))->assertInertia(fn ($page) => $page->where('canEdit', false));
});

test('data can be changed again when the committee asks for a revision', function () {
    $this->registrant->update(['status' => RegistrantStatus::Revision]);

    ($this->save)(['child' => ['birth_place' => 'Bogor']])->assertSessionHasNoErrors();
});

describe('parents', function () {
    test('details of the registering parent are updated on their own person', function () {
        ($this->save)(['parents' => ['father' => ['name' => 'Budi Santoso', 'job' => 'Guru', 'phone' => '081234567890', 'monthly_income' => 'Rp3 juta - Rp5 juta']]]);

        $father = $this->parent->person->refresh();

        expect($father->name)->toBe('Budi Santoso')->and($father->job)->toBe('Guru')->and($father->monthly_income)->toBe('Rp3 juta - Rp5 juta');
    });

    test('the other parent is created and linked, as a biological parent by default', function () {
        ($this->save)(['parents' => ['mother' => ['name' => 'Siti Aminah', 'nik' => '3201987654321098', 'job' => 'Perawat']]]);

        $link = $this->child->parentships()->where('relation', 'mother')->firstOrFail();

        expect($link->relatedPerson->name)->toBe('Siti Aminah')
            ->and($link->relatedPerson->nik)->toBe('3201987654321098')
            ->and($link->kind->value)->toBe('biological')
            ->and($link->is_primary_contact)->toBeFalse();
    });

    test('a parent block left empty creates nothing', function () {
        ($this->save)(['parents' => ['mother' => ['name' => '', 'is_alive' => true], 'guardian' => []]]);

        expect($this->child->parentships()->count())->toBe(1);
    });

    test('a known NIK is linked to the person who already exists, whose details are not overwritten', function () {
        $known = Person::factory()->create(['nik' => '3201987654321098', 'name' => 'Siti Aminah', 'job' => 'Dokter', 'phone' => null]);

        ($this->save)(['parents' => ['mother' => ['name' => 'Nama Lain', 'nik' => '3201987654321098', 'job' => 'Guru', 'phone' => '081234567890']]]);

        $known->refresh();

        expect($this->child->parentships()->where('relation', 'mother')->firstOrFail()->related_person_id)->toBe($known->id)
            ->and($known->name)->toBe('Siti Aminah')
            ->and($known->job)->toBe('Dokter')
            ->and($known->phone)->toBe('081234567890')
            ->and(Person::where('nik', '3201987654321098')->count())->toBe(1);
    });

    test('a NIK of a linked parent can be set while blank but not changed afterwards', function () {
        $this->parent->person->update(['nik' => null]);

        ($this->save)(['parents' => ['father' => ['name' => 'Budi Santoso', 'nik' => '3201111111111111']]]);
        expect($this->parent->person->refresh()->nik)->toBe('3201111111111111');

        ($this->save)(['parents' => ['father' => ['name' => 'Budi Santoso', 'nik' => '3202222222222222']]]);
        expect($this->parent->person->refresh()->nik)->toBe('3201111111111111');
    });

    test('a NIK that belongs to someone else cannot be given to a linked parent', function () {
        $this->parent->person->update(['nik' => null]);
        Person::factory()->create(['nik' => '3203333333333333']);

        ($this->save)(['parents' => ['father' => ['name' => 'Budi', 'nik' => '3203333333333333']]])
            ->assertSessionHasErrors('parents.father.nik');
    });

    test('a parent cannot have the childs NIK', function () {
        ($this->save)(['parents' => ['mother' => ['name' => 'Siti', 'nik' => $this->child->nik]]])
            ->assertSessionHasErrors('parents.mother.nik');

        expect($this->child->parentships()->count())->toBe(1);
    });

    test('a name is needed once other details of a parent are filled in', function () {
        ($this->save)(['parents' => ['mother' => ['job' => 'Guru']]])->assertSessionHasErrors('parents.mother.name');
    });

    test('the kind of a linked father can be corrected to step', function () {
        ($this->save)(['parents' => ['father' => ['name' => 'Budi', 'kind' => 'step']]]);

        expect($this->link->refresh()->kind->value)->toBe('step');
    });
});
