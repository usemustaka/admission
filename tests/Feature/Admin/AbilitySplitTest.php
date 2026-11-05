<?php

use App\Enums\Ability;
use App\Enums\DefaultRole;
use App\Enums\PaymentStatus;
use App\Enums\RegistrantStatus;
use App\Models\Note;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Person;
use App\Models\Registrant;
use App\Models\Setting;
use App\Models\User;
use App\Models\Year;
use App\Support\Announcement;
use Database\Seeders\RolesAndPermissionsSeeder;

/**
 * Staff who may open the admin panel and hold exactly the given abilities.
 */
function staffWithAbilities(Ability ...$abilities): User
{
    test()->seed(RolesAndPermissionsSeeder::class);

    return tap(User::factory()->create())->givePermissionTo(array_map(fn (Ability $ability): string => $ability->value, [Ability::AdminAccess, ...$abilities]));
}

describe('registrant export', function () {
    test('needs the export ability on top of seeing registrants', function () {
        $this->actingAs(staffWithAbilities(Ability::RegistrantView))->get(route('admin.registrants.export'))->assertForbidden();
        $this->actingAs(staffWithAbilities(Ability::RegistrantExport))->get(route('admin.registrants.export'))->assertForbidden();

        $this->actingAs(staffWithAbilities(Ability::RegistrantView, Ability::RegistrantExport))->get(route('admin.registrants.export'))->assertOk();
    });

    test('the list offers the export, selection and template button only to those who may use them', function () {
        $this->actingAs(staffWithAbilities(Ability::RegistrantView));
        $this->get(route('admin.registrants.index'))->assertInertia(fn ($page) => $page
            ->where('can', ['bulk' => false, 'export' => false, 'editAnnouncement' => false]));

        $this->actingAs(staffWithAbilities(Ability::RegistrantView, Ability::RegistrantManage, Ability::RegistrantBulk, Ability::RegistrantExport, Ability::AnnouncementManage));
        $this->get(route('admin.registrants.index'))->assertInertia(fn ($page) => $page
            ->where('can', ['bulk' => true, 'export' => true, 'editAnnouncement' => true]));
    });
});

describe('registrant bulk actions', function () {
    test('need the bulk ability on top of managing registrants', function () {
        $registrant = admissionRegistrant();
        $payload = ['action' => 'accept', 'ids' => [$registrant->id]];

        $this->actingAs(staffWithAbilities(Ability::RegistrantView, Ability::RegistrantManage))->post(route('admin.registrants.bulk'), $payload)->assertForbidden();
        expect($registrant->refresh()->status)->toBe(RegistrantStatus::Review);

        $this->actingAs(staffWithAbilities(Ability::RegistrantView, Ability::RegistrantManage, Ability::RegistrantBulk))->post(route('admin.registrants.bulk'), $payload)->assertRedirect();
        expect($registrant->refresh()->status)->toBe(RegistrantStatus::Validated);
    });
});

describe('placing and bypassing', function () {
    test('placing needs the place ability on top of managing registrants', function () {
        $registrant = admissionRegistrant(RegistrantStatus::Announced);
        $payload = ['year_id' => Year::factory()->active()->create()->id, 'nis' => '2026001'];

        $this->actingAs(staffWithAbilities(Ability::RegistrantView, Ability::RegistrantManage))->post(route('admin.registrants.place', $registrant), $payload)->assertForbidden();
        expect($registrant->refresh()->status)->toBe(RegistrantStatus::Announced);

        $this->actingAs(staffWithAbilities(Ability::RegistrantView, Ability::RegistrantManage, Ability::RegistrantPlace))->post(route('admin.registrants.place', $registrant), $payload)->assertSessionHasNoErrors();
        expect($registrant->refresh()->status)->toBe(RegistrantStatus::Placed);
    });

    test('bypassing needs the bypass ability on top of managing registrants', function () {
        $registrant = admissionRegistrant();
        $payload = ['status' => 'validated', 'note' => 'Berkas diserahkan langsung'];

        $this->actingAs(staffWithAbilities(Ability::RegistrantView, Ability::RegistrantManage))->post(route('admin.registrants.bypass', $registrant), $payload)->assertForbidden();
        expect($registrant->refresh()->status)->toBe(RegistrantStatus::Review);

        $this->actingAs(staffWithAbilities(Ability::RegistrantView, Ability::RegistrantManage, Ability::RegistrantBypass))->post(route('admin.registrants.bypass', $registrant), $payload)->assertSessionHasNoErrors();
        expect($registrant->refresh()->status)->toBe(RegistrantStatus::Validated);
    });

    test('the detail page only offers the steps the user may take', function () {
        $registrant = admissionRegistrant(RegistrantStatus::Announced);

        $this->actingAs(staffWithAbilities(Ability::RegistrantView, Ability::RegistrantManage));
        $this->get(route('admin.registrants.show', $registrant))->assertInertia(fn ($page) => $page
            ->where('can.manage', true)->where('can.place', false)->where('can.bypass', false));

        $this->actingAs(staffWithAbilities(Ability::RegistrantView, Ability::RegistrantManage, Ability::RegistrantPlace, Ability::RegistrantBypass));
        $this->get(route('admin.registrants.show', $registrant))->assertInertia(fn ($page) => $page
            ->where('can.place', true)->where('can.bypass', true));
        $this->getJson(route('admin.registrants.quick-view', $registrant))->assertJsonPath('can.place', true);
    });
});

describe('announcement template', function () {
    test('needs its own ability, registrant management is not enough', function () {
        $this->actingAs(staffWithAbilities(Ability::RegistrantView, Ability::RegistrantManage));

        $this->get(route('admin.admission-settings.edit'))->assertForbidden();
        $this->put(route('admin.admission-settings.update'), ['template' => 'Hasil {nama}'])->assertForbidden();
        expect(Setting::query()->where('key', Announcement::SETTING_KEY)->exists())->toBeFalse();

        $this->actingAs(staffWithAbilities(Ability::AnnouncementManage));

        $this->get(route('admin.admission-settings.edit'))->assertOk();
        $this->put(route('admin.admission-settings.update'), ['template' => 'Hasil {nama}'])->assertSessionHasNoErrors();
        expect(Announcement::template())->toBe('Hasil {nama}');
    });
});

describe('default operator role', function () {
    test('may export, run bulk actions, place and record payments but not bypass or edit the template', function () {
        $operator = userWithRole(DefaultRole::Operator);
        $this->actingAs($operator);

        expect($operator->can(Ability::RegistrantExport->value))->toBeTrue()
            ->and($operator->can(Ability::RegistrantBulk->value))->toBeTrue()
            ->and($operator->can(Ability::RegistrantPlace->value))->toBeTrue()
            ->and($operator->can(Ability::PaymentRecord->value))->toBeTrue()
            ->and($operator->can(Ability::PaymentExport->value))->toBeTrue();

        $this->get(route('admin.registrants.export'))->assertOk();
        $this->get(route('admin.payments.create'))->assertOk();
        $this->get(route('admin.admission-settings.edit'))->assertForbidden();
        $this->post(route('admin.registrants.bypass', admissionRegistrant()), ['status' => 'validated', 'note' => 'Langsung'])->assertForbidden();
    });
});

describe('payments', function () {
    test('recording a payment directly needs the record ability, verifying is not enough', function () {
        $this->actingAs(staffWithAbilities(Ability::PaymentView, Ability::PaymentVerify));

        $this->get(route('admin.payments.create'))->assertForbidden();
        $this->getJson(route('admin.payments.open-items', ['payer' => 1]))->assertForbidden();
        $this->post(route('admin.payments.store'), [])->assertForbidden();

        $this->actingAs(staffWithAbilities(Ability::PaymentRecord));

        $this->get(route('admin.payments.create'))->assertOk();
        $this->getJson(route('admin.payments.open-items', ['payer' => 1]))->assertOk();
        $this->post(route('admin.payments.store'), [])->assertSessionHasErrors('payer_person_id');
    });

    test('whoever may record but not verify cannot verify or reject', function () {
        $payment = Payment::factory()->create(['status' => PaymentStatus::Pending]);
        PaymentAllocation::factory()->create(['payment_id' => $payment->id, 'amount' => $payment->amount]);
        $this->actingAs(staffWithAbilities(Ability::PaymentView, Ability::PaymentRecord));

        $this->put(route('admin.payments.verify', $payment))->assertForbidden();
        $this->put(route('admin.payments.reject', $payment), ['reason' => 'x'])->assertForbidden();
        $this->post(route('admin.payments.bulk-verify'), ['ids' => [$payment->id]])->assertForbidden();
        expect($payment->refresh()->status)->toBe(PaymentStatus::Pending);
    });

    test('exporting needs the export ability on top of seeing payments', function () {
        $this->actingAs(staffWithAbilities(Ability::PaymentView))->get(route('admin.payments.export'))->assertForbidden();
        $this->actingAs(staffWithAbilities(Ability::PaymentExport))->get(route('admin.payments.export'))->assertForbidden();

        $this->actingAs(staffWithAbilities(Ability::PaymentView, Ability::PaymentExport))->get(route('admin.payments.export'))->assertOk();
    });

    test('the list tells the page which buttons to show', function () {
        $this->actingAs(staffWithAbilities(Ability::PaymentView));
        $this->get(route('admin.payments.index'))->assertInertia(fn ($page) => $page
            ->where('can', ['verify' => false, 'record' => false, 'export' => false]));

        $this->actingAs(staffWithAbilities(Ability::PaymentView, Ability::PaymentVerify, Ability::PaymentRecord, Ability::PaymentExport));
        $this->get(route('admin.payments.index'))->assertInertia(fn ($page) => $page
            ->where('can', ['verify' => true, 'record' => true, 'export' => true]));
    });
});

describe('notes', function () {
    test('deleting someone else\'s note needs the delete ability, managing is enough only to edit', function () {
        $note = Note::factory()->public()->create();
        $payload = ['body' => 'Diubah', 'urgency' => 'low', 'visibility' => 'public'];

        $this->actingAs(staffWithAbilities(Ability::NoteView, Ability::NoteManage));
        $this->put(route('admin.notes.update', $note), $payload)->assertRedirect();
        $this->delete(route('admin.notes.destroy', $note))->assertForbidden();
        expect(Note::query()->whereKey($note->id)->exists())->toBeTrue();

        $this->actingAs(staffWithAbilities(Ability::NoteView, Ability::NoteDelete));
        $this->put(route('admin.notes.update', $note), $payload)->assertForbidden();
        $this->delete(route('admin.notes.destroy', $note))->assertRedirect();
        expect(Note::query()->whereKey($note->id)->exists())->toBeFalse();
    });

    test('authors can still delete their own note', function () {
        $author = staffWithAbilities(Ability::NoteView, Ability::NoteCreate);
        $note = Note::factory()->public()->create(['author_id' => $author->id]);

        $this->actingAs($author)->delete(route('admin.notes.destroy', $note))->assertRedirect();

        expect(Note::query()->whereKey($note->id)->exists())->toBeFalse();
    });

    test('the person page marks which notes can be changed and which deleted', function () {
        $person = Person::factory()->create();
        Note::factory()->public()->create(['noteable_id' => $person->id]);
        $this->actingAs(staffWithAbilities(Ability::PersonView, Ability::NoteView, Ability::NoteManage));

        $this->get(route('admin.people.show', $person))->assertInertia(fn ($page) => $page
            ->where('notes.0.can_update', true)->where('notes.0.can_delete', false));
    });
});

describe('people', function () {
    test('registrations are left out of the person page and quick view without the right to see registrants', function () {
        $child = Person::factory()->create();
        Registrant::factory()->create(['person_id' => $child->id]);

        $this->actingAs(staffWithAbilities(Ability::PersonView));
        $this->get(route('admin.people.show', $child))->assertInertia(fn ($page) => $page
            ->where('registrations', [])->where('can.viewRegistrations', false));
        $this->getJson(route('admin.people.quick-view', $child))
            ->assertJsonPath('data.registrations', [])
            ->assertJsonPath('data.can_view_registrations', false);

        $this->actingAs(staffWithAbilities(Ability::PersonView, Ability::RegistrantView));
        $this->get(route('admin.people.show', $child))->assertInertia(fn ($page) => $page
            ->has('registrations', 1)->where('can.viewRegistrations', true));
        $this->getJson(route('admin.people.quick-view', $child))
            ->assertJsonCount(1, 'data.registrations')
            ->assertJsonPath('data.can_view_registrations', true);
    });

    test('the list only offers adding people to those who may', function () {
        $this->actingAs(staffWithAbilities(Ability::PersonView));
        $this->get(route('admin.people.index'))->assertInertia(fn ($page) => $page->where('can.create', false));

        $this->actingAs(staffWithAbilities(Ability::PersonView, Ability::PersonCreate));
        $this->get(route('admin.people.index'))->assertInertia(fn ($page) => $page->where('can.create', true));
    });
});
