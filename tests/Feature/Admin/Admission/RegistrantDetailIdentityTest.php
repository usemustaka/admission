<?php

use App\Enums\DefaultRole;
use App\Enums\NoteUrgency;
use App\Enums\NoteVisibility;
use App\Enums\RegistrantStatus;
use App\Models\Note;
use App\Models\Organization;
use App\Models\Parentship;
use App\Models\Person;
use App\Models\Registrant;
use App\Models\Wave;
use App\Support\IdentityMismatchNote;

beforeEach(function () {
    $this->operator = userWithRole(DefaultRole::Operator);
    $this->actingAs($this->operator);

    $this->onRecord = Person::factory()->create(['nik' => '3201999988887777', 'name' => 'Dewi Lestari']);
    $this->organization = Organization::factory()->create();
    $this->wave = Wave::factory()->create();
    $this->registrant = Registrant::factory()->create([
        'person_id' => $this->onRecord->id,
        'organization_id' => $this->organization->id,
        'wave_id' => $this->wave->id,
        'status' => RegistrantStatus::Review,
        'needs_identity_review' => true,
        'submitted_name' => 'Dewi L',
        'registered_as_relation' => 'guardian',
        'registered_as_kind' => null,
    ]);
    $this->mismatch = IdentityMismatchNote::open($this->onRecord, 'Dewi L', '3201999988887777');

    $this->repoint = fn (array $data = []) => $this->post(route('admin.registrants.repoint-identity', $this->registrant), [
        'nik' => '3201111122223333',
        'reason' => 'Orang tua salah ketik NIK',
        ...$data,
    ]);
    $this->isLinked = fn (Person $child) => Parentship::query()->where('person_id', $child->id)->where('related_person_id', $this->registrant->registered_by_person_id)->exists();
});

describe('"NIK sudah cocok"', function () {
    test('clears the flag, links the parent, and leaves a private audit note by the member', function () {
        $this->post(route('admin.registrants.resolve-identity', $this->registrant))->assertSessionHasNoErrors();

        $audit = Note::query()->where('author_id', $this->operator->id)->first();

        expect($this->registrant->refresh()->needs_identity_review)->toBeFalse()
            ->and(($this->isLinked)($this->onRecord))->toBeTrue()
            ->and($audit->noteable_id)->toBe($this->onRecord->id)
            ->and($audit->visibility)->toBe(NoteVisibility::Private)
            ->and($audit->body)->toContain('Dewi L')->toContain('Dewi Lestari');
    });

    test('downgrades the system note instead of deleting it, and only once', function () {
        $this->post(route('admin.registrants.resolve-identity', $this->registrant))->assertSessionHasNoErrors();
        $this->post(route('admin.registrants.resolve-identity', $this->registrant))->assertSessionHasNoErrors();

        $this->mismatch->refresh();

        expect($this->mismatch->urgency)->toBe(NoteUrgency::Normal)
            ->and($this->mismatch->author_id)->toBeNull()
            ->and($this->mismatch->body)->toContain('Mohon dicek')
            ->and(substr_count($this->mismatch->body, 'Selesai dicocokkan oleh '.$this->operator->name))->toBe(1)
            ->and(Note::query()->where('author_id', $this->operator->id)->count())->toBe(1);
    });

    test('needs the right to manage registrants', function () {
        $this->actingAs(registrantViewer())->post(route('admin.registrants.resolve-identity', $this->registrant))->assertForbidden();

        expect($this->registrant->refresh()->needs_identity_review)->toBeTrue();
    });
});

describe('"Ganti NIK pendaftaran"', function () {
    test('moves the registration to the person who owns the NIK, and links the parent when the name matches', function () {
        $right = Person::factory()->create(['nik' => '3201111122223333', 'name' => 'dewi  l.']);

        ($this->repoint)()->assertSessionHasNoErrors();

        $this->registrant->refresh();
        $this->mismatch->refresh();

        expect($this->registrant->person_id)->toBe($right->id)
            ->and($this->registrant->needs_identity_review)->toBeFalse()
            ->and(($this->isLinked)($right))->toBeTrue()
            ->and(($this->isLinked)($this->onRecord))->toBeFalse()
            ->and($this->mismatch->urgency)->toBe(NoteUrgency::Normal)
            ->and($this->mismatch->body)->toContain('Selesai dicocokkan oleh '.$this->operator->name)
            ->and(Person::query()->count())->toBeGreaterThanOrEqual(3);
    });

    test('keeps the flag and warns about the new person when the name still differs', function () {
        $right = Person::factory()->create(['nik' => '3201111122223333', 'name' => 'Sari Wulandari']);

        ($this->repoint)()->assertSessionHasNoErrors();

        $warning = $right->notes()->whereNull('author_id')->first();

        expect($this->registrant->refresh()->person_id)->toBe($right->id)
            ->and($this->registrant->needs_identity_review)->toBeTrue()
            ->and(($this->isLinked)($right))->toBeFalse()
            ->and($warning->urgency)->toBe(NoteUrgency::High)
            ->and($warning->body)->toContain('Sari Wulandari')->toContain('Dewi L');
    });

    test('creates a new person with the typed name when nobody has the NIK', function () {
        ($this->repoint)()->assertSessionHasNoErrors();

        $created = Person::query()->where('nik', '3201111122223333')->firstOrFail();

        expect($created->name)->toBe('Dewi L')
            ->and($this->registrant->refresh()->person_id)->toBe($created->id)
            ->and($this->registrant->needs_identity_review)->toBeFalse()
            ->and(($this->isLinked)($created))->toBeTrue()
            ->and($this->onRecord->refresh()->name)->toBe('Dewi Lestari');
    });

    test('leaves a private audit note by the member on both children', function () {
        $right = Person::factory()->create(['nik' => '3201111122223333', 'name' => 'Dewi L']);

        ($this->repoint)(['reason' => 'Salah ketik'])->assertSessionHasNoErrors();

        $notes = Note::query()->where('author_id', $this->operator->id)->get();

        expect($notes)->toHaveCount(2)
            ->and($notes->pluck('noteable_id')->all())->toEqualCanonicalizing([$this->onRecord->id, $right->id])
            ->and($notes->every(fn (Note $note) => $note->visibility === NoteVisibility::Private && str_contains($note->body, 'Salah ketik')))->toBeTrue();
    });

    test('refuses a person who already has a registration for the same organization and wave', function () {
        $right = Person::factory()->create(['nik' => '3201111122223333', 'name' => 'Dewi L']);
        Registrant::factory()->create(['person_id' => $right->id, 'organization_id' => $this->organization->id, 'wave_id' => $this->wave->id]);

        ($this->repoint)()->assertSessionHasErrors('nik');

        expect($this->registrant->refresh()->person_id)->toBe($this->onRecord->id)
            ->and($this->registrant->needs_identity_review)->toBeTrue();
    });

    test('is fine when the person is registered somewhere else', function () {
        $right = Person::factory()->create(['nik' => '3201111122223333', 'name' => 'Dewi L']);
        Registrant::factory()->create(['person_id' => $right->id, 'wave_id' => $this->wave->id]);

        ($this->repoint)()->assertSessionHasNoErrors();

        expect($this->registrant->refresh()->person_id)->toBe($right->id);
    });

    test('refuses a registration that is already placed', function () {
        $this->registrant->update(['status' => RegistrantStatus::Placed]);

        ($this->repoint)()->assertSessionHasErrors('nik');

        expect($this->registrant->refresh()->person_id)->toBe($this->onRecord->id);
    });

    test('refuses a registration that is not waiting for the NIK check', function () {
        $this->registrant->update(['needs_identity_review' => false]);

        ($this->repoint)()->assertSessionHasErrors('nik');

        expect($this->registrant->refresh()->person_id)->toBe($this->onRecord->id);
    });

    test('refuses the same NIK and the NIK of the parent who registered', function () {
        ($this->repoint)(['nik' => '3201999988887777'])->assertSessionHasErrors('nik');

        ($this->repoint)(['nik' => $this->registrant->registeredBy->nik])->assertSessionHasErrors('nik');

        expect($this->registrant->refresh()->person_id)->toBe($this->onRecord->id);
    });

    test('needs a NIK of 16 digits and a reason', function () {
        ($this->repoint)(['nik' => '123'])->assertSessionHasErrors('nik');
        ($this->repoint)(['reason' => ''])->assertSessionHasErrors('reason');

        expect($this->registrant->refresh()->person_id)->toBe($this->onRecord->id);
    });

    test('needs the right to manage registrants', function () {
        $this->actingAs(registrantViewer());

        ($this->repoint)()->assertForbidden();

        expect($this->registrant->refresh()->person_id)->toBe($this->onRecord->id);
    });
});
