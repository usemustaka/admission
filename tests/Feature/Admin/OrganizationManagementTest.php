<?php

use App\Enums\DefaultRole;
use App\Enums\OrganizationCategory;
use App\Models\Employee;
use App\Models\ExamDay;
use App\Models\Organization;
use App\Models\Registrant;
use App\Models\Ruleset;
use App\Models\Student;
use App\Models\User;
use App\Models\Wave;

beforeEach(fn () => $this->withoutVite());

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function organizationPayload(array $overrides = []): array
{
    return ['name' => 'SD Mustaka', 'code' => 'sd-mustaka', 'category' => 'sekolah', 'is_active' => true, ...$overrides];
}

test('organization screens are closed to parents and to staff without the organization ability', function () {
    $organization = Organization::factory()->create();

    foreach ([User::factory()->create(), userWithRole(DefaultRole::Operator)] as $outsider) {
        $this->actingAs($outsider);

        $this->get(route('admin.organizations.index'))->assertForbidden();
        $this->post(route('admin.organizations.store'), organizationPayload())->assertForbidden();
        $this->put(route('admin.organizations.update', $organization), organizationPayload())->assertForbidden();
        $this->patch(route('admin.organizations.move', $organization), ['direction' => 'down'])->assertForbidden();
        $this->delete(route('admin.organizations.destroy', $organization))->assertForbidden();
    }

    expect(Organization::count())->toBe(1);
});

test('the list is ordered and counts what each organization is used for', function () {
    $this->actingAs(userWithRole(DefaultRole::Admin));
    $second = Organization::factory()->create(['name' => 'Kedua', 'sort_order' => 2]);
    $first = Organization::factory()->create(['name' => 'Pertama', 'sort_order' => 1]);
    $unused = Organization::factory()->create(['name' => 'Kosong', 'sort_order' => 3, 'is_active' => false]);
    Ruleset::factory()->create(['organization_id' => $first->id]);
    Registrant::factory()->count(2)->create(['organization_id' => $first->id]);
    Student::factory()->create(['organization_id' => $first->id]);

    $this->get(route('admin.organizations.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/organizations/index')
            ->where('organizations.0.name', 'Pertama')
            ->where('organizations.0.rulesets_count', 1)
            ->where('organizations.0.registrants_count', 2)
            ->where('organizations.0.students_count', 1)
            ->where('organizations.0.in_use', true)
            ->where('organizations.1.name', 'Kedua')
            ->where('organizations.1.in_use', false)
            ->where('organizations.2.is_active', false));

    expect($second->id)->not->toBe($unused->id);
});

test('an admin creates a lembaga at the end of the list and edits it', function () {
    $this->actingAs(userWithRole(DefaultRole::Admin));
    Organization::factory()->create(['sort_order' => 4]);

    $this->post(route('admin.organizations.store'), organizationPayload(['code' => ' SD-Mustaka ', 'category' => '']))->assertRedirect(route('admin.organizations.index'));

    $organization = Organization::where('name', 'SD Mustaka')->firstOrFail();
    expect($organization->code)->toBe('sd-mustaka')
        ->and($organization->category)->toBeNull()
        ->and($organization->sort_order)->toBe(5)
        ->and($organization->is_active)->toBeTrue();

    $this->put(route('admin.organizations.update', $organization), organizationPayload(['name' => 'SD Mustaka Baru', 'code' => 'sd', 'is_active' => false]))->assertSessionHasNoErrors();

    expect($organization->refresh()->name)->toBe('SD Mustaka Baru')->and($organization->code)->toBe('sd')->and($organization->is_active)->toBeFalse();
});

test('the code is a unique lowercase slug', function () {
    $this->actingAs(userWithRole(DefaultRole::Admin));
    Organization::factory()->create(['code' => 'smp']);

    $this->post(route('admin.organizations.store'), organizationPayload(['code' => 'smp']))->assertSessionHasErrors('code');
    $this->post(route('admin.organizations.store'), organizationPayload(['code' => 'SMP']))->assertSessionHasErrors('code');
    $this->post(route('admin.organizations.store'), organizationPayload(['code' => 'sd mustaka']))->assertSessionHasErrors('code');
    $this->post(route('admin.organizations.store'), organizationPayload(['code' => '-sd']))->assertSessionHasErrors('code');
    $this->post(route('admin.organizations.store'), organizationPayload(['name' => '']))->assertSessionHasErrors('name');

    expect(Organization::count())->toBe(1);
});

test('the code can only change while nothing uses the lembaga', function () {
    $this->actingAs(userWithRole(DefaultRole::Admin));

    $free = Organization::factory()->create(['code' => 'bebas']);
    $this->put(route('admin.organizations.update', $free), organizationPayload(['code' => 'bebas-baru']))->assertSessionHasNoErrors();
    expect($free->refresh()->code)->toBe('bebas-baru');

    foreach ([
        fn (Organization $organization) => Ruleset::factory()->create(['organization_id' => $organization->id]),
        fn (Organization $organization) => Registrant::factory()->create(['organization_id' => $organization->id]),
        fn (Organization $organization) => Student::factory()->create(['organization_id' => $organization->id]),
        fn (Organization $organization) => Employee::factory()->create(['organization_id' => $organization->id]),
    ] as $index => $use) {
        $organization = Organization::factory()->create(['code' => "dipakai-{$index}"]);
        $use($organization);

        $this->put(route('admin.organizations.update', $organization), organizationPayload(['code' => 'diganti']))->assertSessionHasErrors('code');
        $this->put(route('admin.organizations.update', $organization), organizationPayload(['name' => 'Nama baru', 'code' => "dipakai-{$index}"]))->assertSessionHasNoErrors();

        expect($organization->refresh()->code)->toBe("dipakai-{$index}")->and($organization->name)->toBe('Nama baru');
    }
});

test('a lembaga moves up and down and the order is renumbered', function () {
    $this->actingAs(userWithRole(DefaultRole::Admin));
    $a = Organization::factory()->create(['name' => 'A', 'sort_order' => 5]);
    $b = Organization::factory()->create(['name' => 'B', 'sort_order' => 5]);
    $c = Organization::factory()->create(['name' => 'C', 'sort_order' => 9]);

    $this->patch(route('admin.organizations.move', $c), ['direction' => 'up'])->assertRedirect();
    expect(Organization::orderBy('sort_order')->pluck('name')->all())->toBe(['A', 'C', 'B'])
        ->and(Organization::orderBy('sort_order')->pluck('sort_order')->all())->toBe([1, 2, 3]);

    $this->patch(route('admin.organizations.move', $a), ['direction' => 'up']);
    $this->patch(route('admin.organizations.move', $b), ['direction' => 'down']);
    expect(Organization::orderBy('sort_order')->pluck('name')->all())->toBe(['A', 'C', 'B']);

    $this->patch(route('admin.organizations.move', $a), ['direction' => 'down']);
    expect(Organization::orderBy('sort_order')->pluck('name')->all())->toBe(['C', 'A', 'B']);

    $this->patch(route('admin.organizations.move', $a), ['direction' => 'sideways'])->assertSessionHasErrors('direction');
    expect($b->refresh()->sort_order)->toBe(3);
});

test('only a lembaga nobody uses can be deleted, the others are deactivated instead', function () {
    $this->actingAs(userWithRole(DefaultRole::Admin));
    $free = Organization::factory()->create();

    $this->delete(route('admin.organizations.destroy', $free))->assertRedirect(route('admin.organizations.index'));
    expect(Organization::whereKey($free->id)->exists())->toBeFalse();

    foreach ([
        fn (Organization $organization) => Ruleset::factory()->create(['organization_id' => $organization->id]),
        fn (Organization $organization) => Registrant::factory()->create(['organization_id' => $organization->id]),
        fn (Organization $organization) => Student::factory()->create(['organization_id' => $organization->id]),
        fn (Organization $organization) => Employee::factory()->create(['organization_id' => $organization->id]),
        fn (Organization $organization) => ExamDay::factory()->create(['organization_id' => $organization->id]),
    ] as $use) {
        $organization = Organization::factory()->create();
        $use($organization);

        $this->delete(route('admin.organizations.destroy', $organization))->assertSessionHasErrors('organization');

        expect(session('errors')->first('organization'))->toContain('Nonaktifkan')
            ->and(Organization::whereKey($organization->id)->exists())->toBeTrue();
    }
});

test('a lembaga is in use once it has an exam day, even when nothing else uses it', function () {
    $organization = Organization::factory()->create();

    expect($organization->isInUse())->toBeFalse();

    ExamDay::factory()->create(['organization_id' => $organization->id]);

    expect($organization->isInUse())->toBeTrue();
});

test('an inactive lembaga is not offered to new registrations, but its old data stays', function () {
    $parent = User::factory()->create();
    $active = Organization::factory()->create(['name' => 'Aktif']);
    $inactive = Organization::factory()->create(['name' => 'Tutup', 'is_active' => false]);
    $wave = Wave::factory()->create();
    $registrant = Registrant::factory()->create(['organization_id' => $inactive->id]);

    $this->actingAs($parent)->get(route('portal.children.create'))->assertInertia(fn ($page) => $page
        ->has('organizations', 1)
        ->where('organizations.0.label', 'Aktif'));

    $this->post(route('portal.children.store'), [
        'name' => 'Ahmad Fauzi', 'nik' => '3201123456789012', 'gender' => 'male', 'birth_date' => '2014-05-06',
        'organization_id' => $inactive->id, 'wave_id' => $wave->id, 'relation' => 'father', 'kind' => 'biological',
    ])->assertSessionHasErrors('organization_id');

    $this->actingAs(userWithRole(DefaultRole::Admin))->get(route('admin.registrants.index'))->assertInertia(fn ($page) => $page
        ->has('options.organizations', 2)
        ->where('registrants.data.0.organization', 'Tutup'));

    expect($registrant->refresh()->organization_id)->toBe($inactive->id);
});

test('an inactive lembaga is not offered for new rulesets, except to the rulesets that already use it', function () {
    $this->actingAs(userWithRole(DefaultRole::Admin));
    $active = Organization::factory()->create(['name' => 'Aktif']);
    $inactive = Organization::factory()->create(['name' => 'Tutup', 'is_active' => false]);
    $legacy = Ruleset::factory()->create(['organization_id' => $inactive->id]);
    $other = Ruleset::factory()->create(['organization_id' => $active->id]);

    $this->get(route('admin.rulesets.create'))->assertInertia(fn ($page) => $page->has('options.organizations', 1)->where('options.organizations.0.label', 'Aktif'));
    $this->get(route('admin.rulesets.edit', $legacy))->assertInertia(fn ($page) => $page->has('options.organizations', 2));
    $this->get(route('admin.rulesets.edit', $other))->assertInertia(fn ($page) => $page->has('options.organizations', 1));
    $this->get(route('admin.rulesets.index'))->assertInertia(fn ($page) => $page->has('options.organizations', 2));

    $this->post(route('admin.rulesets.store'), ['name' => 'Baru', 'organization_id' => $inactive->id, 'is_active' => true])->assertSessionHasErrors('organization_id');
    $this->put(route('admin.rulesets.update', $other), ['name' => 'Ubah', 'organization_id' => $inactive->id, 'is_active' => true])->assertSessionHasErrors('organization_id');
    $this->put(route('admin.rulesets.update', $legacy), ['name' => 'Tetap', 'organization_id' => $inactive->id, 'is_active' => true])->assertSessionHasNoErrors();
    $this->post(route('admin.rulesets.store'), ['name' => 'Baru', 'organization_id' => $active->id, 'is_active' => true])->assertSessionHasNoErrors();
});

test('a lembaga takes one of the fixed categories, or none', function () {
    $this->actingAs(userWithRole(DefaultRole::Admin));

    foreach (['sekolah', 'madrasah', 'pesantren', 'bootcamp'] as $category) {
        $this->post(route('admin.organizations.store'), organizationPayload(['code' => "lembaga-{$category}", 'category' => $category]))
            ->assertSessionHasNoErrors();

        expect(Organization::query()->where('code', "lembaga-{$category}")->firstOrFail()->category->value)->toBe($category);
    }

    $this->post(route('admin.organizations.store'), organizationPayload(['code' => 'tanpa-kategori', 'category' => '']))->assertSessionHasNoErrors();

    expect(Organization::query()->where('code', 'tanpa-kategori')->firstOrFail()->category)->toBeNull();
});

test('a category outside the fixed set is refused', function () {
    $this->actingAs(userWithRole(DefaultRole::Admin));

    $this->post(route('admin.organizations.store'), organizationPayload(['category' => 'smp']))->assertSessionHasErrors('category');

    $organization = Organization::factory()->create();

    $this->put(route('admin.organizations.update', $organization), organizationPayload(['code' => $organization->code, 'category' => 'lainnya']))->assertSessionHasErrors('category');
});

test('the list shows the label of each category and offers the choices', function () {
    $this->actingAs(userWithRole(DefaultRole::Admin));

    Organization::factory()->create(['category' => OrganizationCategory::Madrasah]);

    $this->get(route('admin.organizations.index'))->assertInertia(fn ($page) => $page
        ->where('organizations.0.category', 'madrasah')
        ->where('organizations.0.category_label', 'Madrasah')
        ->where('categories', [
            ['value' => 'sekolah', 'label' => 'Sekolah'],
            ['value' => 'madrasah', 'label' => 'Madrasah'],
            ['value' => 'pesantren', 'label' => 'Pesantren'],
            ['value' => 'bootcamp', 'label' => 'Bootcamp'],
        ]));
});
