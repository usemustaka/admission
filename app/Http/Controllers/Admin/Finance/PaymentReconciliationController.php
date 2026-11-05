<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Support\CsvCell;
use App\Support\PaymentPresenter;
use App\Support\PaymentReconciliation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentReconciliationController extends Controller
{
    private const WITH = ['payer', 'verifiedBy', 'allocations.billItem.bill.registrant.person'];

    /**
     * The day's closing: what was verified per method, what was received but still waits, and the payments behind both.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Payment::class);

        $reconciliation = PaymentReconciliation::fromRequest($request);
        $methods = $reconciliation->methodTotals();

        $verified = $reconciliation->verified()->with(self::WITH)->orderBy('verified_at')->orderBy('id')->limit(PaymentReconciliation::LIST_LIMIT)->get();
        $pendingTotals = $reconciliation->pending()->toBase()->selectRaw('count(*) as total, coalesce(sum(amount), 0) as amount')->first();
        $pending = $reconciliation->pending()->with(self::WITH)->orderBy('id')->limit(PaymentReconciliation::LIST_LIMIT)->get();

        return Inertia::render('admin/payments/reconciliation', [
            'date' => $reconciliation->day->toDateString(),
            'today' => today()->toDateString(),
            'methods' => $methods,
            'verified' => [
                'count' => array_sum(array_column($methods, 'count')),
                'total' => array_sum(array_column($methods, 'total')),
                'transfer_total' => collect($methods)->firstWhere('value', PaymentMethod::Transfer->value)['total'] ?? 0,
                'rows' => $verified->map(fn (Payment $payment): array => [
                    ...PaymentPresenter::row($payment),
                    'verified_by' => $payment->verifiedBy?->name,
                    'verified_time' => $payment->verified_at?->format('H:i'),
                ])->values()->all(),
            ],
            'pending' => [
                'count' => (int) $pendingTotals->total,
                'total' => (int) $pendingTotals->amount,
                'rows' => $pending->map(fn (Payment $payment): array => [
                    ...PaymentPresenter::row($payment),
                    'age_days' => PaymentReconciliation::ageInDays($payment),
                ])->values()->all(),
            ],
            'list_limit' => PaymentReconciliation::LIST_LIMIT,
            'can' => ['export' => $request->user()->can('export', Payment::class)],
        ]);
    }

    /**
     * The same day as CSV: every verified payment of the day and every payment received that day that still waits.
     */
    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('export', Payment::class);

        $reconciliation = PaymentReconciliation::fromRequest($request);

        return response()->streamDownload(function () use ($reconciliation): void {
            $out = fopen('php://output', 'w') ?: throw new RuntimeException('Tidak bisa membuka keluaran untuk ekspor.');
            $write = fn (array $row) => fputcsv($out, array_map(CsvCell::safe(...), $row), ',', '"', '');

            fwrite($out, "\xEF\xBB\xBF");
            $write(['Kelompok', 'ID', 'Tanggal bayar', 'Pembayar', 'Siswa', 'Rincian', 'Cara bayar', 'Nominal (Rp)', 'Status', 'Diverifikasi oleh', 'Diverifikasi pada']);

            $groups = [
                'Terverifikasi' => $reconciliation->verified()->orderBy('verified_at')->orderBy('id'),
                'Diterima, menunggu verifikasi' => $reconciliation->pending()->orderBy('id'),
            ];

            foreach ($groups as $group => $query) {
                $query->with(self::WITH)->lazy(500)->each(function (Payment $payment) use ($write, $group): void {
                    $row = PaymentPresenter::row($payment);

                    $write([
                        $group,
                        $payment->id,
                        $payment->paid_on->toDateString(),
                        $row['payer'],
                        implode('; ', $row['for']),
                        implode('; ', array_column($row['items'], 'name')),
                        $row['method'],
                        $payment->amount,
                        $row['status_label'],
                        $payment->verifiedBy?->name,
                        $payment->verified_at?->format('Y-m-d H:i'),
                    ]);
                });
            }

            fclose($out);
        }, 'rekonsiliasi-'.$reconciliation->day->toDateString().'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
