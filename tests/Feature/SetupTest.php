<?php

use App\Actions\Users\CreateStaffAccount;
use App\Enums\DefaultRole;
use App\Enums\OrganizationCategory;
use App\Models\Organization;
use App\Models\Setting;
use App\Models\User;
use App\Models\Wave;
use App\Models\Year;
use App\Support\SetupState;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    config(['setup.enforce' => true, 'setup.token' => null]);

    $this->withoutVite();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function setupPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'brand' => ['name' => 'Sekolah Harapan', 'accent_color' => '#0ea5e9'],
        'contact' => ['phone' => '021-5550100', 'whatsapp' => '+62 812-3456-7890', 'email' => 'info@harapan.test'],
        'payment' => ['bank_name' => 'Bank Contoh', 'account_number' => '1234567890', 'account_holder' => 'Yayasan Harapan'],
        'admin' => ['name' => 'Rina Pengelola', 'email' => 'rina@harapan.test', 'password' => 'Sangat-Rahasia-2026!', 'password_confirmation' => 'Sangat-Rahasia-2026!'],
        'organizations' => [['name' => 'SMP Harapan', 'category' => 'sekolah'], ['name' => 'SMA Harapan']],
        'year' => ['name' => '2026/2027', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'with_periods' => true],
    ], $overrides);
}

test('a fresh install sends every page to the setup', function () {
    $this->get('/')->assertRedirect(route('setup.show'));
    $this->get(route('login'))->assertRedirect(route('setup.show'));
    $this->get('/up')->assertOk();
});

test('an install that already has a super admin is not sent to the setup', function () {
    User::factory()->create()->assignRole(Role::findOrCreate(DefaultRole::SuperAdmin->value, 'web'));

    $this->get(route('login'))->assertOk();
    $this->get(route('setup.show'))->assertNotFound();
});

test('the setup page shows what the install needs and sensible defaults', function () {
    $this->get(route('setup.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('setup/index')
            ->where('requiresToken', false)
            ->has('checks', 5)
            ->where('existing.organizations', [])
            ->has('defaults.year.name')
            ->where('defaults.accent_color', '#a3e635'));
});

test('the setup creates everything and signs the first super admin in', function () {
    Storage::fake('public');

    $this->post(route('setup.store'), setupPayload() + ['logo' => UploadedFile::fake()->image('logo.png')])
        ->assertRedirect(route('dashboard'));

    $admin = User::query()->where('email', 'rina@harapan.test')->firstOrFail();

    expect($admin->hasRole(DefaultRole::SuperAdmin->value))->toBeTrue()
        ->and($admin->email_verified_at)->not->toBeNull()
        ->and($admin->person->name)->toBe('Rina Pengelola');

    $this->assertAuthenticatedAs($admin);

    expect(Setting::get('brand.name'))->toBe('Sekolah Harapan')
        ->and(Setting::get('brand.accent_color'))->toBe('#0ea5e9')
        ->and(Setting::get('contact.whatsapp'))->toBe('+6281234567890')
        ->and(Setting::get('payment.account_number'))->toBe('1234567890')
        ->and(Setting::get(SetupState::SETTING_KEY))->not->toBeNull();

    Storage::disk('public')->assertExists(Setting::get('brand.logo_path'));

    expect(Organization::query()->orderBy('sort_order')->pluck('code', 'name')->all())->toBe(['SMP Harapan' => 'smp-harapan', 'SMA Harapan' => 'sma-harapan'])
        ->and(Organization::query()->where('name', 'SMP Harapan')->firstOrFail()->category)->toBe(OrganizationCategory::School);
});

test('the first school year is active and gets a Ganjil and a Genap period that cover it', function () {
    $this->post(route('setup.store'), setupPayload())->assertRedirect();

    $year = Year::query()->firstOrFail();
    $periods = $year->periods()->orderBy('sequence')->get();

    expect($year->is_active)->toBeTrue()
        ->and($periods->pluck('name')->all())->toBe(['Ganjil', 'Genap'])
        ->and($periods[0]->is_active)->toBeTrue()
        ->and($periods[0]->starts_on->toDateString())->toBe('2026-07-01')
        ->and($periods[1]->ends_on->toDateString())->toBe('2027-06-30')
        ->and($periods[0]->ends_on->copy()->addDay()->toDateString())->toBe($periods[1]->starts_on->toDateString());
});

test('periods can be left out and a first wave can be added', function () {
    $this->post(route('setup.store'), setupPayload([
        'year' => ['with_periods' => false],
        'wave' => ['name' => 'Gelombang 1', 'starts_on' => '2026-11-01', 'ends_on' => '2027-01-31'],
    ]))->assertRedirect();

    expect(Year::query()->firstOrFail()->periods()->count())->toBe(0)
        ->and(Wave::query()->count())->toBe(1)
        ->and(Wave::query()->firstOrFail()->is_open)->toBeTrue();
});

test('roles and permissions exist after the setup', function () {
    $this->post(route('setup.store'), setupPayload())->assertRedirect();

    expect(Role::query()->pluck('name')->all())->toContain('super-admin', 'admin', 'operator', 'user')
        ->and(Permission::query()->count())->toBeGreaterThan(10);
});

test('the setup page is gone once it has been done', function () {
    $this->post(route('setup.store'), setupPayload())->assertRedirect();

    $this->get(route('setup.show'))->assertNotFound();
    $this->post(route('setup.store'), setupPayload(['admin' => ['email' => 'lain@harapan.test']]))->assertForbidden();

    expect(User::query()->count())->toBe(1);
});

test('only one setup can finish, even if the page is submitted twice', function () {
    $this->post(route('setup.store'), setupPayload())->assertRedirect();
    Cache::flush();

    $this->post(route('setup.store'), setupPayload())->assertForbidden();

    expect(Organization::query()->count())->toBe(2);
});

test('the lembaga and the school year are required on a fresh install', function () {
    $payload = setupPayload();
    unset($payload['organizations']);
    $payload['year'] = ['name' => '', 'starts_on' => '', 'ends_on' => ''];

    $this->post(route('setup.store'), $payload)
        ->assertSessionHasErrors(['organizations', 'year.name', 'year.starts_on', 'year.ends_on']);

    expect(User::query()->count())->toBe(0);
});

test('lembaga and a school year that already exist are enough', function () {
    Organization::factory()->create();
    Year::factory()->create(['is_active' => true]);

    $payload = setupPayload();
    unset($payload['organizations'], $payload['year']);

    $this->get(route('setup.show'))->assertInertia(fn ($page) => $page->has('existing.organizations', 1)->has('existing.years', 1));
    $this->post(route('setup.store'), $payload)->assertRedirect(route('dashboard'));

    expect(Organization::query()->count())->toBe(1)
        ->and(Year::query()->count())->toBe(1);
});

test('lembaga with the same name get different codes and names cannot repeat', function () {
    Organization::factory()->create(['name' => 'Lama', 'code' => 'smp-harapan']);

    $this->post(route('setup.store'), setupPayload(['organizations' => [['name' => 'SMP Harapan']]]))->assertRedirect();

    expect(Organization::query()->where('name', 'SMP Harapan')->value('code'))->toBe('smp-harapan-2');
});

test('the admin needs a strong password that is typed twice, and a new email', function () {
    $this->post(route('setup.store'), setupPayload(['admin' => ['password' => 'pendek', 'password_confirmation' => 'pendek']]))
        ->assertSessionHasErrors('admin.password');

    $this->post(route('setup.store'), setupPayload(['admin' => ['password_confirmation' => 'Beda-Sekali-2026!']]))
        ->assertSessionHasErrors('admin.password');

    User::factory()->create(['email' => 'rina@harapan.test']);

    $this->post(route('setup.store'), setupPayload())->assertSessionHasErrors('admin.email');
});

test('a first wave needs both dates and the year must end after it starts', function () {
    $this->post(route('setup.store'), setupPayload(['wave' => ['name' => 'Gelombang 1']]))
        ->assertSessionHasErrors(['wave.starts_on', 'wave.ends_on']);

    $this->post(route('setup.store'), setupPayload(['year' => ['starts_on' => '2027-06-30', 'ends_on' => '2026-07-01']]))
        ->assertSessionHasErrors('year.ends_on');
});

test('the logo has to be a small picture', function () {
    $this->post(route('setup.store'), setupPayload() + ['logo' => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf')])->assertSessionHasErrors('logo');
    $this->post(route('setup.store'), setupPayload() + ['logo' => UploadedFile::fake()->image('besar.png')->size(2048)])->assertSessionHasErrors('logo');
});

test('a setup code in the environment has to be typed in', function () {
    config(['setup.token' => 'kode-rahasia']);

    $this->get(route('setup.show'))->assertInertia(fn ($page) => $page->where('requiresToken', true));

    $this->post(route('setup.store'), setupPayload())->assertSessionHasErrors('setup_token');
    $this->post(route('setup.store'), setupPayload(['setup_token' => 'salah']))->assertSessionHasErrors('setup_token');

    expect(User::query()->count())->toBe(0);

    $this->post(route('setup.store'), setupPayload(['setup_token' => 'kode-rahasia']))->assertRedirect(route('dashboard'));
});

test('a failure in the middle leaves the install as fresh as it was', function () {
    Storage::fake('public');

    $this->mock(CreateStaffAccount::class)->shouldReceive('handle')->andThrow(new RuntimeException('gagal'));

    $this->withoutExceptionHandling();

    expect(fn () => $this->post(route('setup.store'), setupPayload() + ['logo' => UploadedFile::fake()->image('logo.png')]))
        ->toThrow(RuntimeException::class);

    expect(Organization::query()->count())->toBe(0)
        ->and(Year::query()->count())->toBe(0)
        ->and(Setting::query()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([])
        ->and(SetupState::isComplete())->toBeFalse();
});

test('the setup offers the fixed categories and refuses any other', function () {
    $this->get(route('setup.show'))->assertInertia(fn ($page) => $page->has('categories', 4)->where('categories.0.value', 'sekolah'));

    $this->post(route('setup.store'), setupPayload(['organizations' => [['name' => 'SMP Harapan', 'category' => 'smp']]]))
        ->assertSessionHasErrors('organizations.0.category');

    expect(User::query()->count())->toBe(0);
});
