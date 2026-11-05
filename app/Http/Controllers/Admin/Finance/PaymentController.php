<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Actions\Billing\BulkVerifyPayments;
use App\Actions\Billing\CreatePayment;
use App\Actions\Billing\PaymentData;
use App\Actions\Billing\RejectPayment;
use App\Actions\Billing\VerifyPayment;
use App\Enums\Ability;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Finance\BulkVerifyPaymentsRequest;
use App\Http\Requests\Admin\Finance\RecordPaymentRequest;
use App\Http\Requests\Admin\Finance\VerifyPaymentRequest;
use App\Models\Bill;
use App\Models\Payment;
use App\Support\BillPresenter;
use App\Support\PaymentFilters;
use App\Support\PaymentPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Payment::class);

        $filters = PaymentFilters::fromRequest($request);
        $perStatus = $filters->query(withStatus: false)
            ->toBase()
            ->selectRaw('status, count(*) as total, coalesce(sum(amount), 0) as amount')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $payments = $filters->ordered()
            ->with(['payer', 'allocations.billItem.bill.registrant.person'])
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/payments/index', [
            'payments' => $payments->through(fn (Payment $payment): array => PaymentPresenter::row($payment)),
            'filters' => $filters->toProps(),
            'counts' => [
                'all' => (int) $perStatus->sum('total'),
                ...collect(PaymentStatus::cases())->mapWithKeys(fn (PaymentStatus $status): array => [$status->value => (int) ($perStatus->get($status->value)->total ?? 0)])->all(),
            ],
            // `amount` is the sum of the current filter; with no status chosen it mixes waiting, verified and rejected
            // money, so the page shows `by_status` instead.
            'totals' => [
                'amount' => (int) $filters->query()->sum('amount'),
                'by_status' => collect(PaymentStatus::cases())->mapWithKeys(fn (PaymentStatus $status): array => [$status->value => (int) ($perStatus->get($status->value)->amount ?? 0)])->all(),
            ],
            'summary' => $this->summary(),
            'statuses' => array_map(fn (PaymentStatus $case): array => ['value' => $case->value, 'label' => $case->label()], PaymentStatus::cases()),
            'methods' => array_map(fn (PaymentMethod $method): array => ['value' => $method->value, 'label' => $method->label()], PaymentMethod::cases()),
            'can' => [
                'verify' => $request->user()->can(Ability::PaymentVerify->value),
                'record' => $request->user()->can(Ability::PaymentRecord->value),
                'export' => $request->user()->can('export', Payment::class),
            ],
        ]);
    }

    /**
     * The figures above the list, over all payments. "This month" counts by the day a payment was verified, which is
     * when the money was accepted; the date a parent says they paid on is only their claim.
     *
     * Per bill item, "outstanding" is what is still asked and "overpaid" is verified money beyond what was asked,
     * so surplus on one item never hides what another item still owes.
     *
     * @return array<string, mixed>
     */
    private function summary(): array
    {
        $pending = Payment::query()->where('status', PaymentStatus::Pending)->toBase()->selectRaw('count(*) as total, coalesce(sum(amount), 0) as amount')->first();
        $monthStart = today()->startOfMonth();
        $monthEnd = today()->endOfMonth();
        $nextMonthStart = $monthStart->copy()->addMonthNoOverflow();

        $received = DB::table('payment_allocations')
            ->join('payments', 'payments.id', '=', 'payment_allocations.payment_id')
            ->where('payments.status', PaymentStatus::Verified->value)
            ->groupBy('payment_allocations.bill_item_id')
            ->selectRaw('payment_allocations.bill_item_id, sum(payment_allocations.amount) as received');

        $items = DB::table('bill_items')
            ->leftJoinSub($received, 'received', 'received.bill_item_id', '=', 'bill_items.id')
            ->selectRaw('coalesce(sum(case when bill_items.amount > coalesce(received.received, 0) then bill_items.amount - coalesce(received.received, 0) else 0 end), 0) as outstanding')
            ->selectRaw('coalesce(sum(case when coalesce(received.received, 0) > bill_items.amount then received.received - bill_items.amount else 0 end), 0) as overpaid')
            ->first();

        return [
            'pending_count' => (int) $pending->total,
            'pending_amount' => (int) $pending->amount,
            'verified_this_month' => (int) Payment::query()
                ->where('status', PaymentStatus::Verified)
                ->where('verified_at', '>=', $monthStart)
                ->where('verified_at', '<', $nextMonthStart)
                ->sum('amount'),
            'month' => ['dari' => $monthStart->toDateString(), 'sampai' => $monthEnd->toDateString()],
            'rejected_count' => Payment::query()->where('status', PaymentStatus::Rejected)->count(),
            'outstanding' => (int) $items->outstanding,
            'overpaid' => (int) $items->overpaid,
        ];
    }

    /**
     * Every payment of the current filters as CSV, all pages, read in chunks so a long list never sits in memory.
     */
    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('export', Payment::class);

        $filters = PaymentFilters::fromRequest($request);

        return response()->streamDownload(function () use ($filters): void {
            $out = fopen('php://output', 'w') ?: throw new RuntimeException('Tidak bisa membuka keluaran untuk ekspor.');
            $write = fn (array $row) => fputcsv($out, array_map(self::safeCell(...), $row), ',', '"', '');

            fwrite($out, "\xEF\xBB\xBF");
            $write(['ID', 'Tanggal bayar', 'Pembayar', 'Siswa', 'Rincian', 'Cara bayar', 'Nominal (Rp)', 'Status', 'Alasan ditolak', 'Catatan', 'Dikirim', 'Diperiksa oleh', 'Diperiksa pada']);

            $filters->ordered()
                ->with(['payer', 'verifiedBy.person', 'allocations.billItem.bill.registrant.person'])
                ->lazy(500)
                ->each(function (Payment $payment) use ($write): void {
                    $row = PaymentPresenter::row($payment);

                    $write([
                        $payment->id,
                        $payment->paid_on->toDateString(),
                        $row['payer'],
                        implode('; ', $row['for']),
                        implode('; ', array_column($row['items'], 'name')),
                        $row['method'],
                        $payment->amount,
                        $row['status_label'],
                        $payment->rejection_reason,
                        $payment->note,
                        $payment->created_at?->format('Y-m-d H:i'),
                        $payment->verifiedBy?->name,
                        $payment->verified_at?->format('Y-m-d H:i'),
                    ]);
                });

            fclose($out);
        }, 'pembayaran-'.today()->toDateString().'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Spreadsheets run text that starts with these characters as a formula, so such cells get a leading quote.
     */
    private static function safeCell(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'{$value}";
        }

        return $value;
    }

    /**
     * One payment for the quick view of the list, together with the next payment still waiting in the queue
     * (oldest first, within the same narrowing) so the committee can keep going without going back to the list.
     */
    public function quickView(Request $request, Payment $payment): JsonResponse
    {
        Gate::authorize('viewAny', Payment::class);

        $queue = PaymentFilters::fromRequest($request)
            ->ordered(withStatus: false)
            ->where('status', PaymentStatus::Pending)
            ->whereKeyNot($payment->id);

        return response()->json([
            ...PaymentPresenter::detail($payment),
            'can' => ['verify' => $request->user()->can(Ability::PaymentVerify->value) && $payment->isPending()],
            'queue' => [
                'next_id' => (clone $queue)->value('id'),
                'left' => (clone $queue)->reorder()->count(),
            ],
        ]);
    }

    /**
     * Verify the selected payments as they were submitted. Each one is handled on its own; those that were skipped
     * come back, all of them, in the `bulk` flash so the page can list them.
     */
    public function bulkVerify(BulkVerifyPaymentsRequest $request, BulkVerifyPayments $bulkVerify): RedirectResponse
    {
        /** @var list<int> $ids */
        $ids = array_map(intval(...), $request->validated('ids'));

        $result = $bulkVerify->handle($ids, $request->user(), $request->boolean('include_without_proof'));
        $skipped = $result['skipped'];

        $message = $skipped === []
            ? "{$result['verified']} pembayaran diverifikasi."
            : "{$result['verified']} diverifikasi, ".count($skipped).' dilewati. Alasan: '
                .collect($skipped)->countBy('reason')->map(fn (int $count, string $reason): string => "{$reason} ({$count})")->implode('; ').'.';

        Inertia::flash('toast', ['type' => $skipped === [] ? 'success' : 'warning', 'message' => $message]);
        Inertia::flash('bulk', ['done' => $result['verified'], 'skipped' => $skipped]);

        return back();
    }

    public function create(): Response
    {
        Gate::authorize('record', Payment::class);

        return Inertia::render('admin/payments/create', [
            'methods' => array_map(fn (PaymentMethod $method): array => ['value' => $method->value, 'label' => $method->label()], PaymentMethod::cases()),
        ]);
    }

    /**
     * What a payer can still pay, for the form that records a payment directly.
     */
    public function openItems(Request $request): JsonResponse
    {
        Gate::authorize('record', Payment::class);

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

        $detail = PaymentPresenter::detail($payment);

        return Inertia::render('admin/payments/show', [
            'payment' => $detail['payment'],
            'allocations' => $detail['allocations'],
            'can' => ['verify' => $request->user()->can(Ability::PaymentVerify->value) && $payment->isPending()],
        ]);
    }

    public function verify(VerifyPaymentRequest $request, Payment $payment, VerifyPayment $verifyPayment): RedirectResponse
    {
        // Rows given nothing are dropped, but keep the position they had in the form, so an error lands on its own row.
        /** @var array<int, array{bill_item_id: int, amount: int}>|null $given */
        $given = $request->validated('allocations');
        $allocations = $given === null ? null : array_filter($given, fn (array $row): bool => $row['amount'] > 0);

        $verifyPayment->handle($payment, $request->user(), $allocations);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pembayaran sudah diverifikasi.']);

        return $this->afterReview($request, $payment);
    }

    public function reject(Request $request, Payment $payment, RejectPayment $rejectPayment): RedirectResponse
    {
        Gate::authorize(Ability::PaymentVerify->value);

        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']], [], ['reason' => 'Alasan']);

        $rejectPayment->handle($payment, $request->user(), $validated['reason']);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pembayaran sudah ditolak, orang tua akan melihat alasannya.']);

        return $this->afterReview($request, $payment);
    }

    /**
     * From the detail page the committee stays on that payment; from the quick view of the list (`quick`) it goes
     * back to the list it came from.
     */
    private function afterReview(Request $request, Payment $payment): RedirectResponse
    {
        if ($request->boolean('quick')) {
            return back(fallback: route('admin.payments.index'));
        }

        return to_route('admin.payments.show', $payment);
    }
}
