<?php

use App\Enums\Ability;
use App\Enums\BillType;
use App\Enums\DefaultRole;
use App\Enums\DocumentType;
use App\Enums\EducationLevel;
use App\Enums\PaymentStatus;
use App\Enums\RegistrantStatus;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\EducationHistory;
use App\Models\Employee;
use App\Models\Parentship;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Person;
use App\Models\Registrant;
use App\Models\RegistrantDocument;
use App\Models\Student;
use App\Models\User;
use App\Support\PersonProfile;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia;

/**
 * Staff who may open people and registrants but nothing else, so no payments, no editing and no account management.
 */
function personViewer(bool $registrants = true): User
{
    test()->seed(RolesAndPermissionsSeeder::class);

    return tap(User::factory()->create())->givePermissionTo(array_filter([
        Ability::AdminAccess->value,
        Ability::PersonView->value,
        $registrants ? Ability::RegistrantView->value : null,
    ]));
}

beforeEach(function () {
    $this->withoutVite();
    $this->admin = userWithRole(DefaultRole::Admin);
    $this->actingAs($this->admin);
});

test('the full profile and the record dates are on the page', function () {
    $person = Person::factory()->create([
        'nisn' => '0123456789', 'religion' => 'Islam', 'blood_type' => 'O', 'child_order' => 2, 'siblings_count' => 3,
        'address' => 'Jl. Melati 3', 'job' => 'Guru', 'monthly_income' => 'Rp3 juta - Rp5 juta', 'last_education' => 'S1 / D4',
    ]);

    $this->get(route('admin.people.show', $person))->assertInertia(fn (AssertableInertia $page) => $page
        ->component('admin/people/show')
        ->where('profile.nisn', '0123456789')
        ->where('profile.religion', 'Islam')
        ->where('profile.child_order', 2)
        ->where('profile.address', 'Jl. Melati 3')
        ->where('profile.job', 'Guru')
        ->where('profile.last_education', 'S1 / D4')
        ->where('profile.created_at', fn ($value) => filled($value)));
});

test('previous schools, student records, positions and siblings come along', function () {
    $registrant = Registrant::factory()->create(['status' => RegistrantStatus::Placed]);
    $child = $registrant->person;
    $mother = Person::factory()->create();
    Parentship::factory()->create(['person_id' => $child->id, 'related_person_id' => $mother->id]);
    $sibling = Person::factory()->create(['name' => 'Adik Kecil']);
    Parentship::factory()->create(['person_id' => $sibling->id, 'related_person_id' => $mother->id]);
    EducationHistory::factory()->create(['person_id' => $child->id, 'level' => EducationLevel::Elementary, 'school_name' => 'SDN 1', 'npsn' => '12345678']);
    Student::factory()->create(['person_id' => $child->id, 'registrant_id' => $registrant->id, 'nis' => 'S-001']);
    Employee::factory()->create(['person_id' => $mother->id, 'position' => 'Guru Matematika']);

    $this->get(route('admin.people.show', $child))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('profile.education.0.school_name', 'SDN 1')
        ->where('profile.education.0.level_label', 'SD/MI')
        ->where('profile.students.0.nis', 'S-001')
        ->where('profile.students.0.registrant_id', $registrant->id)
        ->where('profile.siblings.0.name', 'Adik Kecil'));

    $this->get(route('admin.people.show', $mother))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('profile.employees.0.position', 'Guru Matematika'));
});

describe('login account', function () {
    test('a parent account shows its email state without a link to staff management', function () {
        $parent = userWithRole(DefaultRole::User);
        $parent->forceFill(['email_verified_at' => null])->save();

        $this->get(route('admin.people.show', $parent->person))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('profile.account.email', $parent->email)
            ->where('profile.account.verified', false)
            ->where('profile.account.is_staff', false)
            ->where('profile.account.is_active', true)
            ->where('can.viewAccount', false));
    });

    test('a staff account links to account management only for staff who may manage accounts', function () {
        $staff = userWithRole(DefaultRole::Operator);

        $this->get(route('admin.people.show', $staff->person))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('profile.account.is_staff', true)
            ->where('profile.account.roles', ['operator'])
            ->where('can.viewAccount', true));

        $this->actingAs(personViewer())->get(route('admin.people.show', $staff->person))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('can.viewAccount', false));
    });

    test('a deactivated account says so and a person without one has none', function () {
        $staff = userWithRole(DefaultRole::Operator);
        $staff->syncRoles(DefaultRole::Inactive->value);

        expect(PersonProfile::account($staff->person)['is_active'])->toBeFalse();
        expect(PersonProfile::account(Person::factory()->create()))->toBeNull();
    });
});

describe('registrations and money', function () {
    beforeEach(function () {
        $this->parent = Person::factory()->create();
        $this->registrant = admissionRegistrant(RegistrantStatus::Announced, ['exam_scores' => [80, 80], 'kip_number' => '123']);
        Parentship::factory()->create(['person_id' => $this->registrant->person_id, 'related_person_id' => $this->parent->id]);
        $bill = Bill::factory()->create(['registrant_id' => $this->registrant->id, 'type' => BillType::Settlement]);
        $this->item = BillItem::factory()->create(['bill_id' => $bill->id, 'amount' => 1000000]);
        RegistrantDocument::factory()->create(['registrant_id' => $this->registrant->id, 'type' => DocumentType::Kip]);
    });

    test('each registration of the family says its type, money, exam result, files and scholarship hints', function () {
        $this->get(route('admin.people.show', $this->parent))->assertInertia(fn (AssertableInertia $page) => $page
            ->has('registrations', 1)
            ->where('registrations.0.id', $this->registrant->id)
            ->where('registrations.0.admission_type', 'Siswa baru')
            ->where('registrations.0.payment', 'unpaid')
            ->where('registrations.0.outstanding', 1000000)
            ->where('registrations.0.exam', 'Tuntas')
            ->where('registrations.0.documents_uploaded', 1)
            ->where('registrations.0.scholarship', ['Tidak mampu']));
    });

    test('payments the person made are listed with what the family still owes', function () {
        $payment = Payment::factory()->create(['payer_person_id' => $this->parent->id, 'amount' => 400000, 'status' => PaymentStatus::Verified]);
        PaymentAllocation::factory()->create(['payment_id' => $payment->id, 'bill_item_id' => $this->item->id, 'amount' => 400000]);
        Payment::factory()->create(['status' => PaymentStatus::Verified]);

        $this->get(route('admin.people.show', $this->parent))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('finance.owed', 600000)
            ->has('finance.payments', 1)
            ->where('finance.payments.0.id', $payment->id)
            ->where('finance.payments.0.receipt', true));
    });

    test('staff who may not see payments get no finance block, and staff who may not see registrations get none', function () {
        $this->actingAs(personViewer())->get(route('admin.people.show', $this->parent))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('finance', null)
            ->where('can.viewPayments', false)
            ->has('registrations', 1));

        $this->actingAs(personViewer(registrants: false))->get(route('admin.people.show', $this->parent))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('registrations', []));
    });
});

test('probable duplicates are offered to staff who can edit, and only to them', function () {
    $person = Person::factory()->create(['name' => 'Siti Aminah', 'phone' => '081234567890']);
    Person::factory()->create(['name' => 'Siti A.', 'phone' => '081234567890']);

    $this->get(route('admin.people.show', $person))->assertInertia(fn (AssertableInertia $page) => $page
        ->has('duplicates', 1)
        ->where('duplicates.0.reasons', ['Nomor HP sama']));

    $this->actingAs(personViewer())->get(route('admin.people.show', $person))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('duplicates', []));
});
