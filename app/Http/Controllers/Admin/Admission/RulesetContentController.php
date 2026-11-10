<?php

namespace App\Http\Controllers\Admin\Admission;

use App\Actions\Admission\SyncBills;
use App\Actions\Admission\SyncExamAspects;
use App\Actions\Admission\SyncRulesetDocuments;
use App\Actions\Admission\SyncRulesetRequiredFields;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Admission\SaveExamAspectsRequest;
use App\Http\Requests\Admin\Admission\SaveRulesetBillsRequest;
use App\Http\Requests\Admin\Admission\SaveRulesetDocumentsRequest;
use App\Http\Requests\Admin\Admission\SaveRulesetRequiredFieldsRequest;
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

    public function documents(SaveRulesetDocumentsRequest $request, Ruleset $ruleset, SyncRulesetDocuments $syncRulesetDocuments): RedirectResponse
    {
        /** @var array<string, string> $choices */
        $choices = $request->validated('documents');

        $syncRulesetDocuments->handle($ruleset, $choices);

        return $this->saved('Berkas yang diminta sudah disimpan.');
    }

    public function requiredFields(SaveRulesetRequiredFieldsRequest $request, Ruleset $ruleset, SyncRulesetRequiredFields $syncRulesetRequiredFields): RedirectResponse
    {
        /** @var list<string> $fields */
        $fields = $request->validated('fields');

        $syncRulesetRequiredFields->handle($ruleset, $fields);

        return $this->saved('Data wajib sudah disimpan.');
    }

    private function saved(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
