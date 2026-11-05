<?php

use App\Enums\RegistrantStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->parent = User::factory()->create();
    $this->registrant = admissionRegistrant(RegistrantStatus::Draft);
    DB::table('parentships')->where('person_id', $this->registrant->person_id)->update(['related_person_id' => $this->parent->person_id]);
    $this->actingAs($this->parent);
});

test('the data can be sent when everything is there', function () {
    $this->post(route('portal.children.submit', $this->registrant))->assertSessionHasNoErrors();

    $this->registrant->refresh();

    expect($this->registrant->status)->toBe(RegistrantStatus::Review)
        ->and($this->registrant->submitted_at)->not->toBeNull()
        ->and($this->registrant->statusChanges)->toHaveCount(1)
        ->and($this->registrant->statusChanges->first()->to_status)->toBe(RegistrantStatus::Review)
        ->and($this->registrant->statusChanges->first()->user_id)->toBe($this->parent->id);
});

test('what is missing is listed and nothing is sent', function () {
    $this->registrant->update(['formal_photo_path' => null]);
    $this->registrant->person->update(['religion' => null]);
    $session = examSessionFor($this->registrant);

    $this->post(route('portal.children.submit', $this->registrant))->assertSessionHasErrors('status');

    expect(session('errors')->first('status'))->toContain('Pas foto')->toContain('Agama')->toContain('Jadwal ujian');

    expect($this->registrant->refresh()->status)->toBe(RegistrantStatus::Draft)
        ->and($session->exists)->toBeTrue();
});

test('it waits for the registration bill to be paid', function () {
    $snapshot = $this->registrant->ruleset_snapshot;
    $snapshot['bills'][] = ['type' => 'registration', 'items' => [['name' => 'Formulir', 'amount' => 100000]]];
    $this->registrant->update(['ruleset_snapshot' => $snapshot]);

    $this->post(route('portal.children.submit', $this->registrant))->assertSessionHasErrors('status');

    expect($this->registrant->refresh()->status)->toBe(RegistrantStatus::Draft);
});

test('the data is sent only once, and only by the parent of the child', function () {
    $this->post(route('portal.children.submit', $this->registrant))->assertSessionHasNoErrors();
    $this->post(route('portal.children.submit', $this->registrant))->assertForbidden();

    $this->actingAs(User::factory()->create())->post(route('portal.children.submit', $this->registrant))->assertForbidden();
});

test('a registration the committee is still matching cannot be sent', function () {
    $this->registrant->update(['needs_identity_review' => true]);

    $this->post(route('portal.children.submit', $this->registrant))->assertForbidden();
});

test('a parent reads why the data was sent back, but not the internal note of a bypass', function () {
    $this->registrant->update(['status' => RegistrantStatus::Revision]);
    $this->registrant->statusChanges()->create(['from_status' => 'review', 'to_status' => 'revision', 'note' => 'Foto kurang jelas']);
    $this->registrant->statusChanges()->create(['from_status' => 'draft', 'to_status' => 'review', 'note' => 'Orang tuanya kenal kepala sekolah', 'is_bypass' => true]);

    $this->get(route('portal.children.show', $this->registrant))->assertInertia(fn ($page) => $page
        ->where('registrant.revision_reason', 'Foto kurang jelas')
        ->where('timeline.0.note', 'Foto kurang jelas')
        ->where('timeline.1.note', null)
        ->where('timeline.1.is_bypass', false)
        ->where('timeline.1.by', null));
});
