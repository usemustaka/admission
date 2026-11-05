<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Enums\Ability;
use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Registrant;
use App\Models\Wave;
use App\Support\Announcement;
use App\Support\ArrearsReport;
use App\Support\CsvCell;
use App\Support\MessageTemplates;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ArrearsController extends Controller
{
    private const WITH = ['person', 'organization', 'wave'];

    /**
     * Who still owes money, one row per registrant (largest debt first unless another order is chosen), with the sum
     * owed per age bucket and a WhatsApp reminder per row.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Payment::class);

        $report = ArrearsReport::fromRequest($request);
        $rows = $report->rows()->with(self::WITH)->paginate(25)->withQueryString();
        $types = $this->billTypes($rows->getCollection());
        $totals = $report->totals();

        return Inertia::render('admin/payments/arrears', [
            'rows' => $rows->through(fn (Registrant $registrant): array => [
                ...$this->row($registrant, $types[$registrant->id] ?? []),
                'whatsapp_url' => Announcement::whatsappUrlFor($registrant, MessageTemplates::ARREARS, ['sisa' => Money::format((int) $registrant->outstanding)]),
            ]),
            'filters' => $report->toProps(),
            'totals' => $totals,
            'buckets' => collect(ArrearsReport::BUCKET_LABELS)->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])->values(),
            'organizations' => Organization::query()->orderBy('sort_order')->orderBy('name')->get()->map(fn (Organization $organization): array => ['value' => $organization->id, 'label' => $organization->name])->values(),
            'waves' => Wave::query()->orderByDesc('id')->get()->map(fn (Wave $wave): array => ['value' => $wave->id, 'label' => $wave->name])->values(),
            'can' => [
                'export' => $request->user()->can('export', Payment::class),
                'manageTemplates' => $request->user()->can(Ability::AnnouncementManage->value),
            ],
        ]);
    }

    /**
     * The report of the current filters as CSV, all pages, read in chunks.
     */
    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('export', Payment::class);

        $report = ArrearsReport::fromRequest($request);

        return response()->streamDownload(function () use ($report): void {
            $out = fopen('php://output', 'w') ?: throw new RuntimeException('Tidak bisa membuka keluaran untuk ekspor.');
            $write = fn (array $row) => fputcsv($out, array_map(CsvCell::safe(...), $row), ',', '"', '');

            fwrite($out, "\xEF\xBB\xBF");
            $write(['ID pendaftar', 'Nama anak', 'Lembaga', 'Gelombang', 'Tagihan terbuka', 'Ditagihkan (Rp)', 'Dibayar terverifikasi (Rp)', 'Menunggu verifikasi (Rp)', 'Sisa (Rp)', 'Kelebihan bayar di rincian lain (Rp)', 'Tagihan terbuka tertua terbit', 'Umur (hari)', 'Kelompok umur']);

            $report->rows()->with(self::WITH)->chunk(500, function (Collection $registrants) use ($write): void {
                $types = $this->billTypes($registrants);

                foreach ($registrants as $registrant) {
                    $row = $this->row($registrant, $types[$registrant->id] ?? []);

                    $write([
                        $row['id'],
                        $row['child_name'],
                        $row['organization'],
                        $row['wave'],
                        implode('; ', $row['types']),
                        $row['billed'],
                        $row['paid'],
                        $row['pending'],
                        $row['outstanding'],
                        $row['excess'],
                        $row['oldest_issued_on'],
                        $row['age_days'],
                        ArrearsReport::BUCKET_LABELS[$row['bucket']],
                    ]);
                }
            });

            fclose($out);
        }, 'tunggakan-'.today()->toDateString().'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @param  list<string>  $types
     * @return array<string, mixed>
     */
    private function row(Registrant $registrant, array $types): array
    {
        $age = ArrearsReport::ageInDays((string) $registrant->oldest_open_at);

        return [
            'id' => $registrant->id,
            'child_name' => $registrant->person->name,
            'organization' => $registrant->organization->name,
            'wave' => $registrant->wave->name,
            'types' => $types,
            'billed' => (int) $registrant->billed,
            'paid' => (int) $registrant->paid,
            'pending' => (int) $registrant->pending,
            'outstanding' => (int) $registrant->outstanding,
            'excess' => (int) $registrant->excess,
            'oldest_issued_on' => substr((string) $registrant->oldest_open_at, 0, 10),
            'age_days' => $age,
            'bucket' => ArrearsReport::bucketOf($age),
        ];
    }

    /**
     * The kinds of bill that are still open, per registrant of the given rows (one query for all of them).
     *
     * @param  Collection<int, Registrant>  $registrants
     * @return array<int, list<string>>
     */
    private function billTypes(Collection $registrants): array
    {
        return Bill::query()
            ->whereIn('registrant_id', $registrants->modelKeys())
            ->with(['items' => fn ($items) => $items->withPaymentTotals()])
            ->orderBy('issued_at')
            ->orderBy('id')
            ->get()
            ->filter(fn (Bill $bill): bool => $bill->outstandingAmount() > 0)
            ->groupBy('registrant_id')
            ->map(fn (Collection $bills): array => $bills->map(fn (Bill $bill): string => $bill->type->label())->unique()->values()->all())
            ->all();
    }
}
