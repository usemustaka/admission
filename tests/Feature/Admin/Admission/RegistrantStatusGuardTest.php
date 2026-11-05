<?php

use App\Actions\Admission\ChangeRegistrantStatus;
use App\Enums\BillType;
use App\Enums\DefaultRole;
use App\Enums\RegistrantStatus;
use App\Models\Parentship;
use App\Models\Person;
use App\Models\Registrant;
use App\Models\Student;
use App\Models\Year;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->admin = userWithRole(DefaultRole::Admin);
    $this->actingAs($this->admin);
    $this->registrant = admissionRegistrant();
});

describe('the stored status decides', function () {
    test('accepting twice in a row refuses the second as a validation error and writes one history row', function () {
        $this->post(route('admin.registrants.accept', $this->registrant))->assertSessionHasNoErrors();

        $this->post(route('admin.registrants.accept', $this->registrant))
            ->assertSessionHasErrors(['status' => 'Pendaftaran yang berstatus "Data valid" tidak bisa langsung dipindah ke "Data valid".']);

        expect($this->registrant->statusChanges()->count())->toBe(1)
            ->and($this->registrant->bills()->where('type', BillType::Settlement)->count())->toBe(1);
    });

    test('a move is checked against the status stored now, not the one loaded earlier', function () {
        $stale = Registrant::query()->findOrFail($this->registrant->id);
        Registrant::query()->whereKey($stale->id)->update(['status' => RegistrantStatus::Validated]);

        expect(fn () => app(ChangeRegistrantStatus::class)->handle($stale, RegistrantStatus::Validated, $this->admin))
            ->toThrow(ValidationException::class);

        expect($this->registrant->statusChanges()->count())->toBe(0)
            ->and($stale->status)->toBe(RegistrantStatus::Validated);
    });

    test('a bypass records the status it really came from', function () {
        $stale = Registrant::query()->findOrFail($this->registrant->id);
        Registrant::query()->whereKey($stale->id)->update(['status' => RegistrantStatus::Validated]);

        app(ChangeRegistrantStatus::class)->handle($stale, RegistrantStatus::Revision, $this->admin, 'Ada yang salah', bypass: true);

        expect($this->registrant->statusChanges()->sole()->from_status)->toBe(RegistrantStatus::Validated);

        $other = Registrant::query()->findOrFail($this->registrant->id);
        Registrant::query()->whereKey($other->id)->update(['status' => RegistrantStatus::Announced]);

        expect(fn () => app(ChangeRegistrantStatus::class)->handle($other, RegistrantStatus::Announced, $this->admin, 'Sama saja', bypass: true))
            ->toThrow(ValidationException::class, 'sudah berstatus itu');
    });
});

describe('while the NIK is unconfirmed', function () {
    beforeEach(function () {
        $this->flagged = admissionRegistrant(RegistrantStatus::Review, ['needs_identity_review' => true]);
    });

    test('a bypass to a status that speaks to the family is refused', function (string $status) {
        $this->post(route('admin.registrants.bypass', $this->flagged), ['status' => $status, 'note' => 'Berkas sudah diperiksa'])
            ->assertSessionHasErrors('status');

        expect($this->flagged->refresh()->status)->toBe(RegistrantStatus::Review)
            ->and($this->flagged->statusChanges()->count())->toBe(0)
            ->and($this->flagged->bills)->toBeEmpty();
    })->with(['validated', 'announced', 'placed']);

    test('the refusal for Valid says to settle the NIK first', function () {
        $this->post(route('admin.registrants.bypass', $this->flagged), ['status' => 'validated', 'note' => 'Berkas sudah diperiksa'])
            ->assertSessionHasErrors(['status' => 'Selesaikan dulu pencocokan NIK dan nama anak sebelum memindahkan ke "Data valid".']);
    });

    test('a bypass to a status that stays with the committee is still possible', function () {
        $this->post(route('admin.registrants.bypass', $this->flagged), ['status' => 'draft', 'note' => 'Mulai dari awal'])
            ->assertSessionHasNoErrors();

        expect($this->flagged->refresh()->status)->toBe(RegistrantStatus::Draft);
    });

    test('a flagged registrant that is already valid cannot be announced or moved on by the flow', function () {
        $this->flagged->update(['status' => RegistrantStatus::Validated]);

        $this->post(route('admin.registrants.announce', $this->flagged))->assertSessionHasErrors('status');
        $this->post(route('admin.registrants.bypass', $this->flagged), ['status' => 'announced', 'note' => 'Langsung'])->assertSessionHasErrors('status');

        expect($this->flagged->refresh()->status)->toBe(RegistrantStatus::Validated);
    });

    test('the data cannot be sent back to a parent who cannot edit it yet', function () {
        $this->post(route('admin.registrants.revise', $this->flagged), ['note' => 'Foto KK buram'])->assertSessionHasErrors('status');

        expect($this->flagged->refresh()->status)->toBe(RegistrantStatus::Review);
    });

    test('the detail leaves out the announcement and the steps that are ahead', function () {
        $this->flagged->update(['status' => RegistrantStatus::Validated]);
        Person::query()->whereKey(Parentship::query()->where('person_id', $this->flagged->person_id)->value('related_person_id'))->update(['phone' => '0812-3456-7890']);

        $this->get(route('admin.registrants.show', $this->flagged))->assertInertia(fn ($page) => $page
            ->where('announcement', null)
            ->where('can.announce', false)
            ->where('can.revise', false)
            ->where('can.bypass', true)
            ->where('statuses', fn ($statuses) => collect($statuses)->pluck('value')->intersect(['validated', 'announced', 'placed'])->isEmpty()));

        $this->flagged->update(['needs_identity_review' => false]);

        $this->get(route('admin.registrants.show', $this->flagged))->assertInertia(fn ($page) => $page
            ->where('announcement.recipient.phone', '0812-3456-7890')
            ->where('can.announce', true));
    });

    test('the workflow offers nothing that moves it forward in the list either', function () {
        $this->get(route('admin.registrants.index'))->assertInertia(fn ($page) => $page
            ->where('registrants.data', fn ($rows) => collect($rows)->every(fn ($row) => ! $row['needs_identity_review'] || (! $row['can']['accept'] && ! $row['can']['revise']))));
    });
});

describe('placing under a race', function () {
    beforeEach(function () {
        $this->registrant->update(['status' => RegistrantStatus::Announced]);
        $this->year = Year::factory()->active()->create();
    });

    test('a student number taken by another request in the meantime is a validation error, not a server error', function () {
        Student::creating(fn () => DB::table('students')->insert(Student::factory()->raw(['nis' => '2026001'])));

        $this->post(route('admin.registrants.place', $this->registrant), ['year_id' => $this->year->id, 'nis' => '2026001'])
            ->assertSessionHasErrors(['nis' => 'Nomor induk ini baru saja dipakai siswa lain. Pakai nomor yang lain.']);

        expect($this->registrant->refresh()->status)->toBe(RegistrantStatus::Announced)
            ->and(Student::query()->count())->toBe(0);
    });

    test('a registration placed by another request in the meantime is a validation error, not a server error', function () {
        Student::creating(fn () => DB::table('students')->insert(Student::factory()->raw(['registrant_id' => $this->registrant->id, 'nis' => '1'])));

        $this->post(route('admin.registrants.place', $this->registrant), ['year_id' => $this->year->id, 'nis' => '2026001'])
            ->assertSessionHasErrors(['status' => 'Anak ini baru saja ditempatkan oleh orang lain. Muat ulang halamannya.']);

        expect($this->registrant->refresh()->status)->toBe(RegistrantStatus::Announced);
    });

    test('placing twice in a row refuses the second as a validation error', function () {
        $payload = ['year_id' => $this->year->id, 'nis' => '2026001'];

        $this->post(route('admin.registrants.place', $this->registrant), $payload)->assertSessionHasNoErrors();
        $this->post(route('admin.registrants.place', $this->registrant), $payload)->assertSessionHasErrors();

        expect(Student::query()->count())->toBe(1)
            ->and($this->registrant->statusChanges()->count())->toBe(1);
    });
});
