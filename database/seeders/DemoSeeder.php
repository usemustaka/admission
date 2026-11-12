<?php

namespace Database\Seeders;

use App\Actions\Admission\AcceptRegistrant;
use App\Actions\Admission\AnnounceRegistrant;
use App\Actions\Admission\BypassRegistrantStatus;
use App\Actions\Admission\CancelPlacement;
use App\Actions\Admission\ChooseExamSession;
use App\Actions\Admission\GrantScholarship;
use App\Actions\Admission\PlaceRegistrant;
use App\Actions\Admission\RequestRevision;
use App\Actions\Admission\ResolveIdentityReview;
use App\Actions\Admission\SaveExamScores;
use App\Actions\Admission\SaveRuleset;
use App\Actions\Admission\SubmitRegistrant;
use App\Actions\Admission\SyncBills;
use App\Actions\Admission\SyncExamAspects;
use App\Actions\Admission\SyncExamSchedule;
use App\Actions\Admission\SyncRulesetDocuments;
use App\Actions\Billing\CreatePayment;
use App\Actions\Billing\PaymentData;
use App\Actions\Billing\RejectPayment;
use App\Actions\Billing\ReopenPayment;
use App\Actions\Billing\VoidPayment;
use App\Actions\Notes\NoteData;
use App\Actions\Notes\SaveNote;
use App\Actions\People\LinkParent;
use App\Actions\People\SetPrimaryContact;
use App\Actions\Portal\RegisterChild;
use App\Actions\Portal\RegisterChildData;
use App\Actions\Portal\SaveRegistrantData;
use App\Actions\Users\DeactivateUser;
use App\Enums\AdmissionType;
use App\Enums\BillType;
use App\Enums\DefaultRole;
use App\Enums\DocumentType;
use App\Enums\Gender;
use App\Enums\NoteUrgency;
use App\Enums\NoteVisibility;
use App\Enums\OrganizationCategory;
use App\Enums\ParentKind;
use App\Enums\ParentRelation;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RegistrantStatus;
use App\Enums\ScholarshipKind;
use App\Models\ExamSession;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Person;
use App\Models\Registrant;
use App\Models\RegistrantDocument;
use App\Models\Ruleset;
use App\Models\Setting;
use App\Models\User;
use App\Models\Wave;
use App\Models\Year;
use App\Support\DocumentKind;
use App\Support\EmailVerification;
use App\Support\RegistrantDocuments;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Spatie\Permission\Models\Role;

/**
 * Sample data that covers the situations the admission flow can be in, for demos and manual testing. It uses
 * the same actions as the application, so every registration is in a state the flow can really produce.
 *
 * Staff (password `password`): admin@demo.test, super@demo.test, operator@demo.test, tes@demo.test (panitia tes),
 * keuangan@demo.test (panitia keuangan) and staf-lama@demo.test (deactivated). Parents sign in with their own
 * email, for example rina@demo.test; tono@demo.test has not verified the email yet.
 *
 * Run it on purpose with `php artisan db:seed --class=DemoSeeder`. It never runs in production, and running
 * it again changes nothing once the demo staff account exists.
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'password';

    /** A one-page stand-in for the files parents upload. */
    private const PLACEHOLDER_PDF = "%PDF-1.1\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 100]>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF";

    private User $admin;

    private User $operator;

    private Year $year;

    private Wave $wave2;

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
            $this->makeSettings();
            $this->makeStaff();
            $this->year = Year::query()->where('is_active', true)->firstOrFail();

            [$wave1, $this->wave2] = $this->makeWaves();
            Organization::query()->firstOrCreate(['code' => 'mi'], ['name' => 'MI', 'category' => OrganizationCategory::School, 'sort_order' => 4]);
            $this->makeRulesets();

            $smp = $this->organization('smp');
            $sma = $this->organization('sma');
            $pesantren = $this->organization('pesantren');
            $mi = $this->organization('mi');

            $this->twoChildrenWaitingForPayment($smp, $wave1);
            $this->partiallyPaid($smp, $wave1);
            $this->inReviewInFullSession($smp, $wave1);
            $this->needsRevision($sma, $wave1);
            $this->validatedWithPartialSettlement($sma, $wave1);
            $this->announcedAndPaid($smp, $wave1);
            $this->placedWithoutRegistrationBill($pesantren, $wave1);
            $this->draftWithoutRegistrationBill($pesantren, $wave1);
            $this->nikWithDifferentName($smp, $wave1);

            $this->siblingsInTwoWaves($smp, $sma, $wave1);
            $this->transferWithFullFamilyAndHardshipEvidence($sma, $wave1);
            $this->incompleteDocuments($sma, $wave1);
            $this->scholarshipGrantedAndFullyPaid($smp, $wave1);
            $this->announcedNotPassed($sma, $wave1);
            $this->partialScores($smp, $wave1);
            $this->rejectedAndReopenedPayments($smp, $wave1);
            $this->bypassedStatus($smp, $wave1);
            $this->placedAndCancelledPlacement($smp, $sma, $wave1);
            $this->madrasahWithoutExamAspects($mi, $wave1);
            $this->resolvedNikCheck($smp, $wave1);
            $this->overriddenFullSession($smp, $wave1);
            $this->unverifiedParent($pesantren, $wave1);
            $this->pesantrenHardshipByKipNumber($pesantren, $wave1);
            $this->notes();
        });
    }

    /**
     * School identity, contact and bank account: they fill the letterhead of printed papers and the payment page.
     * Email verification is not required, so the unverified demo parent can still sign in and sees the reminder.
     */
    private function makeSettings(): void
    {
        foreach ([
            'contact.address' => 'Jl. Pendidikan No. 12, Bandung 40123',
            'contact.phone' => '022 1234567',
            'contact.whatsapp' => '6281200000000',
            'contact.email' => 'psb@demo.test',
            'contact.website' => 'https://demo.test',
            'payment.bank_name' => 'Bank Syariah Contoh',
            'payment.account_number' => '7001234567',
            'payment.account_holder' => 'Yayasan Pendidikan',
            'payment.instructions' => 'Cantumkan nama anak pada berita transfer.',
            EmailVerification::SETTING_KEY => false,
        ] as $key => $value) {
            Setting::put($key, $value);
        }
    }

    private function makeStaff(): void
    {
        $this->admin = $this->makeUser('Admin Demo', 'admin@demo.test', DefaultRole::Admin);
        $this->operator = $this->makeUser('Operator Demo', 'operator@demo.test', DefaultRole::Operator);
        $this->makeUser('Super Admin Demo', 'super@demo.test', DefaultRole::SuperAdmin);
        $this->makeUser('Panitia Tes Demo', 'tes@demo.test', DefaultRole::TestCommittee);
        $this->makeUser('Panitia Keuangan Demo', 'keuangan@demo.test', DefaultRole::FinanceCommittee);

        app(DeactivateUser::class)->handle($this->makeUser('Staf Lama Demo', 'staf-lama@demo.test', DefaultRole::Operator));
    }

    private function makeUser(string $name, string $email, ?DefaultRole $role = null, ?string $phone = null, bool $verified = true): User
    {
        $person = Person::query()->create(['name' => $name, 'email' => $email, 'phone' => $phone ?? sprintf('0812%08d', ++$this->sequence)]);
        $user = User::query()->create(['person_id' => $person->id, 'email' => $email, 'password' => self::PASSWORD]);
        $user->forceFill(['email_verified_at' => $verified ? now() : null])->save();

        if ($role !== null) {
            $user->assignRole($role->value);
        }

        return $user;
    }

    /**
     * @return array{0: Wave, 1: Wave, 2: Wave}
     */
    private function makeWaves(): array
    {
        $start = now()->startOfYear();

        return [
            Wave::query()->create(['name' => 'Gelombang 1', 'starts_on' => $start, 'ends_on' => $start->copy()->addMonths(6), 'is_open' => true]),
            Wave::query()->create(['name' => 'Gelombang 2', 'starts_on' => $start->copy()->addMonths(6), 'ends_on' => $start->copy()->addMonths(12), 'is_open' => true]),
            Wave::query()->create(['name' => 'Gelombang 3', 'starts_on' => $start->copy()->addMonths(12), 'ends_on' => $start->copy()->addMonths(15), 'is_open' => false]),
        ];
    }

    private function organization(string $code): Organization
    {
        return Organization::query()->where('code', $code)->firstOrFail();
    }

    /**
     * Five rulesets in priority order. Two of them match a boy at SMP, the boarding school has no registration bill
     * and asks only for the family card, the madrasah has no exam aspects and asks for no files, and the high school
     * also requires the diploma.
     */
    private function makeRulesets(): void
    {
        $date = now()->addWeek()->toDateString();
        $sessions = fn (int $firstQuota, string $day = ''): array => [[
            'date' => $day === '' ? $date : $day,
            'sessions' => [
                ['label' => 'Sesi 1', 'start_time' => '08:00', 'end_time' => '10:00', 'quota' => $firstQuota],
                ['label' => 'Sesi 2', 'start_time' => '10:00', 'end_time' => '12:00', 'quota' => 20],
            ],
        ]];

        $core = ['family-card' => 'required', 'birth-certificate' => 'required', 'report-card' => 'required', 'diploma' => 'optional'];

        $definitions = [
            ['SMP putra', 'smp', Gender::Male, [BillType::Registration->value => [['name' => 'Formulir', 'amount' => 100000]], BillType::Settlement->value => [['name' => 'Uang pangkal', 'amount' => 2000000], ['name' => 'Seragam', 'amount' => 500000]]], [['name' => 'Tes tulis', 'passing_score' => 70], ['name' => 'Wawancara', 'passing_score' => 60]], 1, $core],
            ['SMP umum', 'smp', null, [BillType::Registration->value => [['name' => 'Formulir', 'amount' => 100000], ['name' => 'Tes seleksi', 'amount' => 100000]], BillType::Settlement->value => [['name' => 'Uang pangkal', 'amount' => 1800000]]], [['name' => 'Tes tulis', 'passing_score' => 65]], 20, $core],
            ['SMA', 'sma', null, [BillType::Registration->value => [['name' => 'Formulir', 'amount' => 150000]], BillType::Settlement->value => [['name' => 'Uang pangkal', 'amount' => 2500000]]], [['name' => 'Tes tulis', 'passing_score' => 70]], 20, [...$core, 'diploma' => 'required']],
            ['Pesantren', 'pesantren', null, [BillType::Settlement->value => [['name' => 'Uang pangkal', 'amount' => 1500000]]], [
                ['name' => 'Literasi', 'passing_score' => 60],
                ['name' => 'Numerasi', 'passing_score' => 60],
                ['name' => 'Baca Al-Qur\'an (Yanbua)', 'scale' => 'good-only', 'passing_score' => 100],
                ['name' => 'Hafalan surat pendek', 'scale' => 'memorized-fair', 'passing_score' => 50],
                ['name' => 'Menulis Arab', 'scale' => 'able-only', 'passing_score' => 100],
                ['name' => 'Membaca pegon', 'scale' => 'good-fair', 'passing_score' => 50],
            ], 20, ['family-card' => 'required', 'birth-certificate' => 'none', 'report-card' => 'none', 'diploma' => 'none']],
            ['MI', 'mi', null, [BillType::Settlement->value => [['name' => 'Uang gedung', 'amount' => 1000000]]], [], null, ['family-card' => 'none', 'birth-certificate' => 'none', 'report-card' => 'none', 'diploma' => 'none']],
        ];

        $scheduled = [];

        foreach ($definitions as [$name, $code, $gender, $bills, $aspects, $quota, $documents]) {
            /** @var Ruleset $ruleset */
            $ruleset = app(SaveRuleset::class)->handle(null, $name, null, $this->organization($code)->id, $gender, true);

            app(SyncBills::class)->handle($ruleset, $bills);
            app(SyncExamAspects::class)->handle($ruleset, $aspects);
            app(SyncRulesetDocuments::class)->handle($ruleset, $documents);

            if ($quota !== null && ! isset($scheduled[$code])) {
                $scheduled[$code] = true;
                app(SyncExamSchedule::class)->handle($this->organization($code)->id, null, $sessions($quota));
            }
        }

        app(SyncExamSchedule::class)->handle($this->organization('sma')->id, $this->wave2->id, $sessions(15, now()->addWeeks(3)->toDateString()));
    }

    private function register(User $parent, string $childName, Gender $gender, Organization $organization, Wave $wave, ParentRelation $relation = ParentRelation::Mother, ?string $nik = null, ?AdmissionType $type = null, ?ParentKind $kind = null): Registrant
    {
        $registrant = app(RegisterChild::class)->handle($parent, new RegisterChildData(
            name: $childName,
            nik: $nik ?? sprintf('3201010101%06d', ++$this->sequence),
            gender: $gender,
            birthDate: now()->subYears(12)->toDateString(),
            organizationId: $organization->id,
            waveId: $wave->id,
            relation: $relation,
            kind: $kind,
            admissionType: $type ?? AdmissionType::New,
        ));

        return $registrant;
    }

    /**
     * Fill in the data form like a parent would: the child's profile, the registering parent's details and the
     * schools the child attended. `$parents` (keyed by relation) and `$child` replace the defaults.
     *
     * @param  array<string, array<string, mixed>>  $parents
     * @param  list<array<string, mixed>>|null  $education
     * @param  array<string, mixed>  $child
     */
    private function fillProfile(Registrant $registrant, array $parents = [], ?array $education = null, ?AdmissionType $type = null, array $child = []): void
    {
        $parent = $this->parentOf($registrant);
        $organization = $registrant->organization->code;

        $education ??= match ($organization) {
            'sma' => [$this->school('elementary', 'SD Negeri 1 Bandung', '20219001'), $this->school('junior-high', 'SMP Negeri 5 Bandung', '20219005')],
            'mi' => [$this->school('kindergarten', 'TK Melati', '69712345')],
            default => [$this->school('elementary', 'SD Negeri 1 Bandung', '20219001')],
        };

        app(SaveRegistrantData::class)->handle(
            $registrant,
            [
                'birth_place' => 'Bandung',
                'religion' => 'Islam',
                'address' => 'Jl. Melati No. '.$registrant->id,
                'family_card_number' => '3201020202020'.str_pad((string) $registrant->id, 3, '0', STR_PAD_LEFT),
                'nisn' => sprintf('00%08d', $registrant->id),
                'blood_type' => ['A', 'B', 'AB', 'O'][$registrant->id % 4],
                'child_order' => 1 + $registrant->id % 3,
                'siblings_count' => 2 + $registrant->id % 3,
                ...$child,
            ],
            $parents + [
                $registrant->registered_as_relation->value => $this->parentDetails($parent->name, ['phone' => $parent->person->phone]),
                ...$this->otherParent($registrant),
            ],
            education: $education,
            admissionType: $type,
        );
    }

    /**
     * The father or mother who did not register the child, so both parents are on record like the rulesets ask for.
     * A guardian who registered leaves both blank on purpose.
     *
     * @return array<string, array<string, mixed>>
     */
    private function otherParent(Registrant $registrant): array
    {
        $first = explode(' ', $registrant->person->name)[0];

        return match ($registrant->registered_as_relation) {
            ParentRelation::Father => ['mother' => $this->parentDetails("Ibu {$first}", ['job' => 'Ibu rumah tangga'])],
            ParentRelation::Mother => ['father' => $this->parentDetails("Ayah {$first}")],
            default => [],
        };
    }

    /**
     * @return array{level: string, school_name: string, npsn: string|null, school_address: string}
     */
    private function school(string $level, string $name, ?string $npsn): array
    {
        return ['level' => $level, 'school_name' => $name, 'npsn' => $npsn, 'school_address' => 'Jl. Merdeka, Bandung'];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function parentDetails(string $name, array $overrides = []): array
    {
        return [
            'name' => $name,
            'nik' => sprintf('3201030303%06d', ++$this->sequence),
            'phone' => sprintf('0812%08d', ++$this->sequence),
            'job' => 'Karyawan swasta',
            'last_education' => 'S1 / D4',
            'monthly_income' => 'Rp3 juta - Rp5 juta',
            'address' => 'Jl. Melati, Bandung',
            'is_alive' => true,
            'kind' => 'biological',
            ...array_filter($overrides, fn (mixed $value): bool => $value !== null),
        ];
    }

    /**
     * Fill in what the flow asks for before the data can be sent: profile, exam session and required files. The
     * session is skipped when the registration has no exam schedule.
     *
     * @param  array<string, mixed>  $profile  options for `fillProfile`: parents, education, type, child
     */
    private function complete(Registrant $registrant, int $sessionNumber = 2, array $profile = []): Registrant
    {
        $this->fillProfile($registrant, $profile['parents'] ?? [], $profile['education'] ?? null, $profile['type'] ?? null, $profile['child'] ?? []);

        $session = ExamSession::query()
            ->whereHas('day', fn ($days) => $days->forRegistrant($registrant))
            ->orderBy('start_time')
            ->skip($sessionNumber - 1)
            ->first();

        if ($session !== null) {
            app(ChooseExamSession::class)->handle($registrant, $session);
        }

        $this->attachRequiredDocuments($registrant);

        return $registrant->refresh();
    }

    /**
     * Upload a placeholder file for every document the registration's ruleset requires, so the demo registrations can
     * be sent. The files are one-page stand-ins, not real documents.
     */
    private function attachRequiredDocuments(Registrant $registrant): void
    {
        foreach (RegistrantDocuments::requirements($registrant) as $requirement) {
            if ($requirement['required']) {
                $this->attachDocument($registrant, $requirement['kind']->key, range(1, $requirement['kind']->maxFiles));
            }
        }
    }

    /**
     * @param  list<int>  $slots
     */
    private function attachDocument(Registrant $registrant, DocumentType|string $type, array $slots = [1]): void
    {
        $kind = DocumentKind::resolve($registrant, $type instanceof DocumentType ? $type->value : $type) ?? DocumentKind::builtin(DocumentType::OtherSupport);
        $key = $kind->key;
        foreach ($slots as $slot) {
            $path = "registrant-documents/{$registrant->id}/demo-{$key}-{$slot}.pdf";
            Storage::disk(RegistrantDocument::DISK)->put($path, self::PLACEHOLDER_PDF);

            RegistrantDocument::query()->updateOrCreate(
                ['registrant_id' => $registrant->id, 'type' => $key, 'slot' => $slot],
                ['path' => $path, 'original_name' => $kind->slotLabel($slot).'.pdf', 'mime_type' => 'application/pdf', 'size' => strlen(self::PLACEHOLDER_PDF)],
            );
        }
    }

    /**
     * Record a payment for a bill of the registration: what is still owed on it, or only the first `$amount` rupiah.
     * It is verified straight away unless `$verified` is false, in which case it waits for the committee and the parent
     * is the one who entered it.
     */
    private function pay(Registrant $registrant, BillType $type, ?int $amount = null, bool $verified = true, PaymentMethod $method = PaymentMethod::Transfer, int $daysAgo = 0, ?string $note = null): ?Payment
    {
        $bill = $registrant->bills()->where('type', $type)->with(['items' => fn ($items) => $items->withPaymentTotals()])->first();

        if ($bill === null) {
            return null;
        }

        $items = [];
        $left = $amount ?? PHP_INT_MAX;

        foreach ($bill->items as $item) {
            $part = min($item->outstandingAmount(reservePending: false), $left);

            if ($part > 0) {
                $items[] = ['bill_item_id' => $item->id, 'amount' => $part];
                $left -= $part;
            }
        }

        if ($items === []) {
            return null;
        }

        return app(CreatePayment::class)->handle(new PaymentData(
            payerPersonId: $registrant->registered_by_person_id,
            paidOn: now()->subDays($daysAgo)->toDateString(),
            method: $method,
            items: $items,
            createdBy: $verified ? $this->admin : $this->parentOf($registrant),
            note: $note,
            confirmDuplicate: true,
        ), verified: $verified);
    }

    private function parentOf(Registrant $registrant): User
    {
        return User::query()->where('person_id', $registrant->registered_by_person_id)->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    private function review(Registrant $registrant, array $profile = [], int $sessionNumber = 2, PaymentMethod $method = PaymentMethod::Transfer): Registrant
    {
        $registrant = $this->complete($registrant, $sessionNumber, $profile);
        $this->pay($registrant, BillType::Registration, method: $method);
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

    /**
     * Accept a registration in review, so the settlement bill is issued.
     */
    private function accept(Registrant $registrant): Registrant
    {
        app(AcceptRegistrant::class)->handle($registrant->refresh(), $this->admin);

        return $registrant->refresh();
    }

    /**
     * @param  list<int|null>  $scores  one per exam aspect, null where nothing was entered
     */
    private function score(Registrant $registrant, array $scores): void
    {
        app(SaveExamScores::class)->handle($registrant->refresh(), array_filter($scores, fn (?int $score): bool => $score !== null));
    }

    private function announce(Registrant $registrant): Registrant
    {
        app(AnnounceRegistrant::class)->handle($registrant->refresh(), $this->admin);

        return $registrant->refresh();
    }

    /**
     * Two children of one mother in two waves and two organizations. The father has died and an uncle is the guardian.
     */
    private function siblingsInTwoWaves(Organization $smp, Organization $sma, Wave $wave1): void
    {
        $parent = $this->makeUser('Maya Kusuma', 'maya@demo.test');
        $profile = ['parents' => [
            'father' => $this->parentDetails('Hendra Kusuma', ['is_alive' => false, 'phone' => null]),
            'guardian' => $this->parentDetails('Paman Dedi Kusuma', ['phone' => '081255550001', 'job' => 'Wiraswasta']),
        ], 'child' => ['child_order' => 1, 'siblings_count' => 2]];

        $raka = $this->register($parent, 'Raka Kusuma', Gender::Male, $smp, $this->wave2, ParentRelation::Mother, type: AdmissionType::Transfer);
        $this->complete($raka, 2, $profile + ['education' => [$this->school('elementary', 'SD Islam Al-Azhar', '20219010'), $this->school('junior-high', 'SMP Harapan Bangsa (pindah semester 1)', '20219011')], 'type' => AdmissionType::Transfer]);
        $this->pay($raka->refresh(), BillType::Registration, method: PaymentMethod::Cash);

        $nisa = $this->review($this->register($parent, 'Nisa Kusuma', Gender::Female, $sma, $wave1), $profile + ['child' => ['child_order' => 2, 'siblings_count' => 2]], 1);
        $this->attachDocument($nisa, DocumentType::TahfidzCertificate, [1, 2]);
        $nisa->update(['support_note' => 'Hafal 5 juz dan juara 2 lomba tahfidz tingkat kota.']);
    }

    /**
     * A transfer student with three schools, a biological father, a step mother and a guardian who is the contact, who
     * also supplied evidence of hardship: a KIP with its number, a letter and a note.
     */
    private function transferWithFullFamilyAndHardshipEvidence(Organization $sma, Wave $wave): void
    {
        $parent = $this->makeUser('Eko Nugroho', 'eko@demo.test');
        $registrant = $this->review($this->register($parent, 'Bagas Nugroho', Gender::Male, $sma, $wave, ParentRelation::Father, type: AdmissionType::Transfer), [
            'type' => AdmissionType::Transfer,
            'education' => [
                $this->school('kindergarten', 'TK Pelangi', null),
                $this->school('elementary', 'SD Negeri 3 Cimahi', '20219033'),
                $this->school('junior-high', 'SMP Negeri 2 Cimahi', '20219034'),
            ],
            'parents' => [
                'father' => $this->parentDetails('Eko Nugroho', ['phone' => $parent->person->phone, 'job' => 'Buruh harian', 'last_education' => 'SMA / sederajat', 'monthly_income' => 'Kurang dari Rp1 juta', 'nik' => '3201111111110001']),
                'mother' => $this->parentDetails('Sri Rahayu', ['kind' => 'step', 'phone' => '081255550002', 'job' => 'Ibu rumah tangga', 'monthly_income' => 'Kurang dari Rp1 juta']),
                'guardian' => $this->parentDetails('Kakek Suharto', ['phone' => '081255550003', 'job' => 'Pensiunan', 'last_education' => 'SMP / sederajat', 'monthly_income' => 'Rp1 juta - Rp3 juta']),
            ],
        ], 1);

        $guardian = $registrant->person->parentships()->where('relation', ParentRelation::Guardian)->firstOrFail();
        app(SetPrimaryContact::class)->handle($guardian);

        $this->attachDocument($registrant, DocumentType::Kip);
        $this->attachDocument($registrant, DocumentType::HardshipLetter);
        $this->attachDocument($registrant, DocumentType::OtherSupport, [1, 2]);
        $registrant->update(['kip_number' => '0012345678', 'support_note' => 'Ayah buruh harian, ibu tidak bekerja. Mohon pertimbangan keringanan biaya.']);
    }

    /**
     * Required files are missing (the high school wants the diploma too), and the parent added a certificate of achievement.
     */
    private function incompleteDocuments(Organization $sma, Wave $wave): void
    {
        $parent = $this->makeUser('Nur Aini', 'nur@demo.test');
        $registrant = $this->register($parent, 'Aulia Rahmawati', Gender::Female, $sma, $wave);
        $this->fillProfile($registrant);
        $this->pay($registrant, BillType::Registration);

        $this->attachDocument($registrant, DocumentType::FamilyCard);
        $this->attachDocument($registrant, DocumentType::ReportCard, [1, 2, 3]);
        $this->attachDocument($registrant, DocumentType::AchievementCertificate, [1, 2]);
    }

    /**
     * A tahfidz scholarship covers most of the entrance fee, the family pays the rest, and the result is announced:
     * the settlement receipt and the acceptance letter can be printed.
     */
    private function scholarshipGrantedAndFullyPaid(Organization $smp, Wave $wave): void
    {
        $parent = $this->makeUser('Fitri Handayani', 'fitri@demo.test');
        $registrant = $this->review($this->register($parent, 'Farhan Handayani', Gender::Male, $smp, $wave, ParentRelation::Mother), [], 2);
        $this->attachDocument($registrant, DocumentType::TahfidzCertificate);
        $this->attachDocument($registrant, DocumentType::AchievementCertificate);
        $this->attachDocument($registrant, DocumentType::Diploma);

        $registrant = $this->accept($registrant);
        $tuition = $registrant->bills()->where('type', BillType::Settlement)->firstOrFail()->items()->where('name', 'Uang pangkal')->firstOrFail();

        app(GrantScholarship::class)->handle($registrant, $this->admin, ScholarshipKind::Tahfidz, [['bill_item_id' => $tuition->id, 'amount' => 1500000]], 'Hafal 10 juz, hasil tes tahfidz sangat baik.');
        $this->pay($registrant->refresh(), BillType::Settlement, daysAgo: 2);
        $this->score($registrant, [90, 85]);
        $this->announce($registrant);
    }

    /**
     * Announced, but the score is under the KKM and the entrance fee is still unpaid: shows in the arrears and gets
     * the "not passed" message.
     */
    private function announcedNotPassed(Organization $sma, Wave $wave): void
    {
        $parent = $this->makeUser('Hendra Gunawan', 'hendra@demo.test');
        $registrant = $this->accept($this->review($this->register($parent, 'Hana Gunawan', Gender::Female, $sma, $wave, ParentRelation::Father)));

        $this->score($registrant, [55]);
        $this->announce($registrant);
    }

    /**
     * Only the first of two exam aspects has a score, and the entrance fee is paid in two installments (transfer, then cash).
     */
    private function partialScores(Organization $smp, Wave $wave): void
    {
        $parent = $this->makeUser('Rahmat Hidayat', 'rahmat@demo.test');
        $registrant = $this->accept($this->review($this->register($parent, 'Ismail Hidayat', Gender::Male, $smp, $wave, ParentRelation::Father)));

        $this->score($registrant, [75, null]);
        $this->pay($registrant->refresh(), BillType::Settlement, 1000000, daysAgo: 5, note: 'Cicilan pertama');
        $this->pay($registrant->refresh(), BillType::Settlement, 500000, method: PaymentMethod::Cash, daysAgo: 1, note: 'Cicilan kedua, dibayar tunai di sekolah');
    }

    /**
     * One transfer is rejected by the committee. Another was verified, then cancelled and put back in the queue.
     */
    private function rejectedAndReopenedPayments(Organization $smp, Wave $wave): void
    {
        $parent = $this->makeUser('Hendro Saputra', 'hendro@demo.test');

        $gita = $this->register($parent, 'Gita Saputra', Gender::Female, $smp, $wave);
        $rejected = $this->pay($gita, BillType::Registration, verified: false, note: 'Transfer dari rekening istri');
        app(RejectPayment::class)->handle($rejected, $this->admin, 'Nominal di mutasi tidak sesuai. Mohon kirim bukti yang benar.');

        $gilang = $this->register($parent, 'Gilang Saputra', Gender::Male, $smp, $wave);
        $paid = $this->pay($gilang, BillType::Registration);
        app(VoidPayment::class)->handle($paid, $this->admin, PaymentStatus::Rejected, 'Dana ternyata belum masuk di rekening sekolah.');
        app(ReopenPayment::class)->handle($paid->refresh(), $this->admin, 'Dana sudah masuk, diverifikasi ulang.');
    }

    /**
     * The committee moved a draft straight to valid data, with a reason that stays in the history.
     */
    private function bypassedStatus(Organization $smp, Wave $wave): void
    {
        $parent = $this->makeUser('Kiki Amelia', 'kiki@demo.test');
        $registrant = $this->register($parent, 'Kayla Amelia', Gender::Female, $smp, $wave);
        $this->fillProfile($registrant);

        app(BypassRegistrantStatus::class)->handle($registrant, $this->admin, RegistrantStatus::Validated, 'Pindahan dari cabang yayasan, berkas sudah diverifikasi langsung oleh panitia.');
        $this->pay($registrant->refresh(), BillType::Settlement, 600000, method: PaymentMethod::Cash, daysAgo: 3);
    }

    /**
     * Two siblings placed as students, and the placement of one of them was cancelled again.
     */
    private function placedAndCancelledPlacement(Organization $smp, Organization $sma, Wave $wave): void
    {
        $parent = $this->makeUser('Lukman Hakim', 'lukman@demo.test');

        $luthfi = $this->accept($this->review($this->register($parent, 'Luthfi Hakim', Gender::Male, $sma, $wave, ParentRelation::Father), [], 1, PaymentMethod::Cash));
        $this->pay($luthfi, BillType::Settlement);
        $this->score($luthfi, [82]);
        $this->announce($luthfi);
        app(PlaceRegistrant::class)->handle($luthfi->refresh(), $this->admin, $this->year, 'SMA-0001');

        $latifah = $this->accept($this->review($this->register($parent, 'Latifah Hakim', Gender::Female, $smp, $wave, ParentRelation::Father)));
        $this->pay($latifah, BillType::Settlement);
        $this->score($latifah, [70]);
        $this->announce($latifah);
        $student = app(PlaceRegistrant::class)->handle($latifah->refresh(), $this->admin, $this->year, 'SMP-0002');

        app(CancelPlacement::class)->handle($student, $this->admin, 'Salah tempat: orang tua memilih SMA untuk Latifah, akan ditempatkan ulang.');
    }

    /**
     * A madrasah with no exam aspects, no registration bill and no files asked for: it counts as accepted without
     * scores. A second child is still a draft.
     */
    private function madrasahWithoutExamAspects(Organization $mi, Wave $wave): void
    {
        $parent = $this->makeUser('Siti Zulaikha', 'siti@demo.test');

        $mira = $this->accept($this->review($this->register($parent, 'Mira Zulaikha', Gender::Female, $mi, $wave)));
        $this->pay($mira, BillType::Settlement);
        $this->announce($mira);

        $this->register($parent, 'Zaki Zulaikha', Gender::Male, $mi, $wave);
    }

    /**
     * A NIK that was already on record with a similar name: the committee confirmed it is the same child, so the
     * parent is linked and the flag is gone.
     */
    private function resolvedNikCheck(Organization $smp, Wave $wave): void
    {
        $known = Person::query()->create(['nik' => '3201999900002222', 'name' => 'Aisyah Salim', 'gender' => Gender::Female, 'birth_place' => 'Garut']);
        app(LinkParent::class)->handle($known, Person::query()->create(['name' => 'Bapak Salim', 'phone' => '081200008888']), ParentRelation::Father);

        $parent = $this->makeUser('Agus Salim', 'agus@demo.test');
        $registrant = $this->register($parent, 'Aisyah S.', Gender::Female, $smp, $wave, ParentRelation::Mother, '3201999900002222');

        app(ResolveIdentityReview::class)->handle($registrant, $this->admin);
    }

    /**
     * The committee put a boy in the full first session for a reason, which is written down as a private note.
     */
    private function overriddenFullSession(Organization $smp, Wave $wave): void
    {
        $parent = $this->makeUser('Ratna Dewi', 'ratna@demo.test');
        $registrant = $this->complete($this->register($parent, 'Rafi Dewantara', Gender::Male, $smp, $wave));
        $full = ExamSession::query()->whereHas('day', fn ($days) => $days->forRegistrant($registrant))->orderBy('start_time')->firstOrFail();

        app(ChooseExamSession::class)->handle($registrant, $full, $this->operator, overrideFull: true, overrideReason: 'Anak membutuhkan pendamping dan harus ujian di sesi pagi.');
    }

    /**
     * A parent who has not verified the email yet: the portal shows the reminder.
     */
    private function unverifiedParent(Organization $pesantren, Wave $wave): void
    {
        $parent = $this->makeUser('Tono Baru', 'tono@demo.test', verified: false);
        $this->register($parent, 'Tegar Baru', Gender::Male, $pesantren, $wave, ParentRelation::Father);
    }

    /**
     * A KIP number without any file still points at a hardship scholarship.
     */
    private function pesantrenHardshipByKipNumber(Organization $pesantren, Wave $wave): void
    {
        $parent = $this->makeUser('Zulkifli Anwar', 'zulkifli@demo.test');
        $registrant = $this->review($this->register($parent, 'Zahra Anwar', Gender::Female, $pesantren, $wave, ParentRelation::Father));

        $registrant->update(['kip_number' => '0099887766', 'support_note' => 'Anak yatim, tinggal bersama kakek.']);
    }

    /**
     * Notes of every urgency and visibility on a few children, some shared with a role, a person or the family.
     */
    private function notes(): void
    {
        $operatorRole = Role::findByName(DefaultRole::Operator->value, 'web');
        $saveNote = app(SaveNote::class);

        $note = fn (string $child, string $body, NoteUrgency $urgency, NoteVisibility $visibility, bool $family = false, array $users = [], array $roles = [], ?User $author = null) => $saveNote->handle(
            new NoteData($body, $urgency, $visibility, $family, $users, $roles),
            author: $author ?? $this->admin,
            noteable: Registrant::query()->where('submitted_name', $child)->firstOrFail()->person,
        );

        $note('Sinta Handayani', 'Orang tua sudah dihubungi lewat WhatsApp, janji mengunggah foto baru besok.', NoteUrgency::High, NoteVisibility::Public, family: true);
        $note('Arga Wijaya', 'Orang tua minta keringanan cicilan uang pangkal. Tunggu keputusan kepala sekolah.', NoteUrgency::Critical, NoteVisibility::Private, users: [$this->operator->id]);
        $note('Budi Santoso Jr.', 'Datang langsung ke sekolah untuk menyerahkan berkas asli.', NoteUrgency::Normal, NoteVisibility::Public, author: $this->operator);
        $note('Dinda Anggraini', 'Tanya jadwal daftar ulang.', NoteUrgency::Low, NoteVisibility::Private, roles: [$operatorRole->id], author: $this->operator);
        $note('Hafiz Basri', 'Siswa asrama, perlu penjemputan saat libur.', NoteUrgency::Normal, NoteVisibility::Private, roles: [$operatorRole->id]);
        $note('Bagas Nugroho', 'Kunjungi rumah sebelum keputusan beasiswa. Hubungi kakeknya, bukan ayah.', NoteUrgency::High, NoteVisibility::Private);
    }
}
