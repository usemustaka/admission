<?php

use App\Enums\DefaultRole;
use App\Models\Setting;
use App\Models\User;
use App\Support\StaffTwoFactor;
use Inertia\Testing\AssertableInertia as Assert;

test('the requirement is off by default', function () {
    expect(StaffTwoFactor::isRequired())->toBeFalse();

    $this->actingAs(userWithRole(DefaultRole::Admin))
        ->get(route('admin.people.index'))
        ->assertOk();
});

test('staff without two-factor are sent to the security page while it is required', function () {
    Setting::put(StaffTwoFactor::SETTING_KEY, true);

    $this->actingAs(userWithRole(DefaultRole::Admin))
        ->get(route('admin.people.index'))
        ->assertRedirect(route('security.edit'));
});

test('staff with confirmed two-factor go through', function () {
    Setting::put(StaffTwoFactor::SETTING_KEY, true);
    $staff = userWithRole(DefaultRole::Admin);
    $staff->forceFill(['two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => now()])->save();

    $this->actingAs($staff)->get(route('admin.people.index'))->assertOk();
});

test('the pages staff need to turn two-factor on stay reachable', function () {
    Setting::put(StaffTwoFactor::SETTING_KEY, true);
    $staff = userWithRole(DefaultRole::Admin);

    $this->actingAs($staff)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('twoFactorRequired', true));

    $this->actingAs($staff)->get(route('profile.edit'))->assertOk();
    $this->actingAs($staff)->get(route('appearance.edit'))->assertOk();
    $this->actingAs($staff)->post(route('logout'))->assertRedirect(route('home'));
});

test('parents are never asked for two-factor', function () {
    Setting::put(StaffTwoFactor::SETTING_KEY, true);
    $parent = User::factory()->create();

    $this->actingAs($parent)->get(route('portal'))->assertOk();
    $this->actingAs($parent)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('twoFactorRequired', false));
});

test('the environment decides until the school saves a choice', function () {
    config(['security.require_two_factor_staff' => true]);
    $staff = userWithRole(DefaultRole::Admin);

    $this->actingAs($staff)->get(route('admin.people.index'))->assertRedirect(route('security.edit'));

    Setting::put(StaffTwoFactor::SETTING_KEY, false);

    $this->actingAs($staff)->get(route('admin.people.index'))->assertOk();
});

test('the requirement can be switched on from the security settings', function () {
    $admin = userWithRole(DefaultRole::SuperAdmin);
    $admin->forceFill(['two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => now()])->save();

    $this->actingAs($admin)
        ->get(route('admin.settings.edit', 'keamanan'))
        ->assertInertia(fn (Assert $page) => $page->where('values.security.require_two_factor_staff', false));

    $this->actingAs($admin)
        ->post(route('admin.settings.update', 'keamanan'), ['security' => ['require_two_factor_staff' => true]])
        ->assertSessionHasNoErrors();

    expect(StaffTwoFactor::isRequired())->toBeTrue();
});
