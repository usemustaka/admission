<?php

use App\Actions\Admission\AnnounceRegistrant;
use App\Enums\BillType;
use App\Enums\DefaultRole;
use App\Enums\RegistrantStatus;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Registrant;
use App\Support\AnnouncementReadiness;
use Illuminate\Validation\ValidationException;

test('a registration with every score above its KKM and no open bill is ready', function () {
    $registrant = admissionRegistrant(RegistrantStatus::Validated, ['exam_scores' => [80, 70]]);

    expect(AnnouncementReadiness::for($registrant))->toBe(['missing_scores' => false, 'not_passed' => false, 'unsettled' => false, 'messages' => []])
        ->and(AnnouncementReadiness::incompleteReason($registrant))->toBeNull();
});

test('missing scores are flagged, and a partial set counts as missing but not as failed', function () {
    expect(AnnouncementReadiness::for(admissionRegistrant(RegistrantStatus::Validated)))->toMatchArray(['missing_scores' => true, 'not_passed' => false, 'messages' => ['Nilai ujian belum lengkap']])
        ->and(AnnouncementReadiness::for(admissionRegistrant(RegistrantStatus::Validated, ['exam_scores' => [20, null]])))->toMatchArray(['missing_scores' => true, 'not_passed' => false]);
});

test('a score under the KKM of any aspect is flagged as not passed', function () {
    $readiness = AnnouncementReadiness::for(admissionRegistrant(RegistrantStatus::Validated, ['exam_scores' => [80, 59]]));

    expect($readiness)->toMatchArray(['missing_scores' => false, 'not_passed' => true, 'messages' => ['Belum tuntas']]);
});

test('a registration without exam aspects never lacks scores', function () {
    $registrant = Registrant::factory()->create(['status' => RegistrantStatus::Validated, 'ruleset_snapshot' => null]);

    expect(AnnouncementReadiness::for($registrant))->toBe(['missing_scores' => false, 'not_passed' => false, 'unsettled' => false, 'messages' => []]);
});

test('an open registration or settlement bill is flagged, a paid one and a bill that asks for nothing are not', function () {
    $open = admissionRegistrant(RegistrantStatus::Validated, ['exam_scores' => [80, 70]]);
    $partlyPaid = admissionRegistrant(RegistrantStatus::Validated, ['exam_scores' => [80, 70]]);
    $paid = admissionRegistrant(RegistrantStatus::Validated, ['exam_scores' => [80, 70]]);
    $free = admissionRegistrant(RegistrantStatus::Validated, ['exam_scores' => [80, 70]]);

    foreach ([[$open, BillType::Registration, 100000, 0], [$partlyPaid, BillType::Settlement, 100000, 40000], [$paid, BillType::Settlement, 100000, 100000], [$free, BillType::Registration, 0, 0]] as [$registrant, $type, $amount, $paidAmount]) {
        $item = BillItem::factory()->create(['bill_id' => Bill::factory()->create(['registrant_id' => $registrant->id, 'type' => $type])->id, 'amount' => $amount]);

        if ($paidAmount > 0) {
            verifiedPayment($item, $paidAmount);
        }
    }

    expect(AnnouncementReadiness::for($open)['unsettled'])->toBeTrue()
        ->and(AnnouncementReadiness::for($partlyPaid)['unsettled'])->toBeTrue()
        ->and(AnnouncementReadiness::for($paid)['unsettled'])->toBeFalse()
        ->and(AnnouncementReadiness::for($free)['unsettled'])->toBeFalse();
});

test('payments that wait for verification do not settle a bill', function () {
    $registrant = admissionRegistrant(RegistrantStatus::Validated, ['exam_scores' => [80, 70]]);
    $item = BillItem::factory()->create(['bill_id' => Bill::factory()->create(['registrant_id' => $registrant->id])->id, 'amount' => 100000]);
    verifiedPayment($item, 100000)->update(['status' => 'pending']);

    expect(AnnouncementReadiness::for($registrant)['unsettled'])->toBeTrue();
});

test('every problem is listed in the message, in a fixed order, and the reason reads as one line', function () {
    $registrant = admissionRegistrant(RegistrantStatus::Validated, ['exam_scores' => [10, 10]]);
    BillItem::factory()->create(['bill_id' => Bill::factory()->create(['registrant_id' => $registrant->id])->id, 'amount' => 100000]);

    $readiness = AnnouncementReadiness::for($registrant);

    expect($readiness['messages'])->toBe(['Belum tuntas', 'Tagihan belum lunas'])
        ->and(AnnouncementReadiness::reason($readiness))->toBe('Belum lengkap: belum tuntas, tagihan belum lunas')
        ->and(AnnouncementReadiness::reason(['messages' => []]))->toBeNull();
});

test('announcing one registrant stays allowed when incomplete, and is refused only when the caller requires completeness', function () {
    $operator = userWithRole(DefaultRole::Operator);
    $incomplete = admissionRegistrant(RegistrantStatus::Validated);
    $alsoIncomplete = admissionRegistrant(RegistrantStatus::Validated);

    expect(fn () => app(AnnounceRegistrant::class)->handle($incomplete, $operator, requireComplete: true))->toThrow(ValidationException::class, 'Belum lengkap: nilai ujian belum lengkap')
        ->and($incomplete->refresh()->status)->toBe(RegistrantStatus::Validated);

    app(AnnounceRegistrant::class)->handle($alsoIncomplete, $operator);

    expect($alsoIncomplete->refresh()->status)->toBe(RegistrantStatus::Announced);
});
