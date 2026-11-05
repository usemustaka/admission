<?php

namespace App\Http\Controllers\Admin\Admission;

use App\Actions\Admission\AcceptRegistrant;
use App\Actions\Admission\AnnounceRegistrant;
use App\Actions\Admission\BypassRegistrantStatus;
use App\Actions\Admission\ChooseExamSession;
use App\Actions\Admission\PlaceRegistrant;
use App\Actions\Admission\RepointRegistrantIdentity;
use App\Actions\Admission\RequestRevision;
use App\Actions\Admission\ResolveIdentityReview;
use App\Actions\Admission\SaveExamScores;
use App\Enums\RegistrantStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\ChooseExamSessionRequest;
use App\Models\ExamSession;
use App\Models\Registrant;
use App\Models\Year;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class RegistrantWorkflowController extends Controller
{
    public function accept(Request $request, Registrant $registrant, AcceptRegistrant $acceptRegistrant): RedirectResponse
    {
        Gate::authorize('manage', $registrant);

        $acceptRegistrant->handle($registrant, $request->user());

        return $this->done('Data diterima. Tagihan pelunasan terbit kalau aturannya punya.');
    }

    public function revise(Request $request, Registrant $registrant, RequestRevision $requestRevision): RedirectResponse
    {
        Gate::authorize('manage', $registrant);

        $validated = $request->validate(['note' => ['required', 'string', 'max:1000']], [], ['note' => 'Alasan']);

        $requestRevision->handle($registrant, $request->user(), $validated['note']);

        return $this->done('Data dikembalikan ke orang tua beserta alasannya.');
    }

    public function bypass(Request $request, Registrant $registrant, BypassRegistrantStatus $bypassRegistrantStatus): RedirectResponse
    {
        Gate::authorize('bypass', Registrant::class);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(RegistrantStatus::class)],
            'note' => ['required', 'string', 'max:1000'],
        ], [], ['status' => 'Status', 'note' => 'Catatan']);

        $bypassRegistrantStatus->handle($registrant, $request->user(), RegistrantStatus::from($validated['status']), $validated['note']);

        return $this->done('Status sudah dipindahkan dan tercatat di riwayat.');
    }

    public function examSession(ChooseExamSessionRequest $request, Registrant $registrant, ChooseExamSession $chooseExamSession): RedirectResponse
    {
        $chooseExamSession->handle(
            $registrant,
            ExamSession::query()->findOrFail($request->integer('exam_session_id')),
            by: $request->user(),
            overrideFull: $request->boolean('override_full'),
            overrideReason: $request->input('override_reason'),
        );

        return $this->done('Jadwal ujian sudah diubah.');
    }

    public function scores(Request $request, Registrant $registrant, SaveExamScores $saveExamScores): RedirectResponse
    {
        Gate::authorize('manage', $registrant);

        $validated = $request->validate([
            'scores' => ['present', 'array'],
            'scores.*' => ['nullable', 'integer', 'between:0,100'],
            'confirm_after_announce' => ['sometimes', 'boolean'],
        ], [], ['scores.*' => 'Nilai']);

        $saveExamScores->handle($registrant, $validated['scores'], $request->user(), $request->boolean('confirm_after_announce'));

        return $this->done('Nilai ujian sudah disimpan.');
    }

    public function announce(Request $request, Registrant $registrant, AnnounceRegistrant $announceRegistrant): RedirectResponse
    {
        Gate::authorize('manage', $registrant);

        $announceRegistrant->handle($registrant, $request->user());

        return $this->done('Pendaftar ditandai sudah diumumkan.');
    }

    public function place(Request $request, Registrant $registrant, PlaceRegistrant $placeRegistrant): RedirectResponse
    {
        Gate::authorize('place', Registrant::class);

        $validated = $request->validate([
            'year_id' => ['required', 'integer', 'exists:years,id'],
            'nis' => ['required', 'string', 'max:50'],
        ], [], ['year_id' => 'Tahun ajaran', 'nis' => 'Nomor induk siswa']);

        $placeRegistrant->handle($registrant, $request->user(), Year::query()->findOrFail((int) $validated['year_id']), $validated['nis']);

        return $this->done('Siswa sudah ditempatkan.');
    }

    public function resolveIdentity(Request $request, Registrant $registrant, ResolveIdentityReview $resolveIdentityReview): RedirectResponse
    {
        Gate::authorize('manage', $registrant);

        $resolveIdentityReview->handle($registrant, $request->user());

        return $this->done('Penandaan selesai. Orang tua sekarang tertaut ke anak ini.');
    }

    public function repointIdentity(Request $request, Registrant $registrant, RepointRegistrantIdentity $repointRegistrantIdentity): RedirectResponse
    {
        Gate::authorize('manage', $registrant);

        $validated = $request->validate([
            'nik' => ['required', 'digits:16'],
            'reason' => ['required', 'string', 'max:1000'],
        ], [], ['nik' => 'NIK', 'reason' => 'Alasan']);

        $repointRegistrantIdentity->handle($registrant, $request->user(), $validated['nik'], $validated['reason']);

        return $this->done('Pendaftaran sudah dialihkan ke NIK yang benar.');
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
