<?php

namespace App\Http\Controllers\Admin\Admission;

use App\Actions\Admission\SyncBills;
use App\Actions\Admission\SyncExamAspects;
use App\Actions\Admission\SyncExamSchedule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Admission\SaveExamAspectsRequest;
use App\Http\Requests\Admin\Admission\SaveExamScheduleRequest;
use App\Http\Requests\Admin\Admission\SaveRulesetBillsRequest;
use App\Models\Ruleset;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class RulesetContentController extends Controller
{
    public function bills(SaveRulesetBillsRequest $request, Ruleset $ruleset, SyncBills $syncBills): RedirectResponse
    {
        /** @var array<string, list<array{id?: int|null, name: string, amount: int}>> $bills */
        $bills = $request->validated('bills');

        $syncBills->handle($ruleset, $bills);

        return $this->saved('Tagihan sudah disimpan.');
    }

    public function examAspects(SaveExamAspectsRequest $request, Ruleset $ruleset, SyncExamAspects $syncExamAspects): RedirectResponse
    {
        /** @var list<array{id?: int|null, name: string, passing_score: int}> $aspects */
        $aspects = $request->validated('aspects');

        $syncExamAspects->handle($ruleset, $aspects);

        return $this->saved('Aspek ujian sudah disimpan.');
    }

    public function examSchedule(SaveExamScheduleRequest $request, Ruleset $ruleset, SyncExamSchedule $syncExamSchedule): RedirectResponse
    {
        /** @var list<array{id?: int|null, date: string, sessions: list<array{id?: int|null, label: string, start_time: string, end_time: string, quota: int}>}> $days */
        $days = $request->validated('days');

        $syncExamSchedule->handle($ruleset, $days);

        return $this->saved('Jadwal ujian sudah disimpan.');
    }

    private function saved(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
