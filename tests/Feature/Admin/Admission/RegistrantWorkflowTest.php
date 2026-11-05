<?php

use App\Enums\BillType;
use App\Enums\DefaultRole;
use App\Enums\RegistrantStatus;
use App\Models\Parentship;
use App\Models\Person;
use App\Models\Registrant;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Models\Year;
use App\Support\Announcement;
use App\Support\ExamResult;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->admin = userWithRole(DefaultRole::Admin);
    $this->actingAs($this->admin);
    $this->registrant = admissionRegistrant();
});

test('only staff with the right can work on registrants', function () {
    $this->actingAs(User::factory()->create())->post(route('admin.registrants.accept', $this->registrant))->assertForbidden();
    $this->actingAs(userWithRole(DefaultRole::Operator))->post(route('admin.registrants.accept', $this->registrant))->assertRedirect();
});

describe('reviewing', function () {
    test('accepting moves it to valid and issues the settlement bill from the ruleset copy', function () {
        $this->post(route('admin.registrants.accept', $this->registrant))->assertSessionHasNoErrors();

        $bill = $this->registrant->refresh()->bills()->where('type', BillType::Settlement)->first();

        expect($this->registrant->status)->toBe(RegistrantStatus::Validated)
            ->and($bill->items->sum('amount'))->toBe(1500000)
            ->and($this->registrant->statusChanges->last()->user_id)->toBe($this->admin->id);
    });

    test('only data under review can be accepted', function () {
        $this->registrant->update(['status' => RegistrantStatus::Draft]);

        $this->post(route('admin.registrants.accept', $this->registrant))->assertSessionHasErrors('status');

        expect($this->registrant->refresh()->bills)->toBeEmpty();
    });

    test('data that is still being matched cannot be accepted', function () {
        $this->registrant->update(['needs_identity_review' => true]);

        $this->post(route('admin.registrants.accept', $this->registrant))->assertSessionHasErrors('status');

        expect($this->registrant->refresh()->status)->toBe(RegistrantStatus::Review);
    });

    test('sending it back needs a reason, which goes into the history', function () {
        $this->post(route('admin.registrants.revise', $this->registrant), ['note' => ''])->assertSessionHasErrors('note');

        $this->post(route('admin.registrants.revise', $this->registrant), ['note' => 'Foto KK buram'])->assertSessionHasNoErrors();

        expect($this->registrant->refresh()->status)->toBe(RegistrantStatus::Revision)
            ->and($this->registrant->statusChanges->last()->note)->toBe('Foto KK buram');
    });
});

describe('bypassing', function () {
    test('any status can be reached with a note, and the history says it was a bypass', function () {
        $this->registrant->update(['status' => RegistrantStatus::Draft]);

        $this->post(route('admin.registrants.bypass', $this->registrant), ['status' => 'validated', 'note' => 'Berkas diserahkan langsung'])->assertSessionHasNoErrors();

        $change = $this->registrant->refresh()->statusChanges->last();

        expect($this->registrant->status)->toBe(RegistrantStatus::Validated)
            ->and($change->is_bypass)->toBeTrue()
            ->and($change->note)->toBe('Berkas diserahkan langsung')
            ->and($change->from_status)->toBe(RegistrantStatus::Draft)
            ->and($this->registrant->bills()->where('type', BillType::Settlement)->exists())->toBeTrue();
    });

    test('a note is required', function () {
        $this->post(route('admin.registrants.bypass', $this->registrant), ['status' => 'validated', 'note' => ''])->assertSessionHasErrors('note');

        expect($this->registrant->refresh()->status)->toBe(RegistrantStatus::Review);
    });

    test('it can step back, and placing is not done by bypass', function () {
        $this->registrant->update(['status' => RegistrantStatus::Announced]);

        $this->post(route('admin.registrants.bypass', $this->registrant), ['status' => 'revision', 'note' => 'Ada data yang salah'])->assertSessionHasNoErrors();
        expect($this->registrant->refresh()->status)->toBe(RegistrantStatus::Revision);

        $this->post(route('admin.registrants.bypass', $this->registrant), ['status' => 'placed', 'note' => 'Langsung saja'])->assertSessionHasErrors('status');
        expect($this->registrant->refresh()->status)->toBe(RegistrantStatus::Revision);
    });

    test('the flow itself does not allow jumping', function () {
        $this->registrant->update(['status' => RegistrantStatus::Draft]);

        $this->post(route('admin.registrants.announce', $this->registrant))->assertSessionHasErrors('status');
    });
});

describe('exam scores', function () {
    test('a score at or above the KKM of each aspect passes, and the registration passes when all do', function () {
        $this->put(route('admin.registrants.scores', $this->registrant), ['scores' => [70, 60]])->assertSessionHasNoErrors();

        $result = ExamResult::for($this->registrant->refresh());

        expect($result['complete'])->toBeTrue()
            ->and($result['passed'])->toBeTrue()
            ->and(array_column($result['aspects'], 'passed'))->toBe([true, true]);

        $this->put(route('admin.registrants.scores', $this->registrant), ['scores' => [90, 59]]);

        $result = ExamResult::for($this->registrant->refresh());

        expect($result['passed'])->toBeFalse()
            ->and(array_column($result['aspects'], 'passed'))->toBe([true, false]);
    });

    test('it is not decided until every aspect has a score', function () {
        $this->put(route('admin.registrants.scores', $this->registrant), ['scores' => [90, null]])->assertSessionHasNoErrors();

        $result = ExamResult::for($this->registrant->refresh());

        expect($result['complete'])->toBeFalse()
            ->and($result['passed'])->toBeNull()
            ->and($result['aspects'][0]['passed'])->toBeTrue();
    });

    test('scores stay between 0 and 100 and match the aspects', function () {
        $this->put(route('admin.registrants.scores', $this->registrant), ['scores' => [101, 50]])->assertSessionHasErrors('scores.0');
        $this->put(route('admin.registrants.scores', $this->registrant), ['scores' => [80]])->assertSessionHasErrors('scores');

        expect($this->registrant->refresh()->exam_scores)->toBeNull();
    });
});

describe('announcing', function () {
    test('the message goes to the primary contact of the child, with the number in international form', function () {
        $contact = Person::factory()->create(['phone' => '0812-3456-7890']);
        Parentship::query()->where('person_id', $this->registrant->person_id)->update(['is_primary_contact' => false]);
        Parentship::factory()->primaryContact()->create(['person_id' => $this->registrant->person_id, 'related_person_id' => $contact->id]);
        Parentship::factory()->create(['person_id' => $this->registrant->person_id, 'is_primary_contact' => false]);

        $url = Announcement::whatsappUrl($this->registrant);

        expect(Announcement::recipient($this->registrant))->toBe(['name' => $contact->name, 'phone' => '0812-3456-7890'])
            ->and($url)->toStartWith('https://wa.me/6281234567890?text=')
            ->and(urldecode(explode('text=', $url)[1]))->toBe(Announcement::text($this->registrant));
    });

    test('there is nobody to send to when the primary contact has no phone', function () {
        Person::query()->whereKey(Parentship::query()->where('person_id', $this->registrant->person_id)->value('related_person_id'))->update(['phone' => null]);

        expect(Announcement::recipient($this->registrant))->toBeNull()
            ->and(Announcement::whatsappUrl($this->registrant))->toBeNull();
    });

    test('phone numbers are turned into WhatsApp numbers', function () {
        expect(Announcement::whatsappNumber('081234567890'))->toBe('6281234567890')
            ->and(Announcement::whatsappNumber('+62 812 3456 7890'))->toBe('6281234567890')
            ->and(Announcement::whatsappNumber('6281234567890'))->toBe('6281234567890')
            ->and(Announcement::whatsappNumber('81234567890'))->toBe('6281234567890');
    });

    test('the text comes from the template in the settings, with the result of the exam', function () {
        Setting::put(Announcement::SETTING_KEY, 'Halo, {nama} ({lembaga}, {gelombang}): {hasil}.');
        $this->registrant->update(['exam_scores' => [80, 50]]);

        expect(Announcement::text($this->registrant))
            ->toBe("Halo, {$this->registrant->person->name} ({$this->registrant->organization->name}, {$this->registrant->wave->name}): belum tuntas.");

        $this->registrant->update(['exam_scores' => [80, 60]]);

        expect(Announcement::text($this->registrant))->toEndWith('tuntas.');
    });

    test('the template can be changed in the settings', function () {
        $this->put(route('admin.admission-settings.update'), ['template' => 'Hasil {nama}: {hasil}'])->assertSessionHasNoErrors();
        $this->put(route('admin.admission-settings.update'), ['template' => ''])->assertSessionHasErrors('template');

        expect(Announcement::template())->toBe('Hasil {nama}: {hasil}');
    });

    test('marking it announced follows the flow', function () {
        $this->registrant->update(['status' => RegistrantStatus::Validated]);

        $this->post(route('admin.registrants.announce', $this->registrant))->assertSessionHasNoErrors();

        expect($this->registrant->refresh()->status)->toBe(RegistrantStatus::Announced);
    });
});

describe('placing', function () {
    beforeEach(function () {
        Storage::disk('public')->put('people/photos/profil.jpg', 'foto');
        $this->registrant->person->update(['photo_path' => 'people/photos/profil.jpg']);
        $this->registrant->update(['status' => RegistrantStatus::Announced]);
        $this->year = Year::factory()->active()->create();
    });

    test('the child becomes a student of the organization with their own copy of the profile photo', function () {
        $this->post(route('admin.registrants.place', $this->registrant), ['year_id' => $this->year->id, 'nis' => '2026001'])->assertSessionHasNoErrors();

        $student = Student::query()->firstOrFail();

        expect($this->registrant->refresh()->status)->toBe(RegistrantStatus::Placed)
            ->and($student->person_id)->toBe($this->registrant->person_id)
            ->and($student->organization_id)->toBe($this->registrant->organization_id)
            ->and($student->year_id)->toBe($this->year->id)
            ->and($student->registrant_id)->toBe($this->registrant->id)
            ->and($student->nis)->toBe('2026001')
            ->and($student->photo_path)->not->toBe($this->registrant->person->photo_path);

        Storage::disk('public')->assertExists($student->photo_path);
        Storage::disk('public')->assertExists($this->registrant->person->photo_path);
    });

    test('only announced registrations can be placed', function () {
        $this->registrant->update(['status' => RegistrantStatus::Validated]);

        $this->post(route('admin.registrants.place', $this->registrant), ['year_id' => $this->year->id, 'nis' => '2026001'])->assertSessionHasErrors('status');

        expect(Student::query()->count())->toBe(0);
    });

    test('a child without a photo can still be placed', function () {
        $this->registrant->person->update(['photo_path' => null]);

        $this->post(route('admin.registrants.place', $this->registrant), ['year_id' => $this->year->id, 'nis' => '2026001'])->assertSessionHasNoErrors();

        expect(Student::query()->firstOrFail()->photo_path)->toBeNull();
    });

    test('the student number is required and cannot be used twice', function () {
        $this->post(route('admin.registrants.place', $this->registrant), ['year_id' => $this->year->id, 'nis' => ''])->assertSessionHasErrors('nis');

        Student::factory()->create(['nis' => '2026001']);

        $this->post(route('admin.registrants.place', $this->registrant), ['year_id' => $this->year->id, 'nis' => '2026001'])->assertSessionHasErrors('nis');

        expect(Student::query()->count())->toBe(1)
            ->and($this->registrant->refresh()->status)->toBe(RegistrantStatus::Announced);
    });

    test('a child who is already a student of the organization is not placed again', function () {
        Student::factory()->create(['person_id' => $this->registrant->person_id, 'organization_id' => $this->registrant->organization_id, 'nis' => '1']);

        $this->post(route('admin.registrants.place', $this->registrant), ['year_id' => $this->year->id, 'nis' => '2'])->assertSessionHasErrors('status');

        expect($this->registrant->refresh()->status)->toBe(RegistrantStatus::Announced);
    });
});

describe('helping the parent', function () {
    test('the committee can resolve a flagged NIK and the parent gets linked', function () {
        $registrant = Registrant::factory()->create(['needs_identity_review' => true, 'registered_as_kind' => null, 'registered_as_relation' => 'guardian']);

        $this->post(route('admin.registrants.resolve-identity', $registrant))->assertSessionHasNoErrors();

        expect($registrant->refresh()->needs_identity_review)->toBeFalse()
            ->and(Parentship::query()->where('person_id', $registrant->person_id)->where('related_person_id', $registrant->registered_by_person_id)->exists())->toBeTrue();
    });

    test('the committee can change the exam session', function () {
        $session = examSessionFor($this->registrant, quota: 3);

        $this->put(route('admin.registrants.exam-session', $this->registrant), ['exam_session_id' => $session->id])->assertSessionHasNoErrors();

        expect($this->registrant->refresh()->exam_session_id)->toBe($session->id);
    });

    test('the committee can edit the data of a registration whatever its status', function () {
        $this->post(route('admin.registrants.data.update', $this->registrant), [
            'child' => ['religion' => 'Hindu', 'address' => 'Jl. Baru 9'],
            'parents' => [],
        ])->assertSessionHasNoErrors();

        expect($this->registrant->person->refresh()->religion)->toBe('Hindu')
            ->and($this->registrant->person->address)->toBe('Jl. Baru 9');

        $this->actingAs(User::factory()->create())->post(route('admin.registrants.data.update', $this->registrant), ['child' => [], 'parents' => []])->assertForbidden();
    });
});
