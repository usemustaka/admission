<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Actions\Billing\CreatePayment;
use App\Actions\Billing\PaymentData;
use App\Actions\Billing\RejectPayment;
use App\Actions\Billing\VerifyPayment;
use App\Enums\Ability;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Finance\RecordPaymentRequest;
use App\Http\Requests\Admin\Finance\VerifyPaymentRequest;
use App\Models\Bill;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Support\BillPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Payment::class);

        $status = $request->enum('status', PaymentStatus::class);

        return Inertia::render('admin/payments/index', [
            'payments' => Payment::query()
                ->when($status, fn (Builder $query) => $query->where('status', $status))
                ->with(['payer', 'allocations.billItem.bill.registrant.person'])
                ->latest()
                ->paginate(15)
                ->withQueryString()
                ->through(fn (Payment $payment): array => BillPresenter::payment($payment)),
            'filters' => ['status' => $request->string('status')->toString()],
            'counts' => [
                'pending' => Payment::query()->where('status', PaymentStatus::Pending)->count(),
            ],
            'statuses' => array_map(fn (PaymentStatus $case): array => ['value' => $case->value, 'label' => $case->label()], PaymentStatus::cases()),
            'can' => ['verify' => $request->user()->can(Ability::PaymentVerify->value)],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize(Ability::PaymentVerify->value);

        return Inertia::render('admin/payments/create', [
            'methods' => array_map(fn (PaymentMethod $method): array => ['value' => $method->value, 'label' => $method->label()], PaymentMethod::cases()),
        ]);
    }

    /**
     * What a payer can still pay, for the form that records a payment directly.
     */
    public function openItems(Request $request): JsonResponse
    {
        Gate::authorize(Ability::PaymentVerify->value);

        $payer = $request->integer('payer');

        $bills = Bill::query()
            ->whereHas('registrant', fn (Builder $registrants) => $registrants->forParentPerson($payer))
            ->with(['registrant.person', 'registrant.organization', 'items' => fn ($items) => $items->withPaymentTotals()])
            ->orderBy('registrant_id')
            ->orderBy('issued_at')
            ->get();

        return response()->json(['groups' => BillPresenter::openGroups($bills, reservePending: false)]);
    }

    public function store(RecordPaymentRequest $request, CreatePayment $createPayment): RedirectResponse
    {
        /** @var list<array{bill_item_id: int, amount: int}> $items */
        $items = $request->validated('items');

        $payment = $createPayment->handle(new PaymentData(
            payerPersonId: $request->integer('payer_person_id'),
            paidOn: $request->string('paid_on')->toString(),
            method: $request->enum('method', PaymentMethod::class) ?? PaymentMethod::Transfer,
            items: $items,
            createdBy: $request->user(),
            note: $request->filled('note') ? $request->string('note')->toString() : null,
            proof: $request->file('proof'),
        ), verified: true);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pembayaran sudah dicatat.']);

        return to_route('admin.payments.show', $payment);
    }

    public function show(Request $request, Payment $payment): Response
    {
        Gate::authorize('viewAny', Payment::class);

        $payment->load(['payer', 'createdBy.person', 'verifiedBy.person', 'allocations.billItem.bill.registrant.person']);

        return Inertia::render('admin/payments/show', [
            'payment' => [
                ...BillPresenter::payment($payment),
                'note' => $payment->note,
                'proof_is_image' => $payment->proof_path !== null && preg_match('/\.(jpe?g|png)$/i', $payment->proof_path) === 1,
                'payer_id' => $payment->payer_person_id,
                'created_by' => $payment->createdBy?->name,
                'verified_by' => $payment->verifiedBy?->name,
                'verified_at' => $payment->verified_at?->translatedFormat('j M Y, H:i'),
            ],
            'allocations' => $payment->allocations->map(function (PaymentAllocation $allocation) use ($payment): array {
                $item = $allocation->billItem()->withPaymentTotals()->firstOrFail();
                $bill = $item->bill;

                return [
                    'bill_item_id' => $item->id,
                    'item' => $item->name,
                    'bill' => $bill->type->label(),
                    'child' => $bill->registrant->person->name,
                    'item_amount' => $item->amount,
                    'amount' => $allocation->amount,
                    'room' => $payment->isPending() ? $item->outstandingAmount(reservePending: false) : null,
                ];
            })->values(),
            'can' => ['verify' => $request->user()->can(Ability::PaymentVerify->value) && $payment->isPending()],
        ]);
    }

    public function verify(VerifyPaymentRequest $request, Payment $payment, VerifyPayment $verifyPayment): RedirectResponse
    {
        /** @var list<array{bill_item_id: int, amount: int}>|null $allocations */
        $allocations = $request->has('allocations')
            ? array_values(array_filter($request->validated('allocations'), fn (array $row): bool => $row['amount'] > 0))
            : null;

        $verifyPayment->handle($payment, $request->user(), $allocations);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pembayaran sudah diverifikasi.']);

        return to_route('admin.payments.show', $payment);
    }

    public function reject(Request $request, Payment $payment, RejectPayment $rejectPayment): RedirectResponse
    {
        Gate::authorize(Ability::PaymentVerify->value);

        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']], [], ['reason' => 'Alasan']);

        $rejectPayment->handle($payment, $request->user(), $validated['reason']);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pembayaran sudah ditolak, orang tua akan melihat alasannya.']);

        return to_route('admin.payments.show', $payment);
    }
}
