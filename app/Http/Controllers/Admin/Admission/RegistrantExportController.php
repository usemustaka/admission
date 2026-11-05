<?php

namespace App\Http\Controllers\Admin\Admission;

use App\Enums\ParentRelation;
use App\Http\Controllers\Controller;
use App\Models\Parentship;
use App\Models\Registrant;
use App\Support\CsvCell;
use App\Support\ExamResult;
use App\Support\ExamSchedulePresenter;
use App\Support\RegistrantBillSummary;
use App\Support\RegistrantListFilters;
use App\Support\RegistrantPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RegistrantExportController extends Controller
{
    private const PAYMENT_LABELS = [
        'none' => 'Tanpa tagihan',
        'unpaid' => 'Belum dibayar',
        'partial' => 'Dibayar sebagian',
        'paid' => 'Lunas',
    ];

    private const TIMEZONE = 'Asia/Jakarta';

    /**
     * The registrants matching the list's filters (all pages), or only the ones picked in the list (`selected_only`
     * with `ids[]`), as a CSV that Excel opens correctly: a UTF-8 BOM up front, and the rows written in chunks so
     * a long list does not sit in memory. The first columns stay as they were; the extra details and one column
     * per exam aspect come after them.
     */
    public function __invoke(Request $request): StreamedResponse
    {
        Gate::authorize('export', Registrant::class);

        $validated = $request->validate([
            'selected_only' => ['sometimes', 'boolean'],
            'ids' => ['required_if:selected_only,1,true', 'array', 'max:'.RegistrantBulkController::MAX_IDS],
            'ids.*' => ['integer'],
        ], [
            'ids.required_if' => 'Pilih minimal satu pendaftar untuk diekspor.',
            'ids.max' => 'Paling banyak '.RegistrantBulkController::MAX_IDS.' pendaftar yang dipilih sekali ekspor.',
        ], ['ids' => 'Pendaftar']);

        $filters = RegistrantListFilters::fromRequest($request);
        $selectedIds = $request->boolean('selected_only') ? array_values(array_unique(array_map('intval', $validated['ids']))) : null;

        $matching = fn (): Builder => $selectedIds === null
            ? $filters->apply(Registrant::query()->select('registrants.*'))
            : Registrant::query()->select('registrants.*')->whereIn('registrants.id', $selectedIds);

        $aspectNames = $this->aspectNames($matching());

        $registrants = $filters->applySort($matching()->with([
            'person.parentships.relatedPerson',
            'registeredBy',
            'organization',
            'wave',
            'examSession.day',
            'bills.items' => fn ($items) => $items->withPaymentTotals(),
        ]));

        return response()->streamDownload(function () use ($registrants, $aspectNames): void {
            $out = fopen('php://output', 'w');

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'Nama', 'NIK', 'Lembaga', 'Gelombang', 'Status', 'Perlu cek NIK', 'Pembayaran', 'Total tagihan', 'Sudah dibayar', 'Jadwal ujian', 'Hasil ujian', 'Nama kontak', 'Nomor HP kontak', 'Tanggal daftar',
                'Nama diketik orang tua', 'Tempat lahir', 'Tanggal lahir', 'NISN', 'Alamat',
                'Nama ayah', 'HP ayah', 'Nama ibu', 'HP ibu', 'Nama wali', 'HP wali',
                'Tanggal kirim',
                ...array_map(fn (string $name): string => CsvCell::safe("Nilai {$name}"), $aspectNames),
            ], escape: '');

            $registrants->lazy(200)->each(function (Registrant $registrant) use ($out, $aspectNames): void {
                $totals = RegistrantBillSummary::totals($registrant->bills);
                $contact = RegistrantPresenter::contact($registrant);
                $result = ExamResult::for($registrant);
                $person = $registrant->person;
                $scores = collect($result['aspects'])->reverse()->mapWithKeys(fn (array $aspect): array => [$aspect['name'] => $aspect['score']]);

                fputcsv($out, [
                    CsvCell::safe($person->name),
                    $this->asText($person->nik),
                    CsvCell::safe($registrant->organization->name),
                    CsvCell::safe($registrant->wave->name),
                    $registrant->status->label(),
                    $registrant->needs_identity_review ? 'Ya' : 'Tidak',
                    self::PAYMENT_LABELS[$totals['status']],
                    $totals['total'],
                    $totals['paid'],
                    $registrant->examSession === null ? '' : CsvCell::safe($registrant->examSession->day->date->toDateString().' '.ExamSchedulePresenter::label($registrant->examSession)),
                    match ($result['passed']) {
                        true => 'Tuntas',
                        false => 'Belum tuntas',
                        null => '',
                    },
                    CsvCell::safe($contact['name'] ?? ''),
                    $this->asText($contact['phone'] ?? null),
                    $registrant->created_at?->timezone(self::TIMEZONE)->toDateString() ?? '',
                    CsvCell::safe($registrant->submitted_name),
                    CsvCell::safe($person->birth_place ?? ''),
                    $person->birth_date?->toDateString() ?? '',
                    $this->asText($person->nisn),
                    CsvCell::safe($person->address ?? ''),
                    ...$this->parentCells($registrant, ParentRelation::Father),
                    ...$this->parentCells($registrant, ParentRelation::Mother),
                    ...$this->parentCells($registrant, ParentRelation::Guardian),
                    $registrant->submitted_at?->timezone(self::TIMEZONE)->format('Y-m-d H:i') ?? '',
                    ...array_map(fn (string $name): mixed => $scores[$name] ?? '', $aspectNames),
                ], escape: '');
            });

            fclose($out);
        }, $this->fileName($filters, $selectedIds), ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * The exam aspects of everyone in the export, by name in the order they first appear. Each registration
     * keeps its own copy of the ruleset, so exports that mix rulesets get the union of their aspects.
     *
     * @param  Builder<Registrant>  $matching
     * @return list<string>
     */
    private function aspectNames(Builder $matching): array
    {
        $names = [];

        $matching->reorder()->select('registrants.id', 'registrants.ruleset_snapshot')->whereNotNull('registrants.ruleset_snapshot')
            ->lazyById(500, 'registrants.id', 'id')
            ->each(function (Registrant $registrant) use (&$names): void {
                foreach ($registrant->ruleset_snapshot['exam_aspects'] ?? [] as $aspect) {
                    $names[$aspect['name']] = true;
                }
            });

        return array_map('strval', array_keys($names));
    }

    /**
     * The name and phone of the first father, mother or guardian on record, as two cells.
     *
     * @return array{0: string, 1: string}
     */
    private function parentCells(Registrant $registrant, ParentRelation $relation): array
    {
        $parent = $registrant->person->parentships->first(fn (Parentship $parentship): bool => $parentship->relation === $relation)?->relatedPerson;

        return [CsvCell::safe($parent?->name ?? ''), $this->asText($parent?->phone)];
    }

    /**
     * Excel turns long digit strings (NIK) and phone numbers into numbers and drops leading zeros; written as a
     * text formula it keeps them as typed. Only values made of digits and phone punctuation are written this way, so
     * nothing can be injected; any other value goes through `CsvCell::safe` like every other text cell.
     */
    private function asText(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (preg_match('/^[\d\s+()-]*\d[\d\s+()-]*$/', $value) !== 1) {
            return CsvCell::safe($value);
        }

        return '="'.preg_replace('/\D/', '', $value).'"';
    }

    /**
     * ASCII only, with what the list was narrowed to and the date in WIB, so a file saved weeks ago still says
     * what it holds. `pendaftar-review-lembaga-sd-2026-11-05.csv`, or `pendaftar-terpilih-12-2026-11-05.csv`.
     *
     * @param  list<int>|null  $selectedIds
     */
    private function fileName(RegistrantListFilters $filters, ?array $selectedIds): string
    {
        $parts = ['pendaftar'];

        if ($selectedIds !== null) {
            $parts[] = 'terpilih-'.count($selectedIds);
        } else {
            $parts[] = $filters->status?->value;
            $parts[] = $filters->organizationId > 0 ? 'lembaga-'.$filters->organizationId : null;
            $parts[] = $filters->waveId > 0 ? 'gelombang-'.$filters->waveId : null;
            $parts[] = $filters->needsIdentityReview ? 'cek-nik' : null;
            $parts[] = $filters->payment !== '' ? 'bayar-'.$filters->payment : null;
            $parts[] = $filters->withoutExamSession ? 'belum-ujian' : null;
            $parts[] = $filters->term !== '' ? 'cari-'.Str::limit(Str::slug($filters->term), 20, '') : null;
        }

        $parts[] = now(self::TIMEZONE)->toDateString();

        return implode('-', array_filter($parts, fn (?string $part): bool => $part !== null && $part !== '' && $part !== 'cari-')).'.csv';
    }
}
