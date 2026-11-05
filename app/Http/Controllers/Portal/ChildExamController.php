<?php

namespace App\Http\Controllers\Portal;

use App\Actions\Admission\ChooseExamSession;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\ChooseExamSessionRequest;
use App\Models\ExamSession;
use App\Models\Registrant;
use App\Support\ExamSchedulePresenter;
use App\Support\RegistrantPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ChildExamController extends Controller
{
    public function edit(Registrant $registrant): Response|RedirectResponse
    {
        Gate::authorize('view', $registrant);

        if ($registrant->needs_identity_review) {
            Inertia::flash('toast', ['type' => 'info', 'message' => 'Panitia masih mencocokkan data anak ini, jadi jadwal belum bisa dipilih.']);

            return to_route('portal.children.show', $registrant);
        }

        return Inertia::render('portal/children/exam', [
            'registrant' => RegistrantPresenter::summary($registrant),
            'days' => ExamSchedulePresenter::days($registrant),
            'selected' => $registrant->exam_session_id === null ? null : (string) $registrant->exam_session_id,
            'canEdit' => Gate::allows('update', $registrant),
        ]);
    }

    public function update(ChooseExamSessionRequest $request, Registrant $registrant, ChooseExamSession $chooseExamSession): RedirectResponse
    {
        $chooseExamSession->handle($registrant, ExamSession::query()->findOrFail($request->integer('exam_session_id')));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Jadwal ujian sudah dipilih.']);

        return to_route('portal.children.show', $registrant);
    }
}
