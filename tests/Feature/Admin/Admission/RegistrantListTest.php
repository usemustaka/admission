<?php

use App\Enums\DefaultRole;
use App\Enums\RegistrantStatus;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Registrant;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(userWithRole(DefaultRole::Operator));
});

test('only staff can see the applicants', function () {
    $this->get(route('admin.registrants.index'))->assertOk();
    $this->actingAs(User::factory()->create())->get(route('admin.registrants.index'))->assertForbidden();
});

test('the list can be filtered by status, organization, name and what needs a NIK check', function () {
    $school = Organization::factory()->create();
    $reviewing = Registrant::factory()->create(['status' => RegistrantStatus::Review, 'organization_id' => $school->id, 'person_id' => Person::factory()->create(['name' => 'Aisyah Rahma'])->id]);
    Registrant::factory()->create(['status' => RegistrantStatus::Draft]);
    $flagged = Registrant::factory()->create(['needs_identity_review' => true]);

    $this->get(route('admin.registrants.index'))->assertInertia(fn ($page) => $page
        ->has('registrants.data', 3)
        ->where('counts.needs_review', 1)
        ->where('counts.in_review', 1));

    $this->get(route('admin.registrants.index', ['status' => 'review']))->assertInertia(fn ($page) => $page
        ->has('registrants.data', 1)
        ->where('registrants.data.0.id', $reviewing->id));

    $this->get(route('admin.registrants.index', ['lembaga' => $school->id]))->assertInertia(fn ($page) => $page->has('registrants.data', 1));
    $this->get(route('admin.registrants.index', ['cari' => 'aisyah']))->assertInertia(fn ($page) => $page->has('registrants.data', 1));

    $this->get(route('admin.registrants.index', ['perlu_dicek' => 1]))->assertInertia(fn ($page) => $page
        ->has('registrants.data', 1)
        ->where('registrants.data.0.id', $flagged->id)
        ->where('registrants.data.0.needs_identity_review', true));
});

test('the list can be sorted by name', function () {
    Registrant::factory()->create(['person_id' => Person::factory()->create(['name' => 'Budi'])->id]);
    Registrant::factory()->create(['person_id' => Person::factory()->create(['name' => 'Ani'])->id]);

    $this->get(route('admin.registrants.index', ['urut' => 'nama']))->assertInertia(fn ($page) => $page
        ->where('registrants.data.0.child.name', 'Ani')
        ->where('filters.arah', 'asc'));

    $this->get(route('admin.registrants.index', ['urut' => 'nama', 'arah' => 'desc']))->assertInertia(fn ($page) => $page->where('registrants.data.0.child.name', 'Budi'));
});

test('the detail shows the result against the KKM, the history and the announcement target', function () {
    $registrant = admissionRegistrant(RegistrantStatus::Validated, ['exam_scores' => [80, 50]]);
    $registrant->statusChanges()->create(['from_status' => 'review', 'to_status' => 'validated', 'note' => 'Lengkap', 'is_bypass' => true]);

    $this->get(route('admin.registrants.show', $registrant))->assertInertia(fn ($page) => $page
        ->component('admin/registrants/show')
        ->where('registrant.status', 'validated')
        ->where('result.passed', false)
        ->where('result.aspects.1.passed', false)
        ->where('timeline.0.is_bypass', true)
        ->where('timeline.0.note', 'Lengkap')
        ->where('can.announce', true)
        ->where('can.accept', false)
        ->has('announcement.text')
        ->where('announcement.whatsapp_url', fn ($url) => str_starts_with($url, 'https://wa.me/62')));
});

test('the detail of a flagged registration says so and offers the fix', function () {
    $registrant = Registrant::factory()->create(['needs_identity_review' => true, 'status' => RegistrantStatus::Draft]);

    $this->get(route('admin.registrants.show', $registrant))->assertInertia(fn ($page) => $page
        ->where('registrant.needs_identity_review', true)
        ->where('readiness.0', 'Panitia masih mencocokkan data anak')
        ->where('can.manage', true));
});
