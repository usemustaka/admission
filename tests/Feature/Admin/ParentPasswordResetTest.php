<?php

use App\Enums\Ability;
use App\Enums\DefaultRole;
use App\Enums\RegistrantStatus;
use App\Models\Note;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->parent = userWithRole(DefaultRole::User);
    $this->parent->update(['email' => 'ibu@example.test']);
    $this->registrant = admissionRegistrant(RegistrantStatus::Review, ['registered_by_person_id' => $this->parent->person_id]);
});

test('an admin sends the parent a link to choose a new password and leaves a note', function () {
    Notification::fake();

    $this->actingAs(userWithRole(DefaultRole::Admin))
        ->post(route('admin.registrants.reset-password', $this->registrant))
        ->assertSessionHasNoErrors();

    Notification::assertSentTo($this->parent, ResetPassword::class);
    expect(Note::query()->where('person_id', $this->registrant->person_id)->where('body', 'like', '%ibu@example.test%')->exists())->toBeTrue();
});

test('staff without the ability cannot send it', function () {
    Notification::fake();

    $this->actingAs(registrantViewer())->post(route('admin.registrants.reset-password', $this->registrant))->assertForbidden();

    Notification::assertNothingSent();
});

test('a registration whose registrant has no login account reports it instead of failing', function () {
    $registrant = admissionRegistrant();

    $this->actingAs(userWithRole(DefaultRole::Admin))
        ->post(route('admin.registrants.reset-password', $registrant))
        ->assertSessionHasErrors('password');
});

test('it never reaches a staff account', function () {
    Notification::fake();
    $this->parent->givePermissionTo(Ability::AdminAccess->value);

    $this->actingAs(userWithRole(DefaultRole::Admin))
        ->post(route('admin.registrants.reset-password', $this->registrant))
        ->assertSessionHasErrors('password');

    Notification::assertNothingSent();
});
