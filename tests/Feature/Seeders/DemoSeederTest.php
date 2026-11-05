<?php

use App\Enums\BillStatus;
use App\Enums\BillType;
use App\Enums\PaymentStatus;
use App\Enums\RegistrantStatus;
use App\Models\Bill;
use App\Models\ExamSession;
use App\Models\Payment;
use App\Models\Person;
use App\Models\Registrant;
use App\Models\Ruleset;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

test('the demo data covers every stage of the flow', function () {
    $this->seed(DemoSeeder::class);

    $byStatus = Registrant::query()->get()->countBy(fn (Registrant $registrant) => $registrant->status->value);

    expect($byStatus->get('draft'))->toBeGreaterThanOrEqual(2)
        ->and(array_keys($byStatus->all()))->toContain('review', 'revision', 'validated', 'announced', 'placed')
        ->and(Student::query()->count())->toBe(1)
        ->and(Student::query()->first()->nis)->toBe('PS-0001');
});

test('it has paid, partly paid and unpaid bills', function () {
    $this->seed(DemoSeeder::class);

    $statuses = Bill::query()->get()->map(fn (Bill $bill) => $bill->loadTotals()->status())->unique()->values()->all();

    expect($statuses)->toContain(BillStatus::Paid, BillStatus::Partial, BillStatus::Unpaid);
});

test('a parent with two children has one payment waiting for both', function () {
    $this->seed(DemoSeeder::class);

    $rina = User::query()->where('email', 'rina@demo.test')->firstOrFail();
    $payment = Payment::query()->where('payer_person_id', $rina->person_id)->firstOrFail();

    expect(Registrant::query()->forParent($rina)->count())->toBe(2)
        ->and($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->allocations->map(fn ($allocation) => $allocation->billItem->bill->registrant_id)->unique())->toHaveCount(2);
});

test('it has a flagged NIK, a revision, a full session, more than one matching ruleset and an organization without registration bill', function () {
    $this->seed(DemoSeeder::class);

    expect(Registrant::query()->where('needs_identity_review', true)->count())->toBe(1)
        ->and(Registrant::query()->where('status', RegistrantStatus::Revision)->first()->statusChanges->last()->note)->not->toBeEmpty();

    $full = ExamSession::query()->get()->filter(fn (ExamSession $session) => Registrant::query()->where('exam_session_id', $session->id)->count() >= $session->quota);

    expect($full)->not->toBeEmpty();

    $boyAtSmp = Ruleset::query()->where('is_active', true)->whereHas('organization', fn ($query) => $query->where('code', 'smp'))->whereIn('gender', ['male'])->count()
        + Ruleset::query()->where('is_active', true)->whereHas('organization', fn ($query) => $query->where('code', 'smp'))->whereNull('gender')->count();

    expect($boyAtSmp)->toBeGreaterThan(1);

    $withoutRegistrationBill = Registrant::query()->get()->filter(fn (Registrant $registrant) => ! $registrant->hasBill(BillType::Registration));

    expect($withoutRegistrationBill)->not->toBeEmpty()
        ->and($withoutRegistrationBill->every(fn (Registrant $registrant) => $registrant->bills()->where('type', BillType::Registration)->doesntExist()))->toBeTrue();
});

test('running it again changes nothing', function () {
    $this->seed(DemoSeeder::class);

    $counts = fn () => [Person::query()->count(), User::query()->count(), Registrant::query()->count(), Bill::query()->count(), Payment::query()->count(), Student::query()->count()];
    $before = $counts();

    $this->seed(DemoSeeder::class);

    expect($counts())->toBe($before);
});

test('the staff of the demo can sign in', function () {
    $this->seed(DemoSeeder::class);

    $this->post(route('login'), ['email' => 'admin@demo.test', 'password' => DemoSeeder::PASSWORD])->assertRedirect();
    $this->assertAuthenticated();

    expect(User::query()->where('email', 'admin@demo.test')->first()->can('admission::registrant.manage'))->toBeTrue()
        ->and(User::query()->where('email', 'operator@demo.test')->exists())->toBeTrue();
});

test('it refuses to run in production', function () {
    app()->detectEnvironment(fn () => 'production');

    expect(fn () => app(DemoSeeder::class)->run())->toThrow(RuntimeException::class);
    expect(User::query()->count())->toBe(0);
});
