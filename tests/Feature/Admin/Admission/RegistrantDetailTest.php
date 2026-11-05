<?php

use App\Enums\DefaultRole;
use App\Enums\NoteUrgency;
use App\Enums\RegistrantStatus;
use App\Models\Note;
use App\Models\Parentship;
use App\Models\Person;
use App\Models\Registrant;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->operator = userWithRole(DefaultRole::Operator);
    $this->actingAs($this->operator);
    $this->registrant = admissionRegistrant();
});

describe('the full data view', function () {
    test('the detail carries every field of the data form, for the child and each parent', function () {
        $this->registrant->person->update([
            'birth_place' => 'Bandung',
            'nisn' => '0012345678',
            'blood_type' => 'AB',
            'child_order' => 2,
            'siblings_count' => 3,
        ]);
        $mother = Person::factory()->create(['job' => 'Guru', 'monthly_income' => 'Rp3 juta - Rp5 juta', 'last_education' => 'S1 / D4', 'address' => 'Jl. Ayah 1']);
        Parentship::factory()->create(['person_id' => $this->registrant->person_id, 'related_person_id' => $mother->id, 'relation' => 'mother']);

        $this->get(route('admin.registrants.show', $this->registrant))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('data.restricted', false)
                ->where('data.child.name', $this->registrant->person->name)
                ->where('data.child.nik', $this->registrant->person->nik)
                ->where('data.child.nisn', '0012345678')
                ->where('data.child.blood_type', 'AB')
                ->where('data.child.child_order', 2)
                ->where('data.child.siblings_count', 3)
                ->where('data.child.address', 'Jl. Melati 3')
                ->where('data.child.family_card_number', '3201234567890123')
                ->where('data.child.religion', 'Islam')
                ->where('data.child.person_id', $this->registrant->person_id)
                ->has('data.child.gender_label')
                ->has('data.child.birth')
                ->has('data.parents', 2)
                ->where('data.parents.1.relation', 'mother')
                ->where('data.parents.1.person_id', $mother->id)
                ->where('data.parents.1.nik', $mother->nik)
                ->where('data.parents.1.job', 'Guru')
                ->where('data.parents.1.monthly_income', 'Rp3 juta - Rp5 juta')
                ->where('data.parents.1.last_education', 'S1 / D4')
                ->where('data.parents.1.address', 'Jl. Ayah 1')
                ->where('data.parents.1.phone', $mother->phone));
    });

    test('nothing of the person on record is sent while the NIK is unconfirmed', function () {
        $registrant = Registrant::factory()->create(['needs_identity_review' => true, 'submitted_name' => 'Dewi L', 'status' => RegistrantStatus::Review]);
        $registrant->person->update(['address' => 'Rahasia 7', 'photo_path' => 'people/photos/x.jpg', 'religion' => 'Islam']);
        Parentship::factory()->primaryContact()->create(['person_id' => $registrant->person_id]);

        $this->get(route('admin.registrants.show', $registrant))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('data.restricted', true)
                ->where('data.child', ['name' => 'Dewi L'])
                ->where('data.parents', [])
                ->where('family', [])
                ->where('registrant.child.photo_url', null)
                ->where('contact.label', 'Yang mendaftarkan'));
    });
});

describe('readiness', function () {
    test('the gaps show at review and at valid, and not once the result is announced', function () {
        $registrant = Registrant::factory()->create(['status' => RegistrantStatus::Review]);

        foreach ([RegistrantStatus::Review, RegistrantStatus::Validated] as $status) {
            $registrant->update(['status' => $status]);

            $this->get(route('admin.registrants.show', $registrant))
                ->assertInertia(fn (AssertableInertia $page) => $page->where('readiness', fn ($gaps) => collect($gaps)->contains('Jenis kelamin anak') || collect($gaps)->contains('Agama anak')));

            $this->getJson(route('admin.registrants.quick-view', $registrant))->assertJsonPath('readiness', fn ($gaps) => count($gaps) > 0);
        }

        $registrant->update(['status' => RegistrantStatus::Announced]);

        $this->get(route('admin.registrants.show', $registrant))->assertInertia(fn (AssertableInertia $page) => $page->where('readiness', []));
    });

    test('a complete registration under review has no gaps', function () {
        $this->get(route('admin.registrants.show', $this->registrant))->assertInertia(fn (AssertableInertia $page) => $page->where('readiness', []));
    });
});

describe('notes', function () {
    test('the notes the user may read are listed with the urgent and the system ones first', function () {
        $person = $this->registrant->person;
        Note::factory()->public()->create(['noteable_id' => $person->id, 'body' => 'Biasa saja', 'urgency' => NoteUrgency::Normal, 'created_at' => now()]);
        Note::factory()->public()->create(['noteable_id' => $person->id, 'author_id' => null, 'body' => 'Cek NIK', 'urgency' => NoteUrgency::High, 'created_at' => now()->subDay()]);
        Note::factory()->create(['noteable_id' => $person->id, 'body' => 'Rahasia orang lain']);
        Note::factory()->create(['noteable_id' => $person->id, 'author_id' => $this->operator->id, 'body' => 'Catatan pribadiku', 'created_at' => now()->subDays(2)]);

        $this->get(route('admin.registrants.show', $this->registrant))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('notes', 3)
                ->where('notes.0.body', 'Cek NIK')
                ->where('notes.0.author.name', 'Sistem')
                ->where('notes.0.urgency', 'high')
                ->where('notes.1.body', 'Biasa saja')
                ->where('notes.2.body', 'Catatan pribadiku')
                ->where('access.notes', true)
                ->where('access.createNote', true)
                ->has('noteOptions.urgencies'));
    });

    test('notes stay out of the page without the right to view them, and the form without the right to write', function () {
        Note::factory()->public()->create(['noteable_id' => $this->registrant->person_id]);
        $viewer = registrantViewer();
        $viewer->givePermissionTo('core::note.view');

        $this->actingAs($viewer)->get(route('admin.registrants.show', $this->registrant))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('notes', 1)->where('access.createNote', false)->where('noteOptions', null));

        $viewer->revokePermissionTo('core::note.view');

        $this->actingAs($viewer->fresh())->get(route('admin.registrants.show', $this->registrant))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('notes', [])->where('access.notes', false));
    });

    test('a note can be written about the child from the detail page, but not without the right', function () {
        $payload = ['noteable_type' => 'person', 'noteable_id' => $this->registrant->person_id, 'body' => 'Sudah ditelepon', 'urgency' => 'normal', 'visibility' => 'public'];

        $this->post(route('admin.notes.store'), $payload)->assertRedirect();

        expect(Note::query()->where('body', 'Sudah ditelepon')->first())->author_id->toBe($this->operator->id);

        $this->actingAs(registrantViewer())->post(route('admin.notes.store'), [...$payload, 'body' => 'Tidak boleh'])->assertForbidden();
    });
});

describe('links', function () {
    test('the page tells which links the user may follow', function () {
        $this->get(route('admin.registrants.show', $this->registrant))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('access.person', true)->where('access.payments', true));

        $this->actingAs(registrantViewer())->get(route('admin.registrants.show', $this->registrant))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('access.person', false)->where('access.payments', false));
    });

    test('the detail is closed to users without registrant rights', function () {
        $this->actingAs(User::factory()->create())->get(route('admin.registrants.show', $this->registrant))->assertForbidden();
    });
});

describe('audit notes for data edits', function () {
    test('an admin edit lists the changed fields in a private note by the member', function () {
        $this->post(route('admin.registrants.data.update', $this->registrant), [
            'child' => ['religion' => 'Hindu', 'address' => 'Jl. Baru 9', 'blood_type' => 'O'],
            'parents' => ['mother' => ['name' => 'Ibu Baru', 'job' => 'Dokter']],
        ])->assertSessionHasNoErrors();

        $note = Note::query()->where('author_id', $this->operator->id)->firstOrFail();

        expect($note->noteable_id)->toBe($this->registrant->person_id)
            ->and($note->visibility->value)->toBe('private')
            ->and($note->body)->toContain('Agama')->toContain('Alamat')->toContain('Golongan darah')
            ->and($note->body)->toContain('Ibu (ditambahkan)')
            ->and($note->body)->not->toContain('Tempat lahir');
    });

    test('saving without changes writes no note', function () {
        $this->post(route('admin.registrants.data.update', $this->registrant), ['child' => ['religion' => 'Islam'], 'parents' => []])->assertSessionHasNoErrors();

        expect(Note::query()->count())->toBe(0);
    });

    test('a parent editing their own draft leaves no audit note', function () {
        $parent = User::factory()->create();
        $draft = Registrant::factory()->create(['status' => RegistrantStatus::Draft]);
        Parentship::factory()->primaryContact()->create(['person_id' => $draft->person_id, 'related_person_id' => $parent->person_id]);

        $this->actingAs($parent)->post(route('portal.children.data.update', $draft), ['child' => ['birth_place' => 'Bogor']])->assertSessionHasNoErrors();

        expect($draft->person->refresh()->birth_place)->toBe('Bogor')
            ->and(Note::query()->count())->toBe(0);
    });
});

describe('announce warnings', function () {
    test('the detail and the quick view say what is unfinished before announcing, while announcing stays allowed', function () {
        $registrant = admissionRegistrant(RegistrantStatus::Validated);

        $this->get(route('admin.registrants.show', $registrant))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('announceWarnings', ['Nilai ujian belum lengkap']));

        $this->getJson(route('admin.registrants.quick-view', $registrant))->assertJsonPath('announce_warnings', ['Nilai ujian belum lengkap']);

        $this->post(route('admin.registrants.announce', $registrant))->assertSessionHasNoErrors();

        expect($registrant->refresh()->status)->toBe(RegistrantStatus::Announced);
    });

    test('there are no warnings when the registration is not ready to announce at all', function () {
        $this->get(route('admin.registrants.show', $this->registrant))->assertInertia(fn (AssertableInertia $page) => $page->where('announceWarnings', []));
    });
});
