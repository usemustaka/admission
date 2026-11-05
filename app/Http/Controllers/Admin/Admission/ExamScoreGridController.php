<?php

namespace App\Http\Controllers\Admin\Admission;

use App\Actions\Admission\SaveSessionScores;
use App\Enums\Ability;
use App\Enums\RegistrantStatus;
use App\Http\Controllers\Controller;
use App\Models\ExamSession;
use App\Models\Registrant;
use App\Support\ExamSessionStats;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ExamScoreGridController extends Controller
{
    /**
     * One row per registration of the session, one column per exam aspect. Staff who may only view see the
     * same grid without inputs.
     */
    public function edit(Request $request, ExamSession $session): Response
    {
        Gate::authorize('viewAny', Registrant::class);

        $registrants = Registrant::query()
            ->select('registrants.*')
            ->join('people', 'people.id', '=', 'registrants.person_id')
            ->where('registrants.exam_session_id', $session->id)
            ->with('person')
            ->orderBy('people.name')
            ->orderBy('registrants.id')
            ->get();

        $columns = $registrants
            ->map(fn (Registrant $registrant): array => $registrant->ruleset_snapshot['exam_aspects'] ?? [])
            ->sortByDesc(fn (array $aspects): int => count($aspects))
            ->first() ?? [];

        return Inertia::render('admin/exams/scores', [
            'session' => ExamSessionStats::describe($session),
            'aspects' => array_values($columns),
            'rows' => $registrants->map(function (Registrant $registrant): array {
                $aspects = $registrant->ruleset_snapshot['exam_aspects'] ?? [];

                return [
                    'id' => $registrant->id,
                    'name' => $registrant->person->name,
                    'nik' => $registrant->person->nik,
                    'photo_url' => $registrant->person->photo_url,
                    'status_label' => $registrant->status->label(),
                    'announced' => in_array($registrant->status, [RegistrantStatus::Announced, RegistrantStatus::Placed], true),
                    'aspects' => $aspects,
                    'scores' => collect($aspects)->keys()->map(fn (int $index): ?int => $registrant->exam_scores[$index] ?? null)->values(),
                ];
            })->values(),
            'can' => [
                'manage' => $request->user()->can(Ability::RegistrantManage->value),
            ],
        ]);
    }

    /**
     * Save the changed rows. Rows that fail are reported one by one and leave the other rows saved.
     */
    public function update(Request $request, ExamSession $session, SaveSessionScores $saveSessionScores): RedirectResponse
    {
        Gate::authorize('manage', Registrant::class);

        $validated = $request->validate([
            'rows' => ['required', 'array', 'min:1', 'max:500'],
            'rows.*' => ['array'],
            'confirm_after_announce' => ['sometimes', 'boolean'],
        ], [], ['rows' => 'Nilai']);

        $result = $saveSessionScores->handle($session, $validated['rows'], $request->user(), $request->boolean('confirm_after_announce'));
        $failed = count($result['errors']);

        if ($failed > 0) {
            return back()->withErrors([
                ...collect($result['errors'])->mapWithKeys(fn (string $message, int $id): array => ["rows.{$id}" => $message])->all(),
                'summary' => "{$result['saved']} baris tersimpan, {$failed} baris gagal. Cek baris yang ditandai merah.",
            ]);
        }

        Inertia::flash('toast', $result['saved'] === 0
            ? ['type' => 'info', 'message' => 'Tidak ada nilai yang berubah.']
            : ['type' => 'success', 'message' => "Nilai {$result['saved']} pendaftar sudah disimpan."]);

        return back();
    }
}
