<?php

use App\Enums\AdmissionType;
use App\Enums\BillStatus;
use App\Enums\BillType;
use App\Enums\NoteUrgency;
use App\Enums\NoteVisibility;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RegistrantStatus;
use App\Enums\ScholarshipKind;
use App\Models\Bill;
use App\Models\EducationHistory;
use App\Models\ExamSession;
use App\Models\Note;
use App\Models\Payment;
use App\Models\Person;
use App\Models\Registrant;
use App\Models\RegistrantDocument;
use App\Models\Ruleset;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Models\Wave;
use App\Support\AdmissionLetter;
use App\Support\AnnouncementReadiness;
use App\Support\ExamResult;
use App\Support\RegistrantDocuments;
use App\Support\RegistrantReadiness;
use App\Support\SettlementReceipt;
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
        ->and(Student::query()->pluck('nis')->sort()->values()->all())->toBe(['PS-0001', 'SMA-0001']);
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

function demoChild(string $name): Registrant
{
    return Registrant::query()->where('submitted_name', $name)->firstOrFail();
}

describe('staff and accounts', function () {
    test('it has the committee roles, a super admin and a deactivated account', function () {
        $this->seed(DemoSeeder::class);

        $user = fn (string $email) => User::query()->where('email', $email)->firstOrFail();

        expect($user('tes@demo.test')->hasRole('panitia-tes'))->toBeTrue()
            ->and($user('keuangan@demo.test')->hasRole('panitia-keuangan'))->toBeTrue()
            ->and($user('super@demo.test')->hasRole('super-admin'))->toBeTrue()
            ->and($user('staf-lama@demo.test')->hasRole('nonaktif'))->toBeTrue()
            ->and($user('tono@demo.test')->hasVerifiedEmail())->toBeFalse()
            ->and($user('rina@demo.test')->hasVerifiedEmail())->toBeTrue();

        $this->post(route('login'), ['email' => 'tes@demo.test', 'password' => DemoSeeder::PASSWORD])->assertRedirect();
        $this->assertAuthenticatedAs($user('tes@demo.test'));
    });

    test('an unverified parent can use the portal and the school letterhead is filled in', function () {
        $this->seed(DemoSeeder::class);
        $this->withoutVite();

        $this->actingAs(User::query()->where('email', 'tono@demo.test')->firstOrFail())->get(route('portal'))->assertOk();
        expect(Setting::get('contact.address'))->not->toBeNull()->and(Setting::get('payment.account_number'))->not->toBeNull();
    });
});

describe('the family and the data form', function () {
    test('it has transfer students, several schools, and a full family with a guardian as the contact', function () {
        $this->seed(DemoSeeder::class);

        $bagas = demoChild('Bagas Nugroho');
        $parents = $bagas->person->parentships()->with('relatedPerson')->get()->keyBy(fn ($parentship) => $parentship->relation->value);

        expect(Registrant::query()->where('admission_type', AdmissionType::Transfer)->count())->toBeGreaterThanOrEqual(2)
            ->and(EducationHistory::query()->where('person_id', $bagas->person_id)->count())->toBe(3)
            ->and($parents['mother']->kind->value)->toBe('step')
            ->and($parents['father']->kind->value)->toBe('biological')
            ->and($parents['guardian']->is_primary_contact)->toBeTrue()
            ->and(demoChild('Nisa Kusuma')->person->parentships->firstWhere('relation.value', 'father')->relatedPerson->is_alive)->toBeFalse();
    });

    test('siblings sit in different waves and organizations, and one wave is closed', function () {
        $this->seed(DemoSeeder::class);

        $maya = User::query()->where('email', 'maya@demo.test')->firstOrFail();
        $children = Registrant::query()->forParent($maya)->get();

        expect($children)->toHaveCount(2)
            ->and($children->pluck('wave_id')->unique())->toHaveCount(2)
            ->and($children->pluck('organization_id')->unique())->toHaveCount(2)
            ->and(Wave::query()->where('is_open', false)->count())->toBe(1);
    });

    test('every child past the draft has the data the flow asks for', function () {
        $this->seed(DemoSeeder::class);

        // The bypassed child is the one that skipped the flow on purpose.
        Registrant::query()->whereNotIn('status', [RegistrantStatus::Draft])->where('needs_identity_review', false)->where('submitted_name', '!=', 'Kayla Amelia')->get()->each(function (Registrant $registrant) {
            expect(RegistrantReadiness::missing($registrant))->toBe([], "{$registrant->submitted_name} is missing something");
        });
    });
});

describe('exam scores', function () {
    test('it has a pass, a fail, partial scores and a registration without aspects', function () {
        $this->seed(DemoSeeder::class);

        $result = fn (string $name) => ExamResult::for(demoChild($name));

        expect($result('Farhan Handayani')['passed'])->toBeTrue()
            ->and($result('Hana Gunawan')['passed'])->toBeFalse()
            ->and($result('Ismail Hidayat')['complete'])->toBeFalse()
            ->and($result('Ismail Hidayat')['aspects'][0]['score'])->toBe(75)
            ->and($result('Mira Zulaikha')['aspects'])->toBe([])
            ->and(demoChild('Hana Gunawan')->status)->toBe(RegistrantStatus::Announced);
    });
});

describe('files', function () {
    test('the rulesets ask for different files', function () {
        $this->seed(DemoSeeder::class);

        $asked = fn (string $name) => collect(Ruleset::query()->where('name', $name)->firstOrFail()->documents)->filter(fn ($document) => in_array($document->type, ['family-card', 'birth-certificate', 'report-card', 'diploma'], true))->mapWithKeys(fn ($document) => [$document->type => $document->is_required])->all();

        expect($asked('SMA'))->toBe(['family-card' => true, 'birth-certificate' => true, 'report-card' => true, 'diploma' => true])
            ->and($asked('Pesantren'))->toBe(['family-card' => true])
            ->and($asked('MI'))->toBe([])
            ->and($asked('SMP umum'))->toHaveKey('diploma', false);
    });

    test('one child has complete files, one is missing some, and the uploaded files exist on the disk', function () {
        $this->seed(DemoSeeder::class);

        expect(RegistrantDocuments::missing(demoChild('Bagas Nugroho')))->toBe([])
            ->and(RegistrantDocuments::missing(demoChild('Aulia Rahmawati')))->toContain('Akta kelahiran', 'Ijazah / SKL');

        RegistrantDocument::query()->each(fn (RegistrantDocument $document) => Storage::disk(RegistrantDocument::DISK)->assertExists($document->path));
    });
});

describe('scholarships', function () {
    test('one child was given a scholarship and the others are still candidates by kind', function () {
        $this->seed(DemoSeeder::class);

        $scholarship = Payment::query()->where('method', PaymentMethod::Scholarship)->sole();

        expect($scholarship->status)->toBe(PaymentStatus::Verified)
            ->and($scholarship->amount)->toBe(1500000)
            ->and($scholarship->note)->toContain('Beasiswa Tahfidz')
            ->and(Registrant::query()->scholarshipCandidates(ScholarshipKind::Hardship)->pluck('submitted_name')->sort()->values()->all())->toBe(['Bagas Nugroho', 'Zahra Anwar'])
            ->and(Registrant::query()->scholarshipCandidates(ScholarshipKind::Achievement)->pluck('submitted_name')->all())->toBe(['Aulia Rahmawati'])
            ->and(Registrant::query()->scholarshipCandidates(ScholarshipKind::Tahfidz)->pluck('submitted_name')->all())->toBe(['Nisa Kusuma'])
            ->and(RegistrantDocuments::hasSupportingEvidence(demoChild('Zahra Anwar')))->toBeTrue();
    });
});

describe('payments', function () {
    test('it has a fully paid registration with its receipt and letter, a rejected payment, and a cancelled one back in the queue', function () {
        $this->seed(DemoSeeder::class);

        $farhan = demoChild('Farhan Handayani');

        expect(SettlementReceipt::isAvailable($farhan))->toBeTrue()
            ->and(AdmissionLetter::canPrint($farhan))->toBeTrue()
            ->and(AdmissionLetter::canPrint(demoChild('Hana Gunawan')))->toBeFalse()
            ->and(Payment::query()->where('status', PaymentStatus::Rejected)->count())->toBeGreaterThanOrEqual(1)
            ->and(demoChild('Gilang Saputra')->bills->flatMap->items->flatMap->allocations->map->payment->pluck('status')->unique()->all())->toBe([PaymentStatus::Pending])
            ->and(Payment::query()->where('method', PaymentMethod::Cash)->count())->toBeGreaterThanOrEqual(3)
            ->and(Payment::query()->where('status', PaymentStatus::Pending)->count())->toBeGreaterThanOrEqual(2);
    });

    test('some registrations still owe money after the result is announced', function () {
        $this->seed(DemoSeeder::class);

        $owing = Registrant::query()->where('status', RegistrantStatus::Announced)->get()
            ->filter(fn (Registrant $registrant) => AnnouncementReadiness::for($registrant)['unsettled']);

        expect($owing->pluck('submitted_name')->all())->toContain('Hana Gunawan');
    });
});

describe('status history, placements and notes', function () {
    test('a bypass keeps its reason, a placement was cancelled, and a NIK check was resolved', function () {
        $this->seed(DemoSeeder::class);

        $kayla = demoChild('Kayla Amelia');
        $latifah = demoChild('Latifah Hakim');

        expect($kayla->status)->toBe(RegistrantStatus::Validated)
            ->and($kayla->statusChanges()->where('is_bypass', true)->first()->note)->not->toBeEmpty()
            ->and($latifah->status)->toBe(RegistrantStatus::Announced)
            ->and(Student::query()->where('person_id', $latifah->person_id)->exists())->toBeFalse()
            ->and(demoChild('Aisyah S.')->needs_identity_review)->toBeFalse()
            ->and(Registrant::query()->where('needs_identity_review', true)->count())->toBe(1);
    });

    test('a full session was overridden with a reason', function () {
        $this->seed(DemoSeeder::class);

        $rafi = demoChild('Rafi Dewantara');

        expect(Note::query()->where('person_id', $rafi->person_id)->where('body', 'like', '%sudah penuh%')->exists())->toBeTrue();
    });

    test('there are notes of every urgency and both visibilities', function () {
        $this->seed(DemoSeeder::class);

        $notes = Note::query()->whereNotNull('author_id')->get();

        expect($notes->pluck('urgency')->unique())->toHaveCount(4)
            ->and($notes->pluck('visibility')->map->value->unique()->sort()->values()->all())->toBe(['private', 'public'])
            ->and($notes->contains(fn (Note $note) => $note->shared_with_family))->toBeTrue()
            ->and($notes->pluck('urgency')->map->value->all())->toContain(NoteUrgency::Critical->value)
            ->and(NoteVisibility::cases())->toHaveCount(2);
    });
});
