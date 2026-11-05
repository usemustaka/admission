<?php

namespace Database\Seeders;

use App\Actions\Admission\AcceptRegistrant;
use App\Actions\Admission\AnnounceRegistrant;
use App\Actions\Admission\ChooseExamSession;
use App\Actions\Admission\PlaceRegistrant;
use App\Actions\Admission\RequestRevision;
use App\Actions\Admission\SaveExamScores;
use App\Actions\Admission\SaveRuleset;
use App\Actions\Admission\SubmitRegistrant;
use App\Actions\Admission\SyncBills;
use App\Actions\Admission\SyncExamAspects;
use App\Actions\Admission\SyncExamSchedule;
use App\Actions\Billing\CreatePayment;
use App\Actions\Billing\PaymentData;
use App\Actions\People\LinkParent;
use App\Actions\Portal\RegisterChild;
use App\Actions\Portal\RegisterChildData;
use App\Enums\BillType;
use App\Enums\DefaultRole;
use App\Enums\Gender;
use App\Enums\ParentRelation;
use App\Enums\PaymentMethod;
use App\Models\ExamSession;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Registrant;
use App\Models\Ruleset;
use App\Models\User;
use App\Models\Wave;
use App\Models\Year;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Sample data that covers the situations the admission flow can be in, for demos and manual testing. It uses
 * the same actions as the application, so every registration is in a state the flow can really produce.
 *
 * Run it on purpose with `php artisan db:seed --class=DemoSeeder`. It never runs in production, and running
 * it again changes nothing once the demo staff account exists.
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'password';

    private User $admin;

    private Year $year;

    private int $sequence = 0;

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoSeeder tidak boleh dijalankan di produksi.');
        }

        if (User::query()->where('email', 'admin@demo.test')->exists()) {
            $this->command?->info('Data demo sudah ada, tidak ada yang diubah.');

            return;
        }

        $this->call(SystemSeeder::class);

        DB::transaction(function (): void {
            $this->admin = $this->makeUser('Admin Demo', 'admin@demo.test', DefaultRole::Admin);
            $this->makeUser('Operator Demo', 'operator@demo.test', DefaultRole::Operator);
            $this->year = Year::query()->where('is_active', true)->firstOrFail();

            [$wave1] = $this->makeWaves();
            $this->makeRulesets();

            $smp = $this->organization('smp');
            $sma = $this->organization('sma');
            $pesantren = $this->organization('pesantren');

            $this->twoChildrenWaitingForPayment($smp, $wave1);
            $this->partiallyPaid($smp, $wave1);
            $this->inReviewInFullSession($smp, $wave1);
            $this->needsRevision($sma, $wave1);
            $this->validatedWithPartialSettlement($sma, $wave1);
            $this->announcedAndPaid($smp, $wave1);
            $this->placedWithoutRegistrationBill($pesantren, $wave1);
            $this->draftWithoutRegistrationBill($pesantren, $wave1);
            $this->nikWithDifferentName($smp, $wave1);
        });
    }

    private function makeUser(string $name, string $email, ?DefaultRole $role = null, ?string $phone = null): User
    {
        $person = Person::query()->create(['name' => $name, 'email' => $email, 'phone' => $phone ?? '0812000000'.str_pad((string) ++$this->sequence, 2, '0', STR_PAD_LEFT)]);
        $user = User::query()->create(['person_id' => $person->id, 'email' => $email, 'password' => self::PASSWORD]);
        $user->forceFill(['email_verified_at' => now()])->save();

        if ($role !== null) {
            $user->assignRole($role->value);
        }

        return $user;
    }

    /**
     * @return array{0: Wave, 1: Wave}
     */
    private function makeWaves(): array
    {
        $start = now()->startOfYear();

        return [
            Wave::query()->create(['name' => 'Gelombang 1', 'starts_on' => $start, 'ends_on' => $start->copy()->addMonths(6), 'is_open' => true]),
            Wave::query()->create(['name' => 'Gelombang 2', 'starts_on' => $start->copy()->addMonths(6), 'ends_on' => $start->copy()->addMonths(12), 'is_open' => true]),
        ];
    }

    private function organization(string $code): Organization
    {
        return Organization::query()->where('code', $code)->firstOrFail();
    }

    /**
     * Four rulesets in priority order. Two of them match a boy at SMP, and the boarding school has no
     * registration bill.
     */
    private function makeRulesets(): void
    {
        $date = now()->addWeek()->toDateString();
        $sessions = fn (int $firstQuota): array => [[
            'date' => $date,
            'sessions' => [
                ['label' => 'Sesi 1', 'start_time' => '08:00', 'end_time' => '10:00', 'quota' => $firstQuota],
                ['label' => 'Sesi 2', 'start_time' => '10:00', 'end_time' => '12:00', 'quota' => 20],
            ],
        ]];

        $definitions = [
            ['SMP putra', 'smp', Gender::Male, [BillType::Registration->value => [['name' => 'Formulir', 'amount' => 100000]], BillType::Settlement->value => [['name' => 'Uang pangkal', 'amount' => 2000000], ['name' => 'Seragam', 'amount' => 500000]]], [['name' => 'Tes tulis', 'passing_score' => 70], ['name' => 'Wawancara', 'passing_score' => 60]], 1],
            ['SMP umum', 'smp', null, [BillType::Registration->value => [['name' => 'Formulir', 'amount' => 100000], ['name' => 'Tes seleksi', 'amount' => 100000]], BillType::Settlement->value => [['name' => 'Uang pangkal', 'amount' => 1800000]]], [['name' => 'Tes tulis', 'passing_score' => 65]], 20],
            ['SMA', 'sma', null, [BillType::Registration->value => [['name' => 'Formulir', 'amount' => 150000]], BillType::Settlement->value => [['name' => 'Uang pangkal', 'amount' => 2500000]]], [['name' => 'Tes tulis', 'passing_score' => 70]], 20],
            ['Pesantren', 'pesantren', null, [BillType::Settlement->value => [['name' => 'Uang pangkal', 'amount' => 1500000]]], [['name' => 'Tes baca Al-Qur\'an', 'passing_score' => 60]], 20],
        ];

        $scheduled = [];

        foreach ($definitions as [$name, $code, $gender, $bills, $aspects, $quota]) {
            /** @var Ruleset $ruleset */
            $ruleset = app(SaveRuleset::class)->handle(null, $name, null, $this->organization($code)->id, $gender, true);

            app(SyncBills::class)->handle($ruleset, $bills);
            app(SyncExamAspects::class)->handle($ruleset, $aspects);

            if (! isset($scheduled[$code])) {
                $scheduled[$code] = true;
                app(SyncExamSchedule::class)->handle($this->organization($code)->id, null, $sessions($quota));
            }
        }
    }

    private function register(User $parent, string $childName, Gender $gender, Organization $organization, Wave $wave, ParentRelation $relation = ParentRelation::Mother, ?string $nik = null): Registrant
    {
        return app(RegisterChild::class)->handle($parent, new RegisterChildData(
            name: $childName,
            nik: $nik ?? '32010101010100'.str_pad((string) ++$this->sequence, 2, '0', STR_PAD_LEFT),
            gender: $gender,
            birthDate: now()->subYears(12)->toDateString(),
            organizationId: $organization->id,
            waveId: $wave->id,
            relation: $relation,
            kind: null,
        ));
    }

    /**
     * Fill in what the flow asks for before the data can be sent: profile and exam session.
     */
    private function complete(Registrant $registrant, int $sessionNumber = 2): Registrant
    {
        $registrant->person->update([
            'birth_place' => 'Bandung',
            'religion' => 'Islam',
            'address' => 'Jl. Melati No. '.$registrant->id,
            'family_card_number' => '3201020202020'.str_pad((string) $registrant->id, 3, '0', STR_PAD_LEFT),
        ]);

        $session = ExamSession::query()
            ->whereHas('day', fn ($days) => $days->forRegistrant($registrant))
            ->orderBy('start_time')
            ->skip($sessionNumber - 1)
            ->first();

        if ($session !== null) {
            app(ChooseExamSession::class)->handle($registrant, $session);
        }

        return $registrant->refresh();
    }

    /**
     * Record a verified payment for a bill of the registration: the whole bill, or only the first `$amount` rupiah of it.
     */
    private function pay(Registrant $registrant, BillType $type, ?int $amount = null, bool $verified = true): void
    {
        $bill = $registrant->bills()->where('type', $type)->with('items')->first();

        if ($bill === null) {
            return;
        }

        $items = [];
        $left = $amount ?? PHP_INT_MAX;

        foreach ($bill->items as $item) {
            $part = min($item->amount, $left);

            if ($part > 0) {
                $items[] = ['bill_item_id' => $item->id, 'amount' => $part];
                $left -= $part;
            }
        }

        app(CreatePayment::class)->handle(new PaymentData(
            payerPersonId: $registrant->registered_by_person_id,
            paidOn: now()->toDateString(),
            method: PaymentMethod::Transfer,
            items: $items,
            createdBy: $this->admin,
        ), verified: $verified);
    }

    private function parentOf(Registrant $registrant): User
    {
        return User::query()->where('person_id', $registrant->registered_by_person_id)->firstOrFail();
    }

    private function review(Registrant $registrant): Registrant
    {
        $registrant = $this->complete($registrant);
        $this->pay($registrant, BillType::Registration);
        app(SubmitRegistrant::class)->handle($registrant->refresh(), $this->parentOf($registrant));

        return $registrant->refresh();
    }

    /**
     * One parent, two children, and a single transfer for both that waits for the committee to verify it.
     */
    private function twoChildrenWaitingForPayment(Organization $smp, Wave $wave): void
    {
        $parent = $this->makeUser('Rina Wulandari', 'rina@demo.test');
        $boy = $this->register($parent, 'Rizky Pratama', Gender::Male, $smp, $wave);
        $girl = $this->register($parent, 'Salsa Pratama', Gender::Female, $smp, $wave);

        $items = [];

        foreach ([$boy, $girl] as $registrant) {
            foreach ($registrant->bills()->where('type', BillType::Registration)->firstOrFail()->items as $item) {
                $items[] = ['bill_item_id' => $item->id, 'amount' => $item->amount];
            }
        }

        app(CreatePayment::class)->handle(new PaymentData(
            payerPersonId: $parent->person_id,
            paidOn: now()->toDateString(),
            method: PaymentMethod::Transfer,
            items: $items,
            createdBy: $parent,
            note: 'Transfer untuk Rizky dan Salsa sekaligus',
        ));
    }

    private function partiallyPaid(Organization $smp, Wave $wave): void
    {
        $parent = $this->makeUser('Yuni Astuti', 'yuni@demo.test');
        $registrant = $this->register($parent, 'Nadia Putri', Gender::Female, $smp, $wave);

        $this->pay($registrant, BillType::Registration, 100000);
    }

    private function inReviewInFullSession(Organization $smp, Wave $wave): void
    {
        $parent = $this->makeUser('Budi Santoso', 'budi@demo.test');
        $registrant = $this->register($parent, 'Budi Santoso Jr.', Gender::Male, $smp, $wave, ParentRelation::Father);
        $this->complete($registrant, sessionNumber: 1);
        $registrant->refresh();
        $this->pay($registrant, BillType::Registration);
        app(SubmitRegistrant::class)->handle($registrant->refresh(), $parent);
    }

    private function needsRevision(Organization $sma, Wave $wave): void
    {
        $parent = $this->makeUser('Sari Handayani', 'sari@demo.test');
        $registrant = $this->review($this->register($parent, 'Sinta Handayani', Gender::Female, $sma, $wave));

        app(RequestRevision::class)->handle($registrant, $this->admin, 'Pas foto kurang jelas dan alamat belum lengkap. Tolong diperbaiki ya.');
    }

    private function validatedWithPartialSettlement(Organization $sma, Wave $wave): void
    {
        $parent = $this->makeUser('Andi Wijaya', 'andi@demo.test');
        $registrant = $this->review($this->register($parent, 'Arga Wijaya', Gender::Male, $sma, $wave, ParentRelation::Father));

        app(AcceptRegistrant::class)->handle($registrant, $this->admin);
        $this->pay($registrant->refresh(), BillType::Settlement, 1000000);
    }

    private function announcedAndPaid(Organization $smp, Wave $wave): void
    {
        $parent = $this->makeUser('Dewi Anggraini', 'dewi@demo.test');
        $registrant = $this->review($this->register($parent, 'Dinda Anggraini', Gender::Female, $smp, $wave));

        app(AcceptRegistrant::class)->handle($registrant, $this->admin);
        $this->pay($registrant->refresh(), BillType::Settlement);
        app(SaveExamScores::class)->handle($registrant->refresh(), [88]);
        app(AnnounceRegistrant::class)->handle($registrant->refresh(), $this->admin);
    }

    private function placedWithoutRegistrationBill(Organization $pesantren, Wave $wave): void
    {
        $parent = $this->makeUser('Hasan Basri', 'hasan@demo.test');
        $registrant = $this->review($this->register($parent, 'Hafiz Basri', Gender::Male, $pesantren, $wave, ParentRelation::Father));

        app(AcceptRegistrant::class)->handle($registrant, $this->admin);
        $this->pay($registrant->refresh(), BillType::Settlement);
        app(SaveExamScores::class)->handle($registrant->refresh(), [75]);
        app(AnnounceRegistrant::class)->handle($registrant->refresh(), $this->admin);
        app(PlaceRegistrant::class)->handle($registrant->refresh(), $this->admin, $this->year, 'PS-0001');
    }

    private function draftWithoutRegistrationBill(Organization $pesantren, Wave $wave): void
    {
        $parent = $this->makeUser('Lina Marlina', 'lina@demo.test');
        $this->register($parent, 'Fahri Marlina', Gender::Male, $pesantren, $wave);
    }

    /**
     * The NIK is already on record with another name, so the committee has to confirm before the parent is linked.
     */
    private function nikWithDifferentName(Organization $smp, Wave $wave): void
    {
        $known = Person::query()->create(['nik' => '3201999900001111', 'name' => 'Puspa Prastuti', 'gender' => Gender::Female, 'birth_place' => 'Jambi']);
        $realParent = Person::query()->create(['name' => 'Joko Prasetyo', 'phone' => '081200009999']);
        app(LinkParent::class)->handle($known, $realParent, ParentRelation::Father);

        $parent = $this->makeUser('Wati Susilowati', 'wati@demo.test');
        $this->register($parent, 'Puspa P.', Gender::Female, $smp, $wave, ParentRelation::Guardian, '3201999900001111');
    }
}
