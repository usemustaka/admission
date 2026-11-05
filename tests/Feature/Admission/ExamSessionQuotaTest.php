<?php

use App\Actions\Admission\ChooseExamSession;
use App\Actions\Admission\SyncExamSchedule;
use App\Enums\RegistrantStatus;
use App\Models\ExamDay;
use App\Models\Registrant;
use App\Models\Ruleset;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->parent = User::factory()->create();
    $this->registrant = admissionRegistrant(RegistrantStatus::Draft);
    DB::table('parentships')->where('person_id', $this->registrant->person_id)->update(['related_person_id' => $this->parent->person_id]);
    $this->session = examSessionFor($this->registrant, quota: 1);
});

test('a parent picks a session and it counts as a seat', function () {
    $this->actingAs($this->parent)
        ->post(route('portal.children.exam.update', $this->registrant), ['exam_session_id' => $this->session->id])
        ->assertRedirect(route('portal.children.show', $this->registrant));

    expect($this->registrant->refresh()->exam_session_id)->toBe($this->session->id);

    $this->get(route('portal.children.exam.edit', $this->registrant))->assertInertia(fn ($page) => $page
        ->where('selected', (string) $this->session->id)
        ->where('days.0.sessions.0.taken', 1)
        ->where('days.0.sessions.0.quota', 1));
});

test('a full session cannot be chosen by someone else', function () {
    $other = admissionRegistrant(RegistrantStatus::Draft, ['ruleset_id' => $this->registrant->ruleset_id, 'exam_session_id' => $this->session->id]);

    $this->actingAs($this->parent)
        ->post(route('portal.children.exam.update', $this->registrant), ['exam_session_id' => $this->session->id])
        ->assertSessionHasErrors('exam_session_id');

    expect($this->registrant->refresh()->exam_session_id)->toBeNull()
        ->and($other->refresh()->exam_session_id)->toBe($this->session->id);
});

test('whoever holds the last seat can keep it', function () {
    $this->registrant->update(['exam_session_id' => $this->session->id]);

    app(ChooseExamSession::class)->handle($this->registrant, $this->session);

    expect($this->registrant->refresh()->exam_session_id)->toBe($this->session->id);
});

test('a session of another ruleset cannot be chosen', function () {
    $foreign = examSessionFor(Registrant::factory()->create(['ruleset_id' => admissionRegistrant()->ruleset_id]));

    expect(fn () => app(ChooseExamSession::class)->handle($this->registrant, $foreign))->toThrow(ValidationException::class);
});

test('a parent cannot choose a session once the data was sent', function () {
    $this->registrant->update(['status' => RegistrantStatus::Review]);

    $this->actingAs($this->parent)
        ->post(route('portal.children.exam.update', $this->registrant), ['exam_session_id' => $this->session->id])
        ->assertForbidden();
});

test('a session that applicants picked cannot be removed from the schedule', function () {
    $this->registrant->update(['exam_session_id' => $this->session->id]);
    $ruleset = Ruleset::query()->findOrFail($this->registrant->ruleset_id);

    expect(fn () => app(SyncExamSchedule::class)->handle($ruleset, []))->toThrow(ValidationException::class);
    expect(ExamDay::query()->where('ruleset_id', $ruleset->id)->count())->toBe(1);

    $day = $this->session->day;

    app(SyncExamSchedule::class)->handle($ruleset, [[
        'id' => $day->id,
        'date' => $day->date->toDateString(),
        'sessions' => [['id' => $this->session->id, 'label' => 'Sesi pagi', 'start_time' => '07:00', 'end_time' => '09:00', 'quota' => 5]],
    ]]);

    expect($this->session->refresh()->label)->toBe('Sesi pagi');
});
