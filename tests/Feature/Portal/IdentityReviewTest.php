<?php

use App\Actions\Admission\ResolveIdentityReview;
use App\Enums\ParentRelation;
use App\Models\Note;
use App\Models\Organization;
use App\Models\Parentship;
use App\Models\Person;
use App\Models\Registrant;
use App\Models\Ruleset;
use App\Models\User;
use App\Models\Wave;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    Ruleset::factory()->create();
    $this->parent = User::factory()->create();
    $this->actingAs($this->parent);

    $this->existing = Person::factory()->create([
        'nik' => '3201999988887777',
        'name' => 'Dewi Lestari',
        'birth_place' => 'Denpasar',
        'address' => 'Jl. Kenanga No. 7',
        'photo_path' => 'people/photos/dewi.jpg',
        'gender' => null,
        'birth_date' => null,
    ]);
    $this->realParent = Parentship::factory()->primaryContact()->create(['person_id' => $this->existing->id, 'relation' => 'mother']);

    $this->post(route('portal.children.store'), [
        'name' => 'Dewi L',
        'nik' => '3201999988887777',
        'gender' => 'female',
        'birth_date' => '2014-05-06',
        'organization_id' => Organization::factory()->create()->id,
        'wave_id' => Wave::factory()->create()->id,
        'relation' => 'guardian',
    ])->assertSessionHasNoErrors();

    $this->registrant = Registrant::firstOrFail();
});

test('the parent is not linked to the child and the record is not changed while the NIK is being confirmed', function () {
    expect($this->registrant->needs_identity_review)->toBeTrue()
        ->and($this->existing->parentships()->count())->toBe(1)
        ->and($this->existing->parentships()->first()->is($this->realParent))->toBeTrue()
        ->and($this->existing->refresh()->gender)->toBeNull()
        ->and($this->existing->birth_date)->toBeNull();
});

test('the parent still sees their registration, with only the name they typed', function () {
    $this->get(route('portal'))
        ->assertInertia(fn ($page) => $page
            ->has('registrants', 1)
            ->where('registrants.0.child.name', 'Dewi L')
            ->where('registrants.0.child.photo_url', null)
            ->where('registrants.0.identity_pending', true)
            ->where('registrants.0.can_edit', false)
            ->has('portal.children', 1)
            ->where('portal.children.0.name', 'Dewi L'))
        ->assertDontSee('Dewi Lestari')
        ->assertDontSee('Denpasar');
});

test('the child page shows nothing from the existing record', function () {
    $this->get(route('portal.children.show', $this->registrant))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('registrant.child.name', 'Dewi L')
            ->where('registrant.identity_pending', true)
            ->where('family', [])
            ->where('completeness.filled', 0))
        ->assertDontSee('Dewi Lestari')
        ->assertDontSee($this->realParent->relatedPerson->name);
});

test('the data page does not open and nothing can be saved', function () {
    $this->get(route('portal.children.data.edit', $this->registrant))
        ->assertRedirect(route('portal.children.show', $this->registrant));

    $this->post(route('portal.children.data.update', $this->registrant), [
        'child' => ['birth_place' => 'Bogor'],
        'photo' => UploadedFile::fake()->image('foto.jpg'),
    ])->assertForbidden();

    expect($this->existing->refresh()->birth_place)->toBe('Denpasar');
});

test('notes shared with the family stay hidden from the parent who is not linked yet', function () {
    $note = Note::factory()->sharedWithFamily()->create(['noteable_id' => $this->existing->id, 'person_id' => $this->existing->id]);

    expect($note->isVisibleTo($this->parent))->toBeFalse();
});

describe('when the committee confirms the NIK', function () {
    beforeEach(function () {
        app(ResolveIdentityReview::class)->handle($this->registrant);
        $this->registrant->refresh();
    });

    test('the flag is cleared and the parent is linked as they said they were', function () {
        $link = Parentship::where('related_person_id', $this->parent->person_id)->firstOrFail();

        expect($this->registrant->needs_identity_review)->toBeFalse()
            ->and($link->person_id)->toBe($this->existing->id)
            ->and($link->relation)->toBe(ParentRelation::Guardian)
            ->and($link->is_primary_contact)->toBeFalse()
            ->and($this->realParent->refresh()->is_primary_contact)->toBeTrue();
    });

    test('the childs details and the data page open for the parent', function () {
        $this->get(route('portal.children.show', $this->registrant))->assertInertia(fn ($page) => $page
            ->where('registrant.child.name', 'Dewi Lestari')
            ->where('registrant.identity_pending', false)
            ->where('registrant.can_edit', true)
            ->has('family', 2));

        $this->get(route('portal.children.data.edit', $this->registrant))->assertOk()
            ->assertInertia(fn ($page) => $page->where('child.birth_place', 'Denpasar'));

        $this->post(route('portal.children.data.update', $this->registrant), ['child' => ['birth_place' => 'Bogor']])->assertSessionHasNoErrors();
    });

    test('confirming again does not link the parent twice', function () {
        app(ResolveIdentityReview::class)->handle($this->registrant);

        expect(Parentship::where('related_person_id', $this->parent->person_id)->count())->toBe(1);
    });
});
