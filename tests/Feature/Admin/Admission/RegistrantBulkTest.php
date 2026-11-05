<?php

use App\Enums\BillType;
use App\Enums\DefaultRole;
use App\Enums\RegistrantStatus;
use App\Models\Registrant;
use App\Models\User;

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

test('staff who may only view skip everything and change nothing', function () {
    $this->actingAs(registrantViewer());
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
