<?php

use App\Enums\Ability;
use App\Enums\BillType;
use App\Enums\DefaultRole;
use App\Enums\RegistrantStatus;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Registrant;
use App\Models\RegistrantStatusChange;
use App\Models\User;
use App\Models\Wave;
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
    $validated = admissionRegistrant(RegistrantStatus::Validated, ['exam_scores' => [80, 70]]);
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

/**
 * A registration that is announceable without warnings: both aspects scored above their KKM and no open bill.
 *
 * @param  array<string, mixed>  $attributes
 */
function announceableRegistrant(array $attributes = []): Registrant
{
    return admissionRegistrant(RegistrantStatus::Validated, ['exam_scores' => [80, 70], ...$attributes]);
}

test('select all runs on every registrant the filters match, beyond the first page', function () {
    $school = Organization::factory()->create();
    $matching = collect(range(1, 30))->map(fn () => admissionRegistrant(attributes: ['organization_id' => $school->id]));
    $other = admissionRegistrant();

    $this->post(route('admin.registrants.bulk'), [
        'action' => 'accept',
        'select_all' => true,
        'filters' => ['lembaga' => $school->id, 'status' => 'review'],
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect(Registrant::query()->where('organization_id', $school->id)->where('status', RegistrantStatus::Validated)->count())->toBe(30)
        ->and($other->refresh()->status)->toBe(RegistrantStatus::Review)
        ->and(bulkFlash('toast')['message'])->toBe('30 berhasil, 0 dilewati.')
        ->and(bulkFlash('bulk')['done'])->toBe(30)
        ->and($matching->first()->statusChanges()->count())->toBe(1);
});

test('select all without filters means every registrant, and skipped ones are reported like in an id based run', function () {
    $ready = admissionRegistrant();
    $draft = admissionRegistrant(RegistrantStatus::Draft);

    $this->post(route('admin.registrants.bulk'), ['action' => 'accept', 'select_all' => true, 'filters' => []])->assertSessionHasNoErrors();

    expect($ready->refresh()->status)->toBe(RegistrantStatus::Validated)
        ->and($draft->refresh()->status)->toBe(RegistrantStatus::Draft)
        ->and(bulkFlash('bulk')['skipped'])->toBe([['id' => $draft->id, 'name' => $draft->person->name, 'reason' => 'Statusnya Draf, bukan Sedang diperiksa']]);
});

test('the preview counts who qualifies and why the rest is skipped, and the run then does exactly that', function () {
    $ready = collect(range(1, 3))->map(fn () => admissionRegistrant());
    admissionRegistrant(RegistrantStatus::Draft);
    admissionRegistrant(RegistrantStatus::Draft);
    $flagged = admissionRegistrant(RegistrantStatus::Review, ['needs_identity_review' => true]);

    $payload = ['action' => 'accept', 'select_all' => true, 'filters' => []];

    $preview = $this->postJson(route('admin.registrants.bulk-preview'), $payload)->assertOk()->assertJson([
        'total' => 6,
        'processed' => 6,
        'capped' => false,
        'eligible' => 3,
        'skipped' => 3,
        'reasons' => [
            ['reason' => 'Statusnya Draf, bukan Sedang diperiksa', 'count' => 2],
            ['reason' => 'Perlu cek NIK dulu', 'count' => 1],
        ],
    ]);

    expect(Registrant::query()->where('status', RegistrantStatus::Validated)->count())->toBe(0)
        ->and(RegistrantStatusChange::query()->count())->toBe(0);

    $this->post(route('admin.registrants.bulk'), $payload);

    expect(bulkFlash('bulk')['done'])->toBe($preview->json('eligible'))
        ->and(bulkFlash('bulk')['skipped_total'])->toBe($preview->json('skipped'))
        ->and($flagged->refresh()->status)->toBe(RegistrantStatus::Review);
});

test('the preview also works for ids picked on the page', function () {
    $ready = admissionRegistrant();
    $draft = admissionRegistrant(RegistrantStatus::Draft);

    $this->postJson(route('admin.registrants.bulk-preview'), ['action' => 'accept', 'ids' => [$ready->id, $draft->id, 999999]])
        ->assertOk()
        ->assertJson(['total' => 3, 'eligible' => 1, 'skipped' => 2, 'capped' => false]);
});

test('a select all run handles at most 1000 registrants and says so', function () {
    $organization = Organization::factory()->create();
    $wave = Wave::factory()->create();
    $parent = Person::factory()->create();
    Registrant::factory()->count(1001)->create([
        'organization_id' => $organization->id,
        'wave_id' => $wave->id,
        'registered_by_person_id' => $parent->id,
        'status' => RegistrantStatus::Draft,
    ]);
    $payload = ['action' => 'accept', 'select_all' => true, 'filters' => ['status' => 'draft']];

    $this->postJson(route('admin.registrants.bulk-preview'), $payload)->assertOk()->assertJson([
        'total' => 1001,
        'processed' => 1000,
        'capped' => true,
        'cap' => 1000,
        'eligible' => 0,
        'skipped' => 1000,
    ]);

    $this->post(route('admin.registrants.bulk'), $payload)->assertRedirect();

    expect(bulkFlash('toast')['message'])->toStartWith('0 berhasil, 1000 dilewati')->toContain('Hanya 1000 dari 1001 hasil')
        ->and(bulkFlash('bulk')['skipped_total'])->toBe(1000)
        ->and(bulkFlash('bulk')['skipped'])->toHaveCount(200);
});

test('select all needs the filters to be a list of plain values and ids are only required without it', function () {
    $this->post(route('admin.registrants.bulk'), ['action' => 'accept', 'select_all' => true, 'filters' => 'semua'])->assertSessionHasErrors('filters');
    $this->post(route('admin.registrants.bulk'), ['action' => 'accept', 'select_all' => true])->assertSessionHasNoErrors();
});

test('announcing in bulk skips registrants with missing scores, a failing result or an open bill unless asked', function () {
    $complete = announceableRegistrant();
    $withoutScores = admissionRegistrant(RegistrantStatus::Validated);
    $failed = announceableRegistrant(['exam_scores' => [50, 50]]);
    $unsettled = announceableRegistrant();
    $bill = Bill::factory()->create(['registrant_id' => $unsettled->id, 'type' => BillType::Settlement]);
    BillItem::factory()->create(['bill_id' => $bill->id, 'amount' => 500000]);
    $settled = announceableRegistrant();
    $settledBill = Bill::factory()->create(['registrant_id' => $settled->id, 'type' => BillType::Settlement]);
    verifiedPayment(BillItem::factory()->create(['bill_id' => $settledBill->id, 'amount' => 500000]), 500000);
    $ids = [$complete->id, $withoutScores->id, $failed->id, $unsettled->id, $settled->id];

    $this->post(route('admin.registrants.bulk'), ['action' => 'announce', 'ids' => $ids])->assertSessionHasNoErrors();

    expect($complete->refresh()->status)->toBe(RegistrantStatus::Announced)
        ->and($settled->refresh()->status)->toBe(RegistrantStatus::Announced)
        ->and($withoutScores->refresh()->status)->toBe(RegistrantStatus::Validated)
        ->and($failed->refresh()->status)->toBe(RegistrantStatus::Validated)
        ->and($unsettled->refresh()->status)->toBe(RegistrantStatus::Validated)
        ->and(bulkFlash('bulk')['done'])->toBe(2)
        ->and(collect(bulkFlash('bulk')['skipped'])->pluck('reason', 'id')->all())->toBe([
            $withoutScores->id => 'Belum lengkap: nilai ujian belum lengkap',
            $failed->id => 'Belum lengkap: belum tuntas',
            $unsettled->id => 'Belum lengkap: tagihan belum lunas',
        ]);

    $this->post(route('admin.registrants.bulk'), ['action' => 'announce', 'ids' => $ids, 'include_incomplete' => true])->assertSessionHasNoErrors();

    expect($withoutScores->refresh()->status)->toBe(RegistrantStatus::Announced)
        ->and($failed->refresh()->status)->toBe(RegistrantStatus::Announced)
        ->and($unsettled->refresh()->status)->toBe(RegistrantStatus::Announced)
        ->and(bulkFlash('bulk')['skipped'])->toBe([['id' => $complete->id, 'name' => $complete->person->name, 'reason' => 'Statusnya Sudah diumumkan, bukan Data valid'], ['id' => $settled->id, 'name' => $settled->person->name, 'reason' => 'Statusnya Sudah diumumkan, bukan Data valid']]);
});

test('the announce preview warns about what is missing and lets the incomplete ones in when asked', function () {
    announceableRegistrant();
    admissionRegistrant(RegistrantStatus::Validated);
    announceableRegistrant(['exam_scores' => [50, 50]]);
    $unsettled = announceableRegistrant(['exam_scores' => [50, null]]);
    $bill = Bill::factory()->create(['registrant_id' => $unsettled->id, 'type' => BillType::Settlement]);
    BillItem::factory()->create(['bill_id' => $bill->id, 'amount' => 500000]);

    $payload = ['action' => 'announce', 'select_all' => true, 'filters' => ['status' => 'validated']];

    $this->postJson(route('admin.registrants.bulk-preview'), $payload)->assertOk()->assertJson([
        'eligible' => 1,
        'skipped' => 3,
        'readiness' => ['missing_scores' => 2, 'not_passed' => 1, 'unsettled' => 1, 'incomplete' => 3],
    ]);

    $this->postJson(route('admin.registrants.bulk-preview'), [...$payload, 'include_incomplete' => true])->assertOk()->assertJson([
        'eligible' => 4,
        'skipped' => 0,
        'readiness' => ['incomplete' => 3],
    ]);
});

test('the announce preview and run agree when incomplete registrants are left out', function () {
    announceableRegistrant();
    admissionRegistrant(RegistrantStatus::Validated);
    $payload = ['action' => 'announce', 'select_all' => true, 'filters' => []];

    $preview = $this->postJson(route('admin.registrants.bulk-preview'), $payload)->json();
    $this->post(route('admin.registrants.bulk'), $payload);

    expect(bulkFlash('bulk')['done'])->toBe($preview['eligible'])->toBe(1)
        ->and(bulkFlash('bulk')['skipped_total'])->toBe($preview['skipped'])->toBe(1);
});

test('requesting a revision in bulk sends the shared reason to every registrant that can take it', function () {
    $first = admissionRegistrant();
    $second = admissionRegistrant();
    $draft = admissionRegistrant(RegistrantStatus::Draft);
    $flagged = admissionRegistrant(RegistrantStatus::Review, ['needs_identity_review' => true]);

    $this->post(route('admin.registrants.bulk'), [
        'action' => 'revise',
        'ids' => [$first->id, $second->id, $draft->id, $flagged->id],
        'note' => 'Foto KK kurang jelas',
    ])->assertSessionHasNoErrors();

    expect($first->refresh()->status)->toBe(RegistrantStatus::Revision)
        ->and($second->refresh()->status)->toBe(RegistrantStatus::Revision)
        ->and($draft->refresh()->status)->toBe(RegistrantStatus::Draft)
        ->and($flagged->refresh()->status)->toBe(RegistrantStatus::Review)
        ->and($first->statusChanges->last()->only(['to_status', 'note', 'user_id', 'is_bypass']))->toBe(['to_status' => RegistrantStatus::Revision, 'note' => 'Foto KK kurang jelas', 'user_id' => $this->admin->id, 'is_bypass' => false])
        ->and($draft->statusChanges()->count())->toBe(0)
        ->and(bulkFlash('bulk')['done'])->toBe(2)
        ->and(collect(bulkFlash('bulk')['skipped'])->pluck('reason')->all())->toBe(['Statusnya Draf, bukan Sedang diperiksa', 'Perlu cek NIK dulu']);
});

test('a bulk revision needs a reason of a sensible length, but its preview does not', function () {
    $registrant = admissionRegistrant();

    $this->post(route('admin.registrants.bulk'), ['action' => 'revise', 'ids' => [$registrant->id]])->assertSessionHasErrors(['note' => 'Tulis apa yang perlu diperbaiki.']);
    $this->post(route('admin.registrants.bulk'), ['action' => 'revise', 'ids' => [$registrant->id], 'note' => '   '])->assertSessionHasErrors('note');
    $this->post(route('admin.registrants.bulk'), ['action' => 'revise', 'ids' => [$registrant->id], 'note' => str_repeat('a', 1001)])->assertSessionHasErrors('note');
    expect($registrant->refresh()->status)->toBe(RegistrantStatus::Review);

    $this->postJson(route('admin.registrants.bulk-preview'), ['action' => 'revise', 'ids' => [$registrant->id]])->assertOk()->assertJson(['eligible' => 1]);
});

test('staff who can only view applicants cannot run, preview or select all in bulk', function () {
    $this->actingAs(registrantViewer());
    $registrant = admissionRegistrant();

    $this->post(route('admin.registrants.bulk'), ['action' => 'accept', 'ids' => [$registrant->id]])->assertForbidden();
    $this->post(route('admin.registrants.bulk'), ['action' => 'accept', 'select_all' => true, 'filters' => []])->assertForbidden();
    $this->postJson(route('admin.registrants.bulk-preview'), ['action' => 'accept', 'ids' => [$registrant->id]])->assertForbidden();

    expect($registrant->refresh()->status)->toBe(RegistrantStatus::Review);
});
