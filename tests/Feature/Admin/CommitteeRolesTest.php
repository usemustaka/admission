<?php

use App\Enums\DefaultRole;
use App\Enums\RegistrantStatus;
use App\Models\User;
use App\Models\Year;
use Database\Seeders\RolesAndPermissionsSeeder;

function committeeMember(DefaultRole $role): User
{
    test()->seed(RolesAndPermissionsSeeder::class);

    return tap(User::factory()->create())->assignRole($role->value);
}

describe('test committee', function () {
    test('enters scores and announces but cannot review, place or touch payments', function () {
        $member = committeeMember(DefaultRole::TestCommittee);
        $registrant = admissionRegistrant(RegistrantStatus::Validated, ['exam_scores' => [null, null]]);

        $this->actingAs($member)->put(route('admin.registrants.scores', $registrant), ['scores' => [0 => 80, 1 => 75]])->assertSessionHasNoErrors();
        expect($registrant->refresh()->exam_scores)->toBe([80, 75]);

        $this->post(route('admin.registrants.announce', $registrant))->assertSessionHasNoErrors();
        expect($registrant->refresh()->status)->toBe(RegistrantStatus::Announced);

        $this->post(route('admin.registrants.accept', admissionRegistrant(RegistrantStatus::Review)))->assertForbidden();
        $this->post(route('admin.registrants.place', $registrant), ['year_id' => Year::factory()->active()->create()->id, 'nis' => '1'])->assertForbidden();
        $this->get(route('admin.payments.index'))->assertForbidden();
    });
});

describe('finance committee', function () {
    test('places announced registrants and verifies payments but cannot enter scores or announce', function () {
        $member = committeeMember(DefaultRole::FinanceCommittee);
        $registrant = admissionRegistrant(RegistrantStatus::Announced);

        $this->actingAs($member)->get(route('admin.payments.index'))->assertOk();

        $this->put(route('admin.registrants.scores', $registrant), ['scores' => [0 => 80]])->assertForbidden();
        $this->post(route('admin.registrants.announce', admissionRegistrant(RegistrantStatus::Validated)))->assertForbidden();

        $this->post(route('admin.registrants.place', $registrant), ['year_id' => Year::factory()->active()->create()->id, 'nis' => '2026009'])->assertSessionHasNoErrors();
        expect($registrant->refresh()->status)->toBe(RegistrantStatus::Placed);
    });
});

test('running the seeder again keeps what an admin changed on a committee role', function () {
    $member = committeeMember(DefaultRole::TestCommittee);
    $role = $member->roles->first();
    $role->revokePermissionTo('admission::registrant.export');

    $this->seed(RolesAndPermissionsSeeder::class);

    expect($role->refresh()->hasPermissionTo('admission::registrant.export'))->toBeFalse();
});
