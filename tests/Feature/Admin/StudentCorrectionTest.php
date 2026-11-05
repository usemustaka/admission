<?php

use App\Enums\DefaultRole;
use App\Enums\NoteVisibility;
use App\Enums\RegistrantStatus;
use App\Models\Note;
use App\Models\Registrant;
use App\Models\RegistrantStatusChange;
use App\Models\Student;
use App\Models\User;
use App\Models\Year;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->withoutVite();
    $this->year = Year::factory()->active()->create(['name' => '2026/2027']);
    $this->nextYear = Year::factory()->create(['name' => '2027/2028']);

    $this->placed = function (string $nis = '2026001', RegistrantStatus $status = RegistrantStatus::Placed): Student {
        $registrant = Registrant::factory()->create(['status' => $status]);

        return Student::factory()->create([
            'person_id' => $registrant->person_id,
            'organization_id' => $registrant->organization_id,
            'registrant_id' => $registrant->id,
            'year_id' => $this->year->id,
            'nis' => $nis,
        ]);
    };
});

test('corrections are closed to parents and to staff without the manage ability', function () {
    $student = ($this->placed)();

    foreach ([User::factory()->create(), userWithRole(DefaultRole::Operator)] as $outsider) {
        $this->actingAs($outsider);

        $this->put(route('admin.students.update', $student), ['nis' => '999', 'year_id' => $this->nextYear->id])->assertForbidden();
        $this->post(route('admin.students.cancel-placement', $student), ['reason' => 'Salah'])->assertForbidden();
    }

    expect($student->refresh()->nis)->toBe('2026001')
        ->and(Student::count())->toBe(1)
        ->and($student->registrant->status)->toBe(RegistrantStatus::Placed);
});

describe('changing NIS and year', function () {
    beforeEach(function () {
        $this->admin = userWithRole(DefaultRole::Admin);
        $this->actingAs($this->admin);
        $this->student = ($this->placed)();
    });

    test('updates both and leaves a private note on the child written by the acting user', function () {
        $this->put(route('admin.students.update', $this->student), ['nis' => '2026999', 'year_id' => $this->nextYear->id])->assertSessionHasNoErrors();

        expect($this->student->refresh()->nis)->toBe('2026999')->and($this->student->year_id)->toBe($this->nextYear->id);

        $note = Note::where('noteable_id', $this->student->person_id)->firstOrFail();
        expect($note->author_id)->toBe($this->admin->id)
            ->and($note->visibility)->toBe(NoteVisibility::Private)
            ->and($note->person_id)->toBe($this->student->person_id)
            ->and($note->body)->toContain('NIS diubah dari 2026001 ke 2026999')
            ->and($note->body)->toContain('Tahun ajaran diubah dari 2026/2027 ke 2027/2028');
    });

    test('can change the year alone, or the NIS alone', function () {
        $this->put(route('admin.students.update', $this->student), ['nis' => '2026001', 'year_id' => $this->nextYear->id])->assertSessionHasNoErrors();
        $this->put(route('admin.students.update', $this->student), ['nis' => '2026002', 'year_id' => $this->nextYear->id])->assertSessionHasNoErrors();

        $bodies = Note::orderBy('id')->pluck('body')->all();
        expect($bodies)->toHaveCount(2)
            ->and($bodies[0])->toContain('Tahun ajaran diubah')->not->toContain('NIS diubah')
            ->and($bodies[1])->toContain('NIS diubah dari 2026001 ke 2026002')->not->toContain('Tahun ajaran diubah');
    });

    test('writes nothing when nothing changed', function () {
        $this->put(route('admin.students.update', $this->student), ['nis' => ' 2026001 ', 'year_id' => $this->year->id])->assertSessionHasNoErrors();

        expect(Note::count())->toBe(0);
    });

    test('refuses an NIS another student has, an empty NIS, and a year that does not exist', function () {
        ($this->placed)('2026555');

        $this->put(route('admin.students.update', $this->student), ['nis' => '2026555', 'year_id' => $this->year->id])->assertSessionHasErrors('nis');
        $this->put(route('admin.students.update', $this->student), ['nis' => '', 'year_id' => $this->year->id])->assertSessionHasErrors('nis');
        $this->put(route('admin.students.update', $this->student), ['nis' => str_repeat('9', 51), 'year_id' => $this->year->id])->assertSessionHasErrors('nis');
        $this->put(route('admin.students.update', $this->student), ['nis' => '2026001', 'year_id' => 9999])->assertSessionHasErrors('year_id');
        $this->put(route('admin.students.update', $this->student), ['nis' => '2026001'])->assertSessionHasErrors('year_id');

        expect($this->student->refresh()->nis)->toBe('2026001')->and(Note::count())->toBe(0);
    });
});

describe('cancelling a placement', function () {
    beforeEach(function () {
        $this->admin = userWithRole(DefaultRole::Admin);
        $this->actingAs($this->admin);
        $this->student = ($this->placed)();
        $this->registrant = $this->student->registrant;
    });

    test('deletes the student, sends the registration back to Announced, and records the reason', function () {
        $this->post(route('admin.students.cancel-placement', $this->student), ['reason' => 'Salah memilih lembaga'])->assertSessionHasNoErrors();

        expect(Student::count())->toBe(0)
            ->and($this->registrant->refresh()->status)->toBe(RegistrantStatus::Announced);

        $history = RegistrantStatusChange::where('registrant_id', $this->registrant->id)->latest('id')->firstOrFail();
        expect($history->from_status)->toBe(RegistrantStatus::Placed)
            ->and($history->to_status)->toBe(RegistrantStatus::Announced)
            ->and($history->note)->toContain('Salah memilih lembaga')
            ->and($history->user_id)->toBe($this->admin->id)
            ->and($history->is_bypass)->toBeTrue();

        $note = Note::where('noteable_id', $this->registrant->person_id)->firstOrFail();
        expect($note->author_id)->toBe($this->admin->id)
            ->and($note->visibility)->toBe(NoteVisibility::Private)
            ->and($note->body)->toContain('dibatalkan')->toContain('NIS 2026001')->toContain('Salah memilih lembaga');
    });

    test('is refused without a reason and changes nothing', function () {
        $this->post(route('admin.students.cancel-placement', $this->student))->assertSessionHasErrors('reason');
        $this->post(route('admin.students.cancel-placement', $this->student), ['reason' => '   '])->assertSessionHasErrors('reason');

        expect(Student::count())->toBe(1)
            ->and($this->registrant->refresh()->status)->toBe(RegistrantStatus::Placed)
            ->and(RegistrantStatusChange::count())->toBe(0)
            ->and(Note::count())->toBe(0);
    });

    test('is refused when the registration is not placed', function () {
        $student = ($this->placed)('2026002', RegistrantStatus::Announced);

        $this->post(route('admin.students.cancel-placement', $student), ['reason' => 'Salah'])->assertSessionHasErrors('student');

        expect(Student::count())->toBe(2)->and(RegistrantStatusChange::count())->toBe(0);
    });

    test('is refused for a student without a registration', function () {
        $student = Student::factory()->create(['year_id' => $this->year->id, 'nis' => '777']);

        $this->post(route('admin.students.cancel-placement', $student), ['reason' => 'Salah'])->assertSessionHasErrors('student');

        expect(Student::whereKey($student->id)->exists())->toBeTrue();
    });

    test('lets the child be placed again, with the same NIS', function () {
        $this->post(route('admin.students.cancel-placement', $this->student), ['reason' => 'Salah tahun']);

        $this->post(route('admin.registrants.place', $this->registrant), ['year_id' => $this->nextYear->id, 'nis' => '2026001'])->assertSessionHasNoErrors();

        expect(Student::firstOrFail()->year_id)->toBe($this->nextYear->id)
            ->and($this->registrant->refresh()->status)->toBe(RegistrantStatus::Placed);
    });

    test('removes the student photo copy and keeps the profile photo', function () {
        Storage::fake('public');
        Storage::disk('public')->put('students/photos/a.jpg', 'x');
        Storage::disk('public')->put('people/a.jpg', 'x');
        $this->registrant->person->update(['photo_path' => 'people/a.jpg']);
        $this->student->update(['photo_path' => 'students/photos/a.jpg']);

        $this->post(route('admin.students.cancel-placement', $this->student), ['reason' => 'Salah'])->assertSessionHasNoErrors();

        Storage::disk('public')->assertMissing('students/photos/a.jpg');
        Storage::disk('public')->assertExists('people/a.jpg');
    });
});

test('the registration page offers the cancel action only to staff who may manage students, and only while placed', function () {
    $student = ($this->placed)();
    $registrant = $student->registrant;

    $this->actingAs(userWithRole(DefaultRole::Admin))->get(route('admin.registrants.show', $registrant))->assertInertia(fn ($page) => $page
        ->where('can.cancel_placement', true)
        ->where('student.id', $student->id));

    $this->actingAs(userWithRole(DefaultRole::Operator))->get(route('admin.registrants.show', $registrant))->assertInertia(fn ($page) => $page->where('can.cancel_placement', false));

    $announced = ($this->placed)('2026002', RegistrantStatus::Announced)->registrant;
    $this->actingAs(userWithRole(DefaultRole::Admin))->get(route('admin.registrants.show', $announced))->assertInertia(fn ($page) => $page->where('can.cancel_placement', false));
});
