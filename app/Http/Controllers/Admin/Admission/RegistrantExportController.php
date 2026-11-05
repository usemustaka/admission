<?php

namespace App\Http\Controllers\Admin\Admission;

use App\Http\Controllers\Controller;
use App\Models\Registrant;
use App\Support\ExamResult;
use App\Support\ExamSchedulePresenter;
use App\Support\RegistrantBillSummary;
use App\Support\RegistrantListFilters;
use App\Support\RegistrantPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RegistrantExportController extends Controller
{
    private const PAYMENT_LABELS = [
        'none' => 'Tanpa tagihan',
        'unpaid' => 'Belum dibayar',
        'partial' => 'Dibayar sebagian',
        'paid' => 'Lunas',
    ];

    /**
     * Every registrant matching the list's filters (all pages) as a CSV that Excel opens correctly: a UTF-8 BOM
     * up front, and the rows written in chunks so a long list does not sit in memory.
     */
    public function __invoke(Request $request): StreamedResponse
    {
        Gate::authorize('export', Registrant::class);

        $filters = RegistrantListFilters::fromRequest($request);

        $registrants = $filters->applySort($filters->apply(
            Registrant::query()
                ->select('registrants.*')
                ->with([
                    'person.parentships.relatedPerson',
                    'registeredBy',
                    'organization',
                    'wave',
                    'examSession.day',
                    'bills.items' => fn ($items) => $items->withPaymentTotals(),
                ]),
        ));

        return response()->streamDownload(function () use ($registrants): void {
            $out = fopen('php://output', 'w');

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Nama', 'NIK', 'Lembaga', 'Gelombang', 'Status', 'Perlu cek NIK', 'Pembayaran', 'Total tagihan', 'Sudah dibayar', 'Jadwal ujian', 'Hasil ujian', 'Nama kontak', 'Nomor HP kontak', 'Tanggal daftar'], escape: '');

            $registrants->lazy(200)->each(function (Registrant $registrant) use ($out): void {
                $totals = RegistrantBillSummary::totals($registrant->bills);
                $contact = RegistrantPresenter::contact($registrant);
                $passed = ExamResult::for($registrant)['passed'];

                fputcsv($out, [
                    $this->safe($registrant->person->name),
                    $this->asText($registrant->person->nik),
                    $this->safe($registrant->organization->name),
                    $this->safe($registrant->wave->name),
                    $registrant->status->label(),
                    $registrant->needs_identity_review ? 'Ya' : 'Tidak',
                    self::PAYMENT_LABELS[$totals['status']],
                    $totals['total'],
                    $totals['paid'],
                    $registrant->examSession === null ? '' : $registrant->examSession->day->date->toDateString().' '.ExamSchedulePresenter::label($registrant->examSession),
                    match ($passed) {
                        true => 'Tuntas',
                        false => 'Belum tuntas',
                        null => '',
                    },
                    $this->safe($contact['name'] ?? ''),
                    $this->asText($contact['phone'] ?? null),
                    $registrant->created_at?->toDateString() ?? '',
                ], escape: '');
            });

            fclose($out);
        }, 'pendaftar-'.now()->toDateString().'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Excel turns long digit strings (NIK) and phone numbers into numbers and drops leading zeros; written as a
     * text formula it keeps them as typed. Only values made of digits and phone punctuation are written this way, so nothing can be injected.
     */
    private function asText(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (preg_match('/^[\d\s+()-]+$/', $value) !== 1) {
            return $value;
        }

        return '="'.preg_replace('/\D/', '', $value).'"';
    }

    /**
     * Cells starting with a formula character would run as a formula in Excel; a leading apostrophe keeps them text.
     */
    private function safe(string $value): string
    {
        return $value !== '' && str_contains("=+-@\t\r", $value[0]) ? "'".$value : $value;
    }
}
