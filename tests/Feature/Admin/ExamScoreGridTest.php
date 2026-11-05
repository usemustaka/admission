<?php

use App\Enums\DefaultRole;
use App\Enums\NoteVisibility;
use App\Enums\RegistrantStatus;
use App\Models\Note;
use App\Models\Registrant;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->withoutVite();
    $this->admin = userWithRole(DefaultRole::Admin);
    $this->actingAs($this->admin);

    $this->first = admissionRegistrant(RegistrantStatus::Review, ['exam_scores' => [70, 60]]);
    $this->session = examSessionFor($this->first, quota: 5);
    $this->first->update(['exam_session_id' => $this->session->id]);
    $this->second = admissionRegistrant(RegistrantStatus::Review, ['ruleset_id' => $this->first->ruleset_id, 'exam_session_id' => $this->session->id]);

    $this->notesOf = fn (Registrant $registrant) => Note::query()->where('person_id', $registrant->person_id)->get();
});

describe('the grid page', function () {
    test('it has a row per registrant and a column per aspect with its KKM', function () {
        $this->get(route('admin.exams.sessions.scores.edit', $this->session))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/exams/scores')
            ->where('aspects', [['name' => 'Tes tulis', 'passing_score' => 70], ['name' => 'Wawancara', 'passing_score' => 60]])
            ->has('rows', 2)
            ->where('rows', fn ($rows) => collect($rows)->firstWhere('id', $this->first->id)['scores'] === [70, 60]
                && collect($rows)->firstWhere('id', $this->second->id)['scores'] === [null, null])
            ->where('can.manage', true));
    });

    test('staff who may only view can open it but not save', function () {
        $viewer = registrantViewer();

        $this->actingAs($viewer)->get(route('admin.exams.sessions.scores.edit', $this->session))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('can.manage', false));

        $this->put(route('admin.exams.sessions.scores.update', $this->session), ['rows' => [$this->second->id => [0 => 90]]])->assertForbidden();

        expect($this->second->refresh()->exam_scores)->toBeNull();
    });
});

describe('saving the grid', function () {
    test('it saves the changed rows, merging per aspect, and tells how many', function () {
        $this->put(route('admin.exams.sessions.scores.update', $this->session), ['rows' => [
            $this->first->id => [1 => 80],
            $this->second->id => [0 => 90],
        ]])->assertSessionHasNoErrors();

        expect($this->first->refresh()->exam_scores)->toBe([70, 80])
            ->and($this->second->refresh()->exam_scores)->toBe([90, null]);
    });

    test('an aspect that is not sent keeps its score, and an empty value clears one', function () {
        $this->put(route('admin.exams.sessions.scores.update', $this->session), ['rows' => [
            $this->first->id => [0 => null],
        ]])->assertSessionHasNoErrors();

        expect($this->first->refresh()->exam_scores)->toBe([null, 60]);
    });

    test('a row that is out of range is rejected on its own and does not hold back the others', function () {
        $this->put(route('admin.exams.sessions.scores.update', $this->session), ['rows' => [
            $this->first->id => [0 => 150],
            $this->second->id => [0 => 80],
        ]])
            ->assertSessionHasErrors(["rows.{$this->first->id}", 'summary'])
            ->assertSessionDoesntHaveErrors("rows.{$this->second->id}");

        expect($this->first->refresh()->exam_scores)->toBe([70, 60])
            ->and($this->second->refresh()->exam_scores)->toBe([80, null]);
    });

    test('a registrant who is not in the session is reported and left alone', function () {
        $stranger = admissionRegistrant(RegistrantStatus::Review, ['ruleset_id' => $this->first->ruleset_id]);

        $this->put(route('admin.exams.sessions.scores.update', $this->session), ['rows' => [
            $stranger->id => [0 => 90],
            $this->second->id => [0 => 80],
        ]])->assertSessionHasErrors("rows.{$stranger->id}");

        expect($stranger->refresh()->exam_scores)->toBeNull()
            ->and($this->second->refresh()->exam_scores)->toBe([80, null]);
    });

    test('the right to manage registrants is needed on the server', function () {
        $this->actingAs(registrantViewer())
            ->put(route('admin.exams.sessions.scores.update', $this->session), ['rows' => [$this->second->id => [0 => 90]]])
            ->assertForbidden();
        $this->actingAs(userWithRole(DefaultRole::Operator))
            ->put(route('admin.exams.sessions.scores.update', $this->session), ['rows' => [$this->second->id => [0 => 90]]])
            ->assertSessionHasNoErrors();
    });

    test('a request without rows is rejected', function () {
        $this->put(route('admin.exams.sessions.scores.update', $this->session), ['rows' => []])->assertSessionHasErrors('rows');
    });

    test('a result that was announced changes only after the staff member confirms', function () {
        $this->first->update(['status' => RegistrantStatus::Announced]);
        $rows = ['rows' => [$this->first->id => [0 => 90], $this->second->id => [0 => 85]]];

        $this->put(route('admin.exams.sessions.scores.update', $this->session), $rows)
            ->assertSessionHasErrors("rows.{$this->first->id}");

        expect($this->first->refresh()->exam_scores)->toBe([70, 60])
            ->and($this->second->refresh()->exam_scores)->toBe([85, null]);

        $this->put(route('admin.exams.sessions.scores.update', $this->session), [...$rows, 'confirm_after_announce' => true])
            ->assertSessionHasNoErrors();

        expect($this->first->refresh()->exam_scores)->toBe([90, 60]);
    });

    test('each registrant gets one private note naming every aspect that changed, by the staff member', function () {
        $this->put(route('admin.exams.sessions.scores.update', $this->session), ['rows' => [
            $this->first->id => [0 => 80, 1 => null],
            $this->second->id => [0 => 90],
        ]])->assertSessionHasNoErrors();

        $notes = ($this->notesOf)($this->first);

        expect($notes)->toHaveCount(1)
            ->and($notes->first()->author_id)->toBe($this->admin->id)
            ->and($notes->first()->visibility)->toBe(NoteVisibility::Private)
            ->and($notes->first()->body)->toContain('Nilai Tes tulis diubah dari 70 ke 80')
            ->and($notes->first()->body)->toContain('Nilai Wawancara dikosongkan (sebelumnya 60)')
            ->and(($this->notesOf)($this->second)->first()->body)->toContain('Nilai Tes tulis diisi 90');
    });

    test('saving the same values again changes nothing and writes no note', function () {
        $this->put(route('admin.exams.sessions.scores.update', $this->session), ['rows' => [$this->first->id => [0 => 70, 1 => 60]]])
            ->assertSessionHasNoErrors();

        expect(($this->notesOf)($this->first))->toHaveCount(0);
    });
});

describe('scores of a single registrant', function () {
    test('the scores sent are merged into the stored ones', function () {
        $this->put(route('admin.registrants.scores', $this->first), ['scores' => [1 => 75]])->assertSessionHasNoErrors();

        expect($this->first->refresh()->exam_scores)->toBe([70, 75]);

        $this->put(route('admin.registrants.scores', $this->first), ['scores' => [0 => null]])->assertSessionHasNoErrors();

        expect($this->first->refresh()->exam_scores)->toBe([null, 75]);
    });

    test('an aspect that does not exist is refused', function () {
        $this->put(route('admin.registrants.scores', $this->first), ['scores' => [5 => 75]])->assertSessionHasErrors('scores');

        expect($this->first->refresh()->exam_scores)->toBe([70, 60]);
    });

    test('an announced or placed result needs the confirmation', function (RegistrantStatus $status) {
        $this->first->update(['status' => $status]);

        $this->put(route('admin.registrants.scores', $this->first), ['scores' => [0 => 95, 1 => 60]])->assertSessionHasErrors('confirm_after_announce');

        expect($this->first->refresh()->exam_scores)->toBe([70, 60]);

        $this->put(route('admin.registrants.scores', $this->first), ['scores' => [0 => 95], 'confirm_after_announce' => true])->assertSessionHasNoErrors();

        expect($this->first->refresh()->exam_scores)->toBe([95, 60]);
    })->with([RegistrantStatus::Announced, RegistrantStatus::Placed]);

    test('an edit leaves a private note on the child', function () {
        $this->put(route('admin.registrants.scores', $this->first), ['scores' => [0 => 80]])->assertSessionHasNoErrors();

        $note = ($this->notesOf)($this->first)->first();

        expect($note->body)->toBe('Nilai Tes tulis diubah dari 70 ke 80.')
            ->and($note->author_id)->toBe($this->admin->id)
            ->and($note->visibility)->toBe(NoteVisibility::Private);
    });
});
