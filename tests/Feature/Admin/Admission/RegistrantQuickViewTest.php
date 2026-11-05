<?php

use App\Enums\DefaultRole;
use App\Enums\RegistrantStatus;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Registrant;
use App\Models\User;

test('only staff who can see applicants get the quick view', function () {
    $registrant = admissionRegistrant();

    $this->actingAs(User::factory()->create())->getJson(route('admin.registrants.quick-view', $registrant))->assertForbidden();
    $this->actingAs(userWithRole(DefaultRole::Operator))->getJson(route('admin.registrants.quick-view', $registrant))->assertOk();
});

test('the quick view summarizes identity, family, bills, exam and history', function () {
    $this->actingAs(userWithRole(DefaultRole::Operator));
    $registrant = admissionRegistrant(RegistrantStatus::Validated, ['exam_scores' => [80, 50]]);
    $registrant->update(['exam_session_id' => examSessionFor($registrant)->id]);
    $bill = Bill::factory()->create(['registrant_id' => $registrant->id]);
    $item = BillItem::factory()->create(['bill_id' => $bill->id, 'amount' => 200000]);
    verifiedPayment($item, 50000);
    foreach (['draft' => 'review', 'review' => 'validated'] as $from => $to) {
        $registrant->statusChanges()->create(['from_status' => $from, 'to_status' => $to]);
    }

    $this->getJson(route('admin.registrants.quick-view', $registrant))
        ->assertOk()
        ->assertJsonPath('id', $registrant->id)
        ->assertJsonPath('status', 'validated')
        ->assertJsonPath('child.name', $registrant->person->name)
        ->assertJsonPath('family.0.is_primary_contact', true)
        ->assertJsonPath('contact.label', 'Kontak utama')
        ->assertJsonPath('bills.total', 200000)
        ->assertJsonPath('bills.paid', 50000)
        ->assertJsonPath('bills.outstanding', 150000)
        ->assertJsonPath('bills.status', 'partial')
        ->assertJsonPath('exam.result.passed', false)
        ->assertJsonPath('exam.session.label', fn ($label) => str_contains($label, 'Sesi'))
        ->assertJsonPath('timeline.0.to', 'validated')
        ->assertJsonPath('can.announce', true)
        ->assertJsonPath('can.accept', false);
});

test('the quick view lists what a draft still lacks', function () {
    $this->actingAs(userWithRole(DefaultRole::Operator));
    $registrant = Registrant::factory()->create(['status' => RegistrantStatus::Draft]);

    $this->getJson(route('admin.registrants.quick-view', $registrant))
        ->assertOk()
        ->assertJsonPath('readiness', fn ($missing) => in_array('Agama anak', $missing, true));
});

test('the quick view flags a registrant whose NIK is unconfirmed and holds back the workflow actions', function () {
    $this->actingAs(userWithRole(DefaultRole::Operator));
    $registrant = admissionRegistrant(RegistrantStatus::Review, ['needs_identity_review' => true]);

    $this->getJson(route('admin.registrants.quick-view', $registrant))
        ->assertOk()
        ->assertJsonPath('needs_identity_review', true)
        ->assertJsonPath('contact.label', 'Yang mendaftarkan')
        ->assertJsonPath('can.accept', false)
        ->assertJsonPath('can.manage', true);
});

test('staff who can only view get no workflow actions in the quick view', function () {
    $this->actingAs(registrantViewer());

    $this->getJson(route('admin.registrants.quick-view', admissionRegistrant()))
        ->assertOk()
        ->assertJsonPath('can.manage', false)
        ->assertJsonPath('can.accept', false);
});
