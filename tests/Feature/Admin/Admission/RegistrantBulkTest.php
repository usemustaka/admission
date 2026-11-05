<?php

use App\Enums\Ability;
use App\Enums\BillType;
use App\Enums\DefaultRole;
use App\Enums\RegistrantStatus;
use App\Models\Registrant;
use App\Models\RegistrantStatusChange;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;

/**
 * @return array<string, mixed>
 */
function bulkFlash(string $key): array
{
    return session("inertia.flash_data.{$key}") ?? [];
}

beforeEach(function () {
    $this->admin = userWithRole(DefaultRole::Operator);
    $this->actingAs($this->admin);
});

test('accepting in bulk validates each registrant and issues their settlement bill', function () {
    $first = admissionRegistrant();
    $second = admissionRegistrant();

    $this->post(route('admin.registrants.bulk'), ['action' => 'accept', 'ids' => [$first->id, $second->id]])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($first->refresh()->status)->toBe(RegistrantStatus::Validated)
        ->and($second->refresh()->status)->toBe(RegistrantStatus::Validated)
        ->and($first->bills()->where('type', BillType::Settlement)->exists())->toBeTrue()
        ->and($first->statusChanges->last()->user_id)->toBe($this->admin->id);
});

test('registrants that do not qualify are skipped with a reason and never block the others', function () {
    $ready = admissionRegistrant();
    $draft = admissionRegistrant(RegistrantStatus::Draft);
    $flagged = admissionRegistrant(RegistrantStatus::Review, ['needs_identity_review' => true]);

    $this->post(route('admin.registrants.bulk'), ['action' => 'accept', 'ids' => [$ready->id, $draft->id, $flagged->id, 999999]])->assertRedirect();

    expect($ready->refresh()->status)->toBe(RegistrantStatus::Validated)
        ->and($draft->refresh()->status)->toBe(RegistrantStatus::Draft)
        ->and($flagged->refresh()->status)->toBe(RegistrantStatus::Review);

    expect(bulkFlash('toast')['type'])->toBe('warning')
        ->and(bulkFlash('toast')['message'])->toStartWith('1 berhasil, 3 dilewati')
        ->and(collect(bulkFlash('bulk')['skipped'])->pluck('reason')->all())->toBe([
            'Statusnya Draf, bukan Sedang diperiksa',
            'Perlu cek NIK dulu',
            'Tidak ditemukan',
        ]);
});

test('announcing in bulk only takes validated registrants', function () {
    $validated = admissionRegistrant(RegistrantStatus::Validated);
    $reviewing = admissionRegistrant();

    $this->post(route('admin.registrants.bulk'), ['action' => 'announce', 'ids' => [$validated->id, $reviewing->id]]);

    expect($validated->refresh()->status)->toBe(RegistrantStatus::Announced)
        ->and($reviewing->refresh()->status)->toBe(RegistrantStatus::Review);

    expect(bulkFlash('toast')['message'])->toContain('1 berhasil, 1 dilewati')->toContain('bukan Data valid');
});

test('staff who may bulk-process but not manage skip everything and change nothing', function () {
    $this->actingAs(tap(registrantViewer())->givePermissionTo(Ability::RegistrantBulk->value));
    $registrant = admissionRegistrant();

    $this->post(route('admin.registrants.bulk'), ['action' => 'accept', 'ids' => [$registrant->id]]);

    expect($registrant->refresh()->status)->toBe(RegistrantStatus::Review);
    expect(bulkFlash('toast')['message'])->toStartWith('0 berhasil, 1 dilewati')->toContain('tidak punya izin');
});

test('people without access to applicants cannot run bulk actions', function () {
    $registrant = admissionRegistrant();

    $this->actingAs(User::factory()->create())->post(route('admin.registrants.bulk'), ['action' => 'accept', 'ids' => [$registrant->id]])->assertForbidden();

    expect($registrant->refresh()->status)->toBe(RegistrantStatus::Review);
});

test('a bulk request needs a known action and a sensible list of ids', function () {
    $registrant = admissionRegistrant();

    $this->post(route('admin.registrants.bulk'), ['action' => 'delete', 'ids' => [$registrant->id]])->assertSessionHasErrors('action');
    $this->post(route('admin.registrants.bulk'), ['action' => 'accept', 'ids' => []])->assertSessionHasErrors('ids');
    $this->post(route('admin.registrants.bulk'), ['action' => 'accept', 'ids' => range(1, 201)])->assertSessionHasErrors('ids');
    $this->post(route('admin.registrants.bulk'), ['action' => 'accept'])->assertSessionHasErrors('ids');

    expect(Registrant::query()->where('status', RegistrantStatus::Validated)->exists())->toBeFalse();
});

test('an unexpected failure on one registrant skips only that one, with a reason, and is reported', function () {
    Exceptions::fake();
    $broken = admissionRegistrant();
    $fine = admissionRegistrant();
    RegistrantStatusChange::creating(function (RegistrantStatusChange $change) use ($broken) {
        if ($change->registrant_id === $broken->id) {
            throw new RuntimeException('Disk penuh');
        }
    });

    $this->post(route('admin.registrants.bulk'), ['action' => 'accept', 'ids' => [$broken->id, $fine->id]])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($fine->refresh()->status)->toBe(RegistrantStatus::Validated)
        ->and($broken->refresh()->status)->toBe(RegistrantStatus::Review)
        ->and($broken->bills)->toBeEmpty()
        ->and(bulkFlash('toast')['type'])->toBe('warning')
        ->and(bulkFlash('toast')['message'])->toBe('1 berhasil, 1 dilewati: Gagal, coba lagi (1).')
        ->and(bulkFlash('bulk')['skipped'])->toBe([['id' => $broken->id, 'name' => $broken->person->name, 'reason' => 'Gagal, coba lagi']]);

    Exceptions::assertReported(fn (RuntimeException $exception) => $exception->getMessage() === 'Disk penuh');
});

test('a registrant that moved on after the list was loaded is skipped by its stored status', function () {
    $moved = admissionRegistrant();
    $waiting = admissionRegistrant();
    $moveOnce = true;
    Registrant::retrieved(function (Registrant $registrant) use ($moved, &$moveOnce) {
        if ($moveOnce && $registrant->is($moved)) {
            $moveOnce = false;
            DB::table('registrants')->where('id', $moved->id)->update(['status' => RegistrantStatus::Validated->value]);
        }
    });

    $this->post(route('admin.registrants.bulk'), ['action' => 'accept', 'ids' => [$moved->id, $waiting->id]])->assertRedirect();

    expect($waiting->refresh()->status)->toBe(RegistrantStatus::Validated)
        ->and($moved->statusChanges()->count())->toBe(0)
        ->and($moved->bills)->toBeEmpty()
        ->and(collect(bulkFlash('bulk')['skipped'])->pluck('reason')->all())->toBe(['Statusnya Data valid, bukan Sedang diperiksa']);
});
