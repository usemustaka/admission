<?php

use App\Enums\Ability;
use App\Enums\BillStatus;
use App\Enums\BillType;
use App\Enums\DefaultRole;
use App\Enums\DocumentType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RegistrantStatus;
use App\Enums\ScholarshipKind;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Note;
use App\Models\Payment;
use App\Models\Registrant;
use App\Models\RegistrantDocument;
use App\Support\PaymentReconciliation;
use App\Support\RegistrantListFilters;
use App\Support\ScholarshipPresenter;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->admin = userWithRole(DefaultRole::Admin);
    $this->registrant = admissionRegistrant(RegistrantStatus::Validated);
    $this->bill = Bill::factory()->create(['registrant_id' => $this->registrant->id, 'type' => BillType::Settlement]);
    $this->tuition = BillItem::factory()->create(['bill_id' => $this->bill->id, 'name' => 'Uang pangkal', 'amount' => 1000000, 'sort_order' => 0]);
    $this->uniform = BillItem::factory()->create(['bill_id' => $this->bill->id, 'name' => 'Seragam', 'amount' => 500000, 'sort_order' => 1]);

    $this->grant = fn (array $data = []) => $this->actingAs($this->admin)->post(route('admin.registrants.scholarship', $this->registrant), [
        'kind' => 'achievement',
        'items' => [['bill_item_id' => $this->tuition->id, 'amount' => 600000]],
        ...$data,
    ]);
});

describe('granting', function () {
    test('it adds a verified payment of the method scholarship and leaves the bill amounts alone', function () {
        ($this->grant)(['note' => 'Juara olimpiade'])->assertSessionHasNoErrors();

        $payment = Payment::query()->sole();

        expect($payment->method)->toBe(PaymentMethod::Scholarship)
            ->and($payment->status)->toBe(PaymentStatus::Verified)
            ->and($payment->amount)->toBe(600000)
            ->and($payment->note)->toContain('Beasiswa Prestasi')->toContain('Juara olimpiade')
            ->and($payment->verified_by_user_id)->toBe($this->admin->id)
            ->and($payment->allocations()->sole()->bill_item_id)->toBe($this->tuition->id)
            ->and($this->tuition->refresh()->amount)->toBe(1000000)
            ->and($this->bill->refresh()->loadTotals()->status())->toBe(BillStatus::Partial);
    });

    test('a scholarship that covers every item makes the bill paid', function () {
        ($this->grant)(['items' => [
            ['bill_item_id' => $this->tuition->id, 'amount' => 1000000],
            ['bill_item_id' => $this->uniform->id, 'amount' => 500000],
        ]])->assertSessionHasNoErrors();

        expect($this->bill->refresh()->loadTotals()->status())->toBe(BillStatus::Paid);
    });

    test('it leaves a private note on the child saying who got how much', function () {
        ($this->grant)()->assertSessionHasNoErrors();

        expect(Note::query()->where('person_id', $this->registrant->person_id)->where('body', 'like', 'Beasiswa Prestasi diberikan sebesar%')->exists())->toBeTrue();
    });

    test('it cannot cover more than what is still owed', function () {
        ($this->grant)(['items' => [['bill_item_id' => $this->tuition->id, 'amount' => 1000001]]])->assertSessionHasErrors('items.0.amount');

        expect(Payment::query()->count())->toBe(0);
    });

    test('a second scholarship only covers what the first left owing', function () {
        ($this->grant)()->assertSessionHasNoErrors();
        ($this->grant)(['kind' => 'hardship', 'items' => [['bill_item_id' => $this->tuition->id, 'amount' => 400001]]])->assertSessionHasErrors('items.0.amount');
        ($this->grant)(['kind' => 'hardship', 'items' => [['bill_item_id' => $this->tuition->id, 'amount' => 400000]]])->assertSessionHasNoErrors();

        expect(Payment::query()->count())->toBe(2);
    });

    test('an empty selection and an unknown kind are refused', function () {
        ($this->grant)(['items' => [['bill_item_id' => $this->tuition->id, 'amount' => 0]]])->assertSessionHasErrors('items');
        ($this->grant)(['kind' => 'nepotism'])->assertSessionHasErrors('kind');

        expect(Payment::query()->count())->toBe(0);
    });

    test('an item of another child cannot be covered', function () {
        $other = BillItem::factory()->create(['amount' => 100000]);

        ($this->grant)(['items' => [['bill_item_id' => $other->id, 'amount' => 100000]]])->assertSessionHasErrors('items.0.bill_item_id');
    });

    test('a placed child or one with an open NIK check gets no scholarship', function () {
        $this->registrant->update(['status' => RegistrantStatus::Placed]);
        ($this->grant)()->assertSessionHasErrors('items');

        $this->registrant->update(['status' => RegistrantStatus::Validated, 'needs_identity_review' => true]);
        ($this->grant)()->assertSessionHasErrors('items');

        expect(Payment::query()->count())->toBe(0);
    });

    test('only staff with the scholarship ability can grant, however much else they may do', function () {
        $this->admin = userWithRole(DefaultRole::Operator);
        ($this->grant)()->assertForbidden();

        $this->admin->givePermissionTo(Ability::ScholarshipGrant->value);
        ($this->grant)()->assertSessionHasNoErrors();
    });

    test('a scholarship is never offered as a way to pay or to record a payment', function () {
        $parent = userWithRole(DefaultRole::User);

        $this->actingAs($this->admin)->post(route('admin.payments.store'), [
            'payer_person_id' => $this->registrant->registered_by_person_id, 'paid_on' => today()->toDateString(), 'method' => 'scholarship',
            'items' => [['bill_item_id' => $this->tuition->id, 'amount' => 1000]],
        ])->assertSessionHasErrors('method');

        $this->actingAs($parent)->post(route('portal.payments.store'), ['method' => 'scholarship'])->assertSessionHasErrors('method');
    });

    test('the daily reconciliation counts money received, not scholarships', function () {
        ($this->grant)()->assertSessionHasNoErrors();

        $totals = collect((new PaymentReconciliation(CarbonImmutable::today()))->methodTotals());

        expect($totals->pluck('value')->all())->not->toContain('scholarship')->and($totals->sum('total'))->toBe(0);
    });
});

describe('candidates', function () {
    test('files and a KIP number make a child a candidate for the matching kind until a scholarship is granted', function () {
        $other = admissionRegistrant(RegistrantStatus::Review);
        RegistrantDocument::factory()->create(['registrant_id' => $other->id, 'type' => DocumentType::TahfidzCertificate]);
        $this->registrant->update(['kip_number' => '123456']);

        expect(Registrant::query()->scholarshipCandidates()->pluck('id')->sort()->values()->all())->toBe([$this->registrant->id, $other->id])
            ->and(Registrant::query()->scholarshipCandidates(ScholarshipKind::Tahfidz)->pluck('id')->all())->toBe([$other->id])
            ->and(Registrant::query()->scholarshipCandidates(ScholarshipKind::Hardship)->pluck('id')->all())->toBe([$this->registrant->id])
            ->and(Registrant::query()->scholarshipCandidates(ScholarshipKind::Achievement)->count())->toBe(0);

        ($this->grant)(['kind' => 'hardship'])->assertSessionHasNoErrors();

        expect(Registrant::query()->scholarshipCandidates()->pluck('id')->all())->toBe([$other->id]);
    });

    test('a supporting file that points at no scholarship, or a placed child, is not a candidate', function () {
        RegistrantDocument::factory()->create(['registrant_id' => $this->registrant->id, 'type' => DocumentType::OtherSupport]);
        $placed = admissionRegistrant(RegistrantStatus::Placed, ['kip_number' => '999']);

        expect(Registrant::query()->scholarshipCandidates()->count())->toBe(0);
    });

    test('the list filters on candidates by kind', function () {
        $this->withoutVite();
        $this->registrant->update(['kip_number' => '123456']);
        $other = admissionRegistrant(RegistrantStatus::Review);
        RegistrantDocument::factory()->create(['registrant_id' => $other->id, 'type' => DocumentType::AchievementCertificate]);

        $this->actingAs($this->admin)->get(route('admin.registrants.index', ['beasiswa' => 'semua']))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('registrants.data', fn ($rows) => collect($rows)->pluck('id')->sort()->values()->all() === [$this->registrant->id, $other->id])
            ->where('filters.beasiswa', 'semua')
            ->where('can.scholarship', true));

        $this->get(route('admin.registrants.index', ['beasiswa' => 'achievement']))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('registrants.data', fn ($rows) => collect($rows)->pluck('id')->all() === [$other->id]));

        expect(RegistrantListFilters::fromArray(['beasiswa' => 'bogus'])->scholarship)->toBe('');
    });

    test('the dashboard lists the candidates only for staff who may grant', function () {
        $this->withoutVite();
        $this->registrant->update(['kip_number' => '123456']);

        $this->actingAs($this->admin)->get(route('dashboard'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('attention', fn ($queues) => collect($queues)->firstWhere('key', 'scholarship_candidates')['count'] === 1));

        $this->actingAs(registrantViewer())->get(route('dashboard'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('attention', fn ($queues) => collect($queues)->firstWhere('key', 'scholarship_candidates') === null));
    });
});

describe('the registrant page', function () {
    test('it shows the evidence, the kinds it points at, what is owed and what was granted', function () {
        $this->withoutVite();
        $this->registrant->update(['kip_number' => '123456']);
        ($this->grant)(['note' => 'x'])->assertSessionHasNoErrors();

        $this->get(route('admin.registrants.show', $this->registrant))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('scholarship.suggested', ['hardship'])
            ->has('scholarship.granted', 1)
            ->where('scholarship.items.0.outstanding', 400000)
            ->where('scholarship.blocked', null)
            ->where('documents.kip_number', '123456'));
    });

    test('staff who cannot grant do not get the scholarship block but still see the files', function () {
        $this->withoutVite();
        RegistrantDocument::factory()->create(['registrant_id' => $this->registrant->id, 'type' => DocumentType::Kip]);

        $this->actingAs(registrantViewer())->get(route('admin.registrants.show', $this->registrant))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('scholarship', null)
            ->where('documents.count', 1));
    });

    test('it says why nothing can be granted when nothing is owed', function () {
        $this->tuition->update(['amount' => 0]);
        $this->uniform->update(['amount' => 0]);

        expect(ScholarshipPresenter::for($this->registrant)['blocked'])->toBe('Tidak ada tagihan yang masih harus dibayar.');
    });
});
