<?php

use App\Actions\Admission\SaveRuleset;
use App\Actions\Admission\SyncBills;
use App\Actions\Admission\SyncExamAspects;
use App\Actions\Admission\SyncExamSchedule;
use App\Enums\BillStatus;
use App\Enums\BillType;
use App\Enums\DefaultRole;
use App\Enums\RegistrantStatus;
use App\Models\Bill;
use App\Models\ExamSession;
use App\Models\Organization;
use App\Models\Parentship;
use App\Models\Payment;
use App\Models\Registrant;
use App\Models\Student;
use App\Models\User;
use App\Models\Wave;
use App\Models\Year;
use App\Support\Announcement;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * The whole journey through the application's own pages: a parent registers a child and pays, sends the data,
 * the committee validates it, the parent pays the settlement, and the committee scores, announces and places.
 */
test('a child goes from registration to placement', function () {
    Storage::fake('public');
    Storage::fake('local');

    $organization = Organization::factory()->create();
    $wave = Wave::factory()->create();
    $year = Year::factory()->active()->create();
    $ruleset = app(SaveRuleset::class)->handle(null, 'Aturan uji', null, $organization->id, null, true);
    app(SyncBills::class)->handle($ruleset, [
        'registration' => [['name' => 'Formulir', 'amount' => 100000]],
        'settlement' => [['name' => 'Uang pangkal', 'amount' => 1000000]],
    ]);
    app(SyncExamAspects::class)->handle($ruleset, [['name' => 'Tes tulis', 'passing_score' => 70]]);
    app(SyncExamSchedule::class)->handle($organization->id, null, [['date' => now()->addWeek()->toDateString(), 'sessions' => [['label' => 'Sesi 1', 'start_time' => '08:00', 'end_time' => '10:00', 'quota' => 5]]]]);

    $parent = User::factory()->create();
    $admin = userWithRole(DefaultRole::Admin);

    // The parent registers the child.
    $this->actingAs($parent)->post(route('portal.children.store'), [
        'name' => 'Ahmad Fauzi',
        'nik' => '3201123456789012',
        'gender' => 'male',
        'birth_date' => '2014-05-06',
        'organization_id' => $organization->id,
        'wave_id' => $wave->id,
        'relation' => 'father',
        'kind' => 'biological',
    ])->assertSessionHasNoErrors();

    $registrant = Registrant::query()->firstOrFail();
    expect($registrant->status)->toBe(RegistrantStatus::Draft)
        ->and($registrant->currentStep())->toBe('registration-bill');

    // The data cannot be sent before the registration bill is paid.
    $this->post(route('portal.children.submit', $registrant))->assertSessionHasErrors('status');

    // The parent pays, and the committee verifies it.
    $item = Bill::query()->where('type', BillType::Registration)->firstOrFail()->items->firstOrFail();
    $this->post(route('portal.payments.store'), [
        'items' => [['bill_item_id' => $item->id, 'amount' => 100000]],
        'paid_on' => today()->toDateString(),
        'method' => 'transfer',
        'proof' => UploadedFile::fake()->image('bukti.jpg'),
    ])->assertSessionHasNoErrors();

    $this->actingAs($admin)->put(route('admin.payments.verify', Payment::query()->firstOrFail()))->assertSessionHasNoErrors();
    expect($registrant->refresh()->currentStep())->toBe('data');

    // The parent fills in the data, picks a session and sends it.
    $this->actingAs($parent)->post(route('portal.children.data.update', $registrant), [
        'child' => ['birth_place' => 'Bandung', 'religion' => 'Islam', 'address' => 'Jl. Melati 3', 'family_card_number' => '3201234567890123'],
        'parents' => ['father' => ['name' => 'Fauzi Rahman', 'phone' => '081234567890']],
        'photo' => UploadedFile::fake()->image('profil.jpg'),
    ])->assertSessionHasNoErrors();

    // The ruleset asks for the family card, birth certificate and every report card.
    $this->post(route('portal.children.submit', $registrant))->assertSessionHasErrors('status');
    foreach ([['family-card', 1], ['birth-certificate', 1], ...array_map(fn (int $semester): array => ['report-card', $semester], range(1, 5))] as [$type, $slot]) {
        $this->post(route('portal.children.documents.store', $registrant), ['type' => $type, 'slot' => $slot, 'file' => UploadedFile::fake()->create("{$type}.pdf", 100, 'application/pdf')])->assertSessionHasNoErrors();
    }

    $session = $registrant->refresh()->ruleset_id === $ruleset->id ? ExamSession::query()->firstOrFail() : null;
    $this->post(route('portal.children.exam.update', $registrant), ['exam_session_id' => $session->id])->assertSessionHasNoErrors();
    $this->post(route('portal.children.submit', $registrant))->assertSessionHasNoErrors();

    expect($registrant->refresh()->status)->toBe(RegistrantStatus::Review)
        ->and($registrant->currentStep())->toBe('validation');

    // The committee accepts the data, which issues the settlement bill.
    $this->actingAs($admin)->post(route('admin.registrants.accept', $registrant))->assertSessionHasNoErrors();
    $settlement = $registrant->refresh()->bills()->where('type', BillType::Settlement)->firstOrFail();

    expect($registrant->status)->toBe(RegistrantStatus::Validated)
        ->and($registrant->currentStep())->toBe('settlement-bill')
        ->and($settlement->items->sum('amount'))->toBe(1000000);

    // The parent pays the settlement and it is verified.
    $this->actingAs($parent)->post(route('portal.payments.store'), [
        'items' => [['bill_item_id' => $settlement->items->first()->id, 'amount' => 1000000]],
        'paid_on' => today()->toDateString(),
        'method' => 'transfer',
        'proof' => UploadedFile::fake()->image('bukti2.jpg'),
    ])->assertSessionHasNoErrors();
    $this->actingAs($admin)->put(route('admin.payments.verify', Payment::query()->latest('id')->firstOrFail()))->assertSessionHasNoErrors();

    expect(Bill::query()->find($settlement->id)->loadTotals()->status())->toBe(BillStatus::Paid);

    // The committee enters the scores and announces to the primary contact.
    $this->put(route('admin.registrants.scores', $registrant), ['scores' => [80]])->assertSessionHasNoErrors();

    $father = Parentship::query()->where('person_id', $registrant->person_id)->firstOrFail();
    expect($father->is_primary_contact)->toBeTrue()
        ->and(Announcement::whatsappUrl($registrant->refresh()))->toStartWith('https://wa.me/6281234567890?text=')
        ->and(Announcement::text($registrant))->toContain('Ahmad Fauzi')->toContain('tuntas');

    $this->post(route('admin.registrants.announce', $registrant))->assertSessionHasNoErrors();
    expect($registrant->refresh()->status)->toBe(RegistrantStatus::Announced);

    // The parent sees the result, then the committee places the student.
    $this->actingAs($parent)->get(route('portal.children.show', $registrant))->assertInertia(fn ($page) => $page->where('result.passed', true));

    $this->actingAs($admin)->post(route('admin.registrants.place', $registrant), ['year_id' => $year->id, 'nis' => '2026001'])->assertSessionHasNoErrors();

    $student = Student::query()->firstOrFail();

    expect($registrant->refresh()->status)->toBe(RegistrantStatus::Placed)
        ->and($student->person_id)->toBe($registrant->person_id)
        ->and($student->nis)->toBe('2026001')
        ->and($student->photo_path)->not->toBeNull()
        ->and($registrant->statusChanges->pluck('to_status')->map->value->all())->toBe(['review', 'validated', 'announced', 'placed']);

    Storage::disk('public')->assertExists($student->photo_path);
});
