<?php

use App\Enums\DefaultRole;
use App\Enums\NoteVisibility;
use App\Enums\RegistrantStatus;
use App\Models\ExamSession;
use App\Models\Note;
use App\Models\Registrant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->admin = userWithRole(DefaultRole::Admin);

    $this->registrant = admissionRegistrant(RegistrantStatus::Review);
    $this->full = examSessionFor($this->registrant, quota: 1);
    admissionRegistrant(RegistrantStatus::Review, ['organization_id' => $this->registrant->organization_id, 'wave_id' => $this->registrant->wave_id, 'exam_session_id' => $this->full->id]);

    $this->notesOf = fn (Registrant $registrant) => Note::query()->where('person_id', $registrant->person_id)->get();
});

describe('the committee changing the session', function () {
    test('a full session is refused without the override', function () {
        $this->actingAs($this->admin)
            ->put(route('admin.registrants.exam-session', $this->registrant), ['exam_session_id' => $this->full->id])
            ->assertSessionHasErrors('exam_session_id');

        expect($this->registrant->refresh()->exam_session_id)->toBeNull();
    });

    test('the override needs a reason', function () {
        $this->actingAs($this->admin)
            ->put(route('admin.registrants.exam-session', $this->registrant), ['exam_session_id' => $this->full->id, 'override_full' => true, 'override_reason' => '  '])
            ->assertSessionHasErrors('override_reason');

        expect($this->registrant->refresh()->exam_session_id)->toBeNull();
    });

    test('with the override and a reason the registrant goes into the full session and the reason is kept in a note', function () {
        $this->actingAs($this->admin)
            ->put(route('admin.registrants.exam-session', $this->registrant), ['exam_session_id' => $this->full->id, 'override_full' => true, 'override_reason' => 'Titipan kepala sekolah'])
            ->assertSessionHasNoErrors();

        $note = ($this->notesOf)($this->registrant)->first();

        expect($this->registrant->refresh()->exam_session_id)->toBe($this->full->id)
            ->and(Registrant::query()->where('exam_session_id', $this->full->id)->count())->toBe(2)
            ->and($note->author_id)->toBe($this->admin->id)
            ->and($note->visibility)->toBe(NoteVisibility::Private)
            ->and($note->body)->toContain('Sesi ujian dipindah dari (belum memilih) ke')
            ->and($note->body)->toContain('Sesi sudah penuh (1 dari 1 kursi)')
            ->and($note->body)->toContain('Titipan kepala sekolah');
    });

    test('an open session needs no override and the note says where the registrant moved from', function () {
        $open = ExamSession::factory()->create(['exam_day_id' => $this->full->exam_day_id, 'label' => 'Sesi 2', 'quota' => 5]);
        $this->registrant->update(['exam_session_id' => $this->full->id]);

        $this->actingAs($this->admin)
            ->put(route('admin.registrants.exam-session', $this->registrant), ['exam_session_id' => $open->id, 'override_full' => true])
            ->assertSessionHasNoErrors();

        $body = ($this->notesOf)($this->registrant)->first()->body;

        expect($this->registrant->refresh()->exam_session_id)->toBe($open->id)
            ->and($body)->toContain('Sesi 1')
            ->and($body)->toContain('Sesi 2')
            ->and($body)->not->toContain('penuh');
    });

    test('choosing the session the registrant already has writes no note', function () {
        $this->registrant->update(['exam_session_id' => $this->full->id]);

        $this->actingAs($this->admin)
            ->put(route('admin.registrants.exam-session', $this->registrant), ['exam_session_id' => $this->full->id])
            ->assertSessionHasNoErrors();

        expect(($this->notesOf)($this->registrant))->toHaveCount(0);
    });

    test('staff who may only view cannot change the session, even with the override', function () {
        $this->actingAs(registrantViewer())
            ->put(route('admin.registrants.exam-session', $this->registrant), ['exam_session_id' => $this->full->id, 'override_full' => true, 'override_reason' => 'Titipan'])
            ->assertForbidden();

        expect($this->registrant->refresh()->exam_session_id)->toBeNull();
    });
});

describe('a parent choosing the session', function () {
    test('a full session stays refused even when the request asks for the override', function () {
        $parent = User::factory()->create();
        $draft = admissionRegistrant(RegistrantStatus::Draft, ['ruleset_id' => $this->registrant->ruleset_id]);
        DB::table('parentships')->where('person_id', $draft->person_id)->update(['related_person_id' => $parent->person_id]);

        $this->actingAs($parent)
            ->post(route('portal.children.exam.update', $draft), ['exam_session_id' => $this->full->id, 'override_full' => true, 'override_reason' => 'Tolong'])
            ->assertSessionHasErrors('exam_session_id');

        expect($draft->refresh()->exam_session_id)->toBeNull()
            ->and(($this->notesOf)($draft))->toHaveCount(0);
    });
});
