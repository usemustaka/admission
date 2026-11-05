<?php

use App\Enums\DefaultRole;
use App\Models\Setting;
use App\Models\User;
use App\Support\Brand;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    Brand::flush();
});

function saveSettings(string $group, array $data = [])
{
    return test()->post(route('admin.settings.update', $group), Arr::undot($data));
}

test('the settings pages are for people who may manage settings', function () {
    $this->actingAs(userWithRole(DefaultRole::Operator));

    $this->get(route('admin.settings.edit', 'identitas'))->assertForbidden();
    saveSettings('identitas', ['brand.name' => 'Sekolah Harapan'])->assertForbidden();

    expect(Setting::get('brand.name'))->toBeNull();
});

test('guests and parents cannot open the settings', function () {
    $this->get(route('admin.settings.edit', 'identitas'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())->get(route('admin.settings.edit', 'identitas'))->assertForbidden();
});

test('the settings index opens the identity tab', function () {
    $this->actingAs(userWithRole(DefaultRole::Admin));

    $this->get(route('admin.settings.index'))->assertRedirect(route('admin.settings.edit', 'identitas'));
});

test('an unknown group is not found', function () {
    $this->actingAs(userWithRole(DefaultRole::Admin));

    $this->get('/admin/settings/rahasia')->assertNotFound();
});

test('a tab shows its current values and the defaults', function () {
    $this->actingAs(userWithRole(DefaultRole::Admin));
    Setting::put('contact.phone', '021 555 0100');

    $this->get(route('admin.settings.edit', 'kontak'))->assertInertia(fn ($page) => $page
        ->component('admin/settings/edit')
        ->where('group', 'kontak')
        ->where('values.contact.phone', '021 555 0100')
        ->has('groups', 5));
});

test('the identity tab saves the names and the logo', function () {
    $this->actingAs(userWithRole(DefaultRole::Admin));

    saveSettings('identitas', [
        'brand.name' => 'Sekolah Harapan',
        'foundation.name' => 'Yayasan Harapan Bangsa',
        'brand.tagline' => 'Mendidik dengan hati',
        'brand.show_name' => false,
        'logo' => UploadedFile::fake()->image('logo.png'),
    ])->assertSessionHasNoErrors();

    Brand::flush();

    expect(Brand::name())->toBe('Sekolah Harapan')
        ->and(Brand::get('foundation.name'))->toBe('Yayasan Harapan Bangsa')
        ->and(Brand::get('brand.show_name'))->toBeFalse()
        ->and(Brand::logoUrl())->not->toBeNull();

    Storage::disk('public')->assertExists(Setting::get('brand.logo_path'));
});

test('replacing or removing the logo deletes the old file', function () {
    $this->actingAs(userWithRole(DefaultRole::Admin));

    saveSettings('identitas', ['brand.name' => 'Sekolah', 'logo' => UploadedFile::fake()->image('a.png')]);
    $first = Setting::get('brand.logo_path');

    saveSettings('identitas', ['brand.name' => 'Sekolah', 'logo' => UploadedFile::fake()->image('b.png')]);
    $second = Setting::get('brand.logo_path');

    Storage::disk('public')->assertMissing($first);
    Storage::disk('public')->assertExists($second);

    saveSettings('identitas', ['brand.name' => 'Sekolah', 'remove_logo' => true]);

    Storage::disk('public')->assertMissing($second);
    expect(Setting::get('brand.logo_path'))->toBeNull();
});

test('the app name is required and files must be small images', function () {
    $this->actingAs(userWithRole(DefaultRole::Admin));

    saveSettings('identitas', ['brand.name' => ''])->assertSessionHasErrors('brand.name');
    saveSettings('identitas', ['brand.name' => 'Sekolah', 'logo' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml')])->assertSessionHasErrors('logo');
    saveSettings('identitas', ['brand.name' => 'Sekolah', 'logo' => UploadedFile::fake()->image('besar.png')->size(2048)])->assertSessionHasErrors('logo');
});

test('only a six digit hex colour is accepted as the accent', function () {
    $this->actingAs(userWithRole(DefaultRole::Admin));

    saveSettings('tampilan', ['brand.accent_color' => 'merah'])->assertSessionHasErrors('brand.accent_color');
    saveSettings('tampilan', ['brand.accent_color' => '#f00'])->assertSessionHasErrors('brand.accent_color');
    saveSettings('tampilan', ['brand.accent_color' => '#2563eb'])->assertSessionHasNoErrors();

    Brand::flush();

    expect(Brand::accent())->toBe('#2563eb');
});

test('the contact, payment and front page tabs save their fields and blank ones reset to the default', function () {
    $this->actingAs(userWithRole(DefaultRole::Admin));

    saveSettings('kontak', ['contact.whatsapp' => '081234567890', 'contact.email' => 'admin@sekolah.test', 'contact.website' => 'https://sekolah.test'])->assertSessionHasNoErrors();
    saveSettings('pembayaran', ['payment.bank_name' => 'BSI', 'payment.account_number' => '7001234567', 'payment.account_holder' => 'Yayasan Harapan'])->assertSessionHasNoErrors();
    saveSettings('halaman-depan', ['brand.welcome_title' => 'PSB 2027', 'brand.welcome_description' => '   ', 'brand.footer_text' => '© Harapan'])->assertSessionHasNoErrors();

    Brand::flush();
    $shared = Brand::shared();

    expect($shared['contact']['whatsapp'])->toBe('081234567890')
        ->and($shared['payment']['account_number'])->toBe('7001234567')
        ->and($shared['public']['welcome_title'])->toBe('PSB 2027')
        ->and($shared['public']['welcome_description'])->toBe(Brand::DEFAULT_WELCOME_DESCRIPTION)
        ->and($shared['public']['footer_text'])->toBe('© Harapan');

    saveSettings('kontak', ['contact.email' => 'bukan-email'])->assertSessionHasErrors('contact.email');
});

test('every page receives the brand and the app name follows it', function () {
    $this->actingAs(User::factory()->create());
    Setting::put('brand.name', 'Sekolah Harapan');
    Setting::put('payment.account_number', '7001234567');
    Brand::flush();

    $this->get(route('portal'))->assertInertia(fn ($page) => $page
        ->where('name', 'Sekolah Harapan')
        ->where('brand.name', 'Sekolah Harapan')
        ->where('brand.payment.account_number', '7001234567'));
});

test('the document head carries the name, favicon and accent colour', function () {
    Setting::put('brand.name', 'Sekolah Harapan');
    Setting::put('brand.accent_color', '#2563eb');
    Setting::put('brand.favicon_path', $path = 'brand/ikon.png');
    Storage::disk('public')->put($path, 'x');
    Brand::flush();

    $html = $this->get(route('home'))->assertOk()->getContent();

    expect($html)->toContain('<meta name="app-name" content="Sekolah Harapan">')
        ->and($html)->toContain('--color-lime-400:')
        ->and($html)->toContain('/storage/brand/ikon.png');
});

test('the default accent adds no theme override', function () {
    Brand::flush();

    expect(Brand::themeVariables())->toBe([]);

    $this->get(route('home'))->assertOk()->assertDontSee('--color-lime-400:', false);
});

test('only people who may manage settings see the settings menu', function () {
    $this->actingAs(userWithRole(DefaultRole::Admin))->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('auth.abilities', fn ($abilities) => collect($abilities)->contains('core::setting.manage')));

    $this->actingAs(userWithRole(DefaultRole::Operator))->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('auth.abilities', fn ($abilities) => ! collect($abilities)->contains('core::setting.manage')));
});
