<?php

use App\Enums\BillType;
use App\Enums\DefaultRole;
use App\Enums\PaymentStatus;
use App\Enums\RegistrantStatus;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\ExamDay;
use App\Models\ExamSession;
use App\Models\Parentship;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Registrant;
use App\Models\Ruleset;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function userWithRole(DefaultRole $role): User
{
    test()->seed(RolesAndPermissionsSeeder::class);

    return tap(User::factory()->create())->assignRole($role->value);
}

/**
 * A parent with a registered child who has a bill of the given item amounts.
 *
 * @param  list<int>  $amounts
 * @return array{0: Registrant, 1: Bill}
 */
function billedChild(User $parent, array $amounts = [150000], BillType $type = BillType::Registration): array
{
    $registrant = Registrant::factory()->create();
    Parentship::factory()->create(['person_id' => $registrant->person_id, 'related_person_id' => $parent->person_id]);
    $bill = Bill::factory()->create(['registrant_id' => $registrant->id, 'type' => $type]);

    foreach ($amounts as $index => $amount) {
        BillItem::factory()->create(['bill_id' => $bill->id, 'name' => "Rincian {$index}", 'amount' => $amount, 'sort_order' => $index]);
    }

    return [$registrant, $bill->refresh()];
}

function verifiedPayment(BillItem $item, int $amount): Payment
{
    $payment = Payment::factory()->create(['amount' => $amount, 'status' => PaymentStatus::Verified]);
    PaymentAllocation::factory()->create(['payment_id' => $payment->id, 'bill_item_id' => $item->id, 'amount' => $amount]);

    return $payment;
}

/**
 * A registration with a ruleset copy that has a settlement bill and two exam aspects (KKM 70 and 60), and a
 * child whose profile is complete enough to be sent for review.
 *
 * @param  array<string, mixed>  $attributes
 */
function admissionRegistrant(RegistrantStatus $status = RegistrantStatus::Review, array $attributes = []): Registrant
{
    $ruleset = Ruleset::factory()->create();

    $registrant = Registrant::factory()->create([
        'status' => $status,
        'ruleset_id' => $ruleset->id,
        'ruleset_snapshot' => [
            'ruleset_id' => $ruleset->id,
            'name' => $ruleset->name,
            'bills' => [['type' => 'settlement', 'items' => [['name' => 'Uang pangkal', 'amount' => 1000000], ['name' => 'Seragam', 'amount' => 500000]]]],
            'exam_aspects' => [['name' => 'Tes tulis', 'passing_score' => 70], ['name' => 'Wawancara', 'passing_score' => 60]],
        ],
        'formal_photo_path' => 'registrants/formal-photos/pas.jpg',
        ...$attributes,
    ]);

    $registrant->person->update(['religion' => 'Islam', 'address' => 'Jl. Melati 3', 'family_card_number' => '3201234567890123']);
    Parentship::factory()->primaryContact()->create(['person_id' => $registrant->person_id]);

    return $registrant;
}

function examSessionFor(Registrant $registrant, int $quota = 30): ExamSession
{
    return ExamSession::factory()->create([
        'quota' => $quota,
        'exam_day_id' => ExamDay::factory()->create(['ruleset_id' => $registrant->ruleset_id])->id,
    ]);
}
