<?php

namespace App\Http\Controllers\Admin\Admission;

use App\Actions\Admission\AcceptRegistrant;
use App\Actions\Admission\AnnounceRegistrant;
use App\Actions\Admission\RequestRevision;
use App\Enums\RegistrantStatus;
use App\Http\Controllers\Controller;
use App\Models\Registrant;
use App\Models\User;
use App\Support\AnnouncementReadiness;
use App\Support\RegistrantListFilters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Throwable;

class RegistrantBulkController extends Controller
{
    /** Registrants that can be picked one by one on a page. */
    public const MAX_IDS = 200;

    /** Registrants one "select all results" run handles; the rest wait for a second run. */
    public const MAX_FILTERED = 1000;

    /** How many registrants are loaded at a time while a run goes through them. */
    private const CHUNK = 100;

    /** How many skipped registrants are listed by name; the count and reasons always cover all of them. */
    private const MAX_SKIPPED_LISTED = 200;

    private const ACTIONS = ['accept', 'announce', 'revise'];

    /**
     * Run one workflow step over the selected registrants, picked by id or as every result of the list's filters.
     * Every registrant is handled on its own with the same action, status and NIK rules as the detail page, so
     * one that does not qualify never blocks the others.
     */
    public function __invoke(Request $request, AcceptRegistrant $acceptRegistrant, AnnounceRegistrant $announceRegistrant, RequestRevision $requestRevision): RedirectResponse
    {
        Gate::authorize('bulk', Registrant::class);

        $validated = $this->validated($request, forRun: true);
        $action = $validated['action'];
        $target = $this->target($request, $validated);
        $user = $request->user();
        $includeIncomplete = $request->boolean('include_incomplete');
        $note = trim((string) ($validated['note'] ?? ''));

        if ($target['filtered']) {
            set_time_limit(0);
        }

        $done = 0;
        $skipped = [];

        foreach (array_chunk($target['ids'], self::CHUNK) as $ids) {
            $registrants = Registrant::query()->with('person')->whereIn('id', $ids)->get()->keyBy('id');

            foreach ($ids as $id) {
                $registrant = $registrants->get($id);

                if ($registrant === null) {
                    $skipped[] = ['id' => $id, 'name' => 'Pendaftar #'.$id, 'reason' => 'Tidak ditemukan'];

                    continue;
                }

                $reason = $this->process($user, $registrant, $action, $includeIncomplete, $note, $acceptRegistrant, $announceRegistrant, $requestRevision);

                if ($reason === null) {
                    $done++;

                    continue;
                }

                $skipped[] = ['id' => $registrant->id, 'name' => $registrant->person->name, 'reason' => $reason];
            }
        }

        Inertia::flash('toast', ['type' => $skipped === [] ? 'success' : 'warning', 'message' => $this->summary($done, $skipped).$this->capNote($target)]);
        Inertia::flash('bulk', [
            'action' => $action,
            'done' => $done,
            'skipped' => array_slice($skipped, 0, self::MAX_SKIPPED_LISTED),
            'skipped_total' => count($skipped),
        ]);

        return back();
    }

    /**
     * What a run would do, without changing anything: how many registrants qualify, how many are skipped and the
     * most common reasons. It uses the same rules as the run, so the numbers match what the run then does.
     */
    public function preview(Request $request): JsonResponse
    {
        Gate::authorize('bulk', Registrant::class);

        $validated = $this->validated($request, forRun: false);
        $action = $validated['action'];
        $target = $this->target($request, $validated);
        $user = $request->user();
        $includeIncomplete = $request->boolean('include_incomplete');

        $eligible = 0;
        $reasons = [];
        $readinessCounts = ['missing_scores' => 0, 'not_passed' => 0, 'unsettled' => 0, 'incomplete' => 0];

        foreach (array_chunk($target['ids'], self::CHUNK) as $ids) {
            $registrants = Registrant::query()
                ->with($action === 'announce' ? ['bills.items' => fn ($items) => $items->withPaymentTotals()] : [])
                ->whereIn('id', $ids)
                ->get()
                ->keyBy('id');

            foreach ($ids as $id) {
                $registrant = $registrants->get($id);
                $reason = $registrant === null
                    ? 'Tidak ditemukan'
                    : $this->skipReason($user, $registrant, $action, includeIncomplete: true);

                if ($reason === null && $action === 'announce') {
                    $readiness = AnnouncementReadiness::for($registrant);

                    if ($readiness['messages'] !== []) {
                        $readinessCounts['incomplete']++;

                        foreach (['missing_scores', 'not_passed', 'unsettled'] as $flag) {
                            $readinessCounts[$flag] += $readiness[$flag] ? 1 : 0;
                        }

                        $reason = $includeIncomplete ? null : AnnouncementReadiness::reason($readiness);
                    }
                }

                if ($reason === null) {
                    $eligible++;

                    continue;
                }

                $reasons[$reason] = ($reasons[$reason] ?? 0) + 1;
            }
        }

        arsort($reasons);

        return response()->json([
            'action' => $action,
            'total' => $target['total'],
            'processed' => count($target['ids']),
            'capped' => $target['capped'],
            'cap' => self::MAX_FILTERED,
            'eligible' => $eligible,
            'skipped' => count($target['ids']) - $eligible,
            'reasons' => collect($reasons)->take(5)->map(fn (int $count, string $reason): array => ['reason' => $reason, 'count' => $count])->values(),
            'readiness' => $action === 'announce' ? $readinessCounts : null,
        ]);
    }

    /**
     * @return array{action: string, ids?: list<int|string>, select_all?: bool, filters?: array<string, mixed>, note?: string|null, include_incomplete?: bool}
     */
    private function validated(Request $request, bool $forRun): array
    {
        $selectAll = $request->boolean('select_all');

        return $request->validate([
            'action' => ['required', Rule::in(self::ACTIONS)],
            'select_all' => ['sometimes', 'boolean'],
            'ids' => [Rule::requiredIf(! $selectAll), 'array', 'min:1', 'max:'.self::MAX_IDS],
            'ids.*' => ['integer'],
            'filters' => ['nullable', 'array'],
            'include_incomplete' => ['sometimes', 'boolean'],
            'note' => [Rule::requiredIf($forRun && $request->input('action') === 'revise'), 'nullable', 'string', 'max:1000'],
        ], [
            'ids.required' => 'Pilih minimal satu pendaftar.',
            'ids.min' => 'Pilih minimal satu pendaftar.',
            'ids.max' => 'Paling banyak '.self::MAX_IDS.' pendaftar sekali proses.',
            'note.required' => 'Tulis apa yang perlu diperbaiki.',
        ], ['action' => 'Aksi', 'ids' => 'Pendaftar', 'note' => 'Alasan']);
    }

    /**
     * Who the run is about: the ids picked one by one, or every registrant matching the filters (the oldest
     * {@see self::MAX_FILTERED} by id, so a preview and the run that follows see the same rows).
     *
     * @param  array<string, mixed>  $validated
     * @return array{ids: list<int>, total: int, capped: bool, filtered: bool}
     */
    private function target(Request $request, array $validated): array
    {
        if (! $request->boolean('select_all')) {
            $ids = array_values(array_unique(array_map('intval', $validated['ids'])));

            return ['ids' => $ids, 'total' => count($ids), 'capped' => false, 'filtered' => false];
        }

        $matching = RegistrantListFilters::fromArray($validated['filters'] ?? [])->apply(Registrant::query());
        $total = (clone $matching)->count();

        /** @var list<int> $ids */
        $ids = $matching->orderBy('registrants.id')->limit(self::MAX_FILTERED)->pluck('registrants.id')->map(fn (mixed $id): int => (int) $id)->all();

        return ['ids' => $ids, 'total' => $total, 'capped' => $total > self::MAX_FILTERED, 'filtered' => true];
    }

    /**
     * Run the step for one registrant in its own transaction, checking its status and NIK flag on the row as it
     * is stored right now (under a lock), not as it was when the list was loaded. Returns why it was skipped, or
     * null when it went through. Anything unexpected only skips this registrant.
     */
    private function process(User $user, Registrant $registrant, string $action, bool $includeIncomplete, string $note, AcceptRegistrant $acceptRegistrant, AnnounceRegistrant $announceRegistrant, RequestRevision $requestRevision): ?string
    {
        try {
            return DB::transaction(function () use ($user, $registrant, $action, $includeIncomplete, $note, $acceptRegistrant, $announceRegistrant, $requestRevision): ?string {
                $fresh = Registrant::query()->lockForUpdate()->findOrFail($registrant->id);

                $reason = $this->skipReason($user, $fresh, $action, $includeIncomplete);

                if ($reason !== null) {
                    return $reason;
                }

                match ($action) {
                    'accept' => $acceptRegistrant->handle($fresh, $user),
                    'announce' => $announceRegistrant->handle($fresh, $user, requireComplete: ! $includeIncomplete),
                    'revise' => $requestRevision->handle($fresh, $user, $note),
                };

                return null;
            });
        } catch (ValidationException $exception) {
            return collect($exception->errors())->flatten()->first() ?? 'Tidak bisa diproses';
        } catch (Throwable $exception) {
            report($exception);

            return 'Gagal, coba lagi';
        }
    }

    /**
     * Why this registrant cannot take the step right now, or null when it can. Nothing is changed here.
     */
    private function skipReason(User $user, Registrant $registrant, string $action, bool $includeIncomplete): ?string
    {
        $requiredStatus = $action === 'announce' ? RegistrantStatus::Validated : RegistrantStatus::Review;

        return match (true) {
            ! Gate::forUser($user)->allows('manage', $registrant) => 'Kamu tidak punya izin memproses pendaftar',
            $registrant->status !== $requiredStatus => "Statusnya {$registrant->status->label()}, bukan {$requiredStatus->label()}",
            $registrant->needs_identity_review => 'Perlu cek NIK dulu',
            $action === 'announce' && ! $includeIncomplete => AnnouncementReadiness::incompleteReason($registrant),
            default => null,
        };
    }

    /**
     * @param  list<array{id: int, name: string, reason: string}>  $skipped
     */
    private function summary(int $done, array $skipped): string
    {
        $message = "{$done} berhasil, ".count($skipped).' dilewati';

        if ($skipped === []) {
            return $message.'.';
        }

        $reasons = collect($skipped)->countBy('reason')->map(fn (int $count, string $reason): string => "{$reason} ({$count})")->implode('; ');

        return "{$message}: {$reasons}.";
    }

    /**
     * @param  array{total: int, capped: bool}  $target
     */
    private function capNote(array $target): string
    {
        return $target['capped']
            ? ' Hanya '.self::MAX_FILTERED." dari {$target['total']} hasil yang diproses sekali jalan; jalankan lagi untuk sisanya."
            : '';
    }
}
