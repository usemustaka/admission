<?php

namespace App\Http\Controllers\Admin\Admission;

use App\Actions\Admission\AcceptRegistrant;
use App\Actions\Admission\AnnounceRegistrant;
use App\Enums\RegistrantStatus;
use App\Http\Controllers\Controller;
use App\Models\Registrant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class RegistrantBulkController extends Controller
{
    public const MAX_IDS = 200;

    /**
     * Run one workflow step over the selected registrants. Every registrant is handled on its own with the same
     * action, status and NIK rules as the detail page, so one that does not qualify never blocks the others.
     */
    public function __invoke(Request $request, AcceptRegistrant $acceptRegistrant, AnnounceRegistrant $announceRegistrant): RedirectResponse
    {
        Gate::authorize('viewAny', Registrant::class);

        $validated = $request->validate([
            'action' => ['required', Rule::in(['accept', 'announce'])],
            'ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_IDS],
            'ids.*' => ['integer'],
        ], [
            'ids.required' => 'Pilih minimal satu pendaftar.',
            'ids.min' => 'Pilih minimal satu pendaftar.',
            'ids.max' => 'Paling banyak '.self::MAX_IDS.' pendaftar sekali proses.',
        ], ['action' => 'Aksi', 'ids' => 'Pendaftar']);

        $action = $validated['action'];
        $requiredStatus = $action === 'accept' ? RegistrantStatus::Review : RegistrantStatus::Validated;
        $user = $request->user();

        $registrants = Registrant::query()->with('person')->whereIn('id', $validated['ids'])->get()->keyBy('id');

        $done = 0;
        $skipped = [];

        foreach (array_unique(array_map('intval', $validated['ids'])) as $id) {
            $registrant = $registrants->get($id);

            if ($registrant === null) {
                $skipped[] = ['id' => $id, 'name' => 'Pendaftar #'.$id, 'reason' => 'Tidak ditemukan'];

                continue;
            }

            $reason = $this->skipReason($user, $registrant, $requiredStatus);

            if ($reason === null) {
                try {
                    $action === 'accept'
                        ? $acceptRegistrant->handle($registrant, $user)
                        : $announceRegistrant->handle($registrant, $user);
                    $done++;

                    continue;
                } catch (ValidationException $exception) {
                    $reason = collect($exception->errors())->flatten()->first() ?? 'Tidak bisa diproses';
                }
            }

            $skipped[] = ['id' => $registrant->id, 'name' => $registrant->person->name, 'reason' => $reason];
        }

        Inertia::flash('toast', ['type' => $skipped === [] ? 'success' : 'warning', 'message' => $this->summary($done, $skipped)]);
        Inertia::flash('bulk', ['action' => $action, 'done' => $done, 'skipped' => $skipped]);

        return back();
    }

    private function skipReason(User $user, Registrant $registrant, RegistrantStatus $requiredStatus): ?string
    {
        return match (true) {
            ! Gate::forUser($user)->allows('manage', $registrant) => 'Kamu tidak punya izin memproses pendaftar',
            $registrant->status !== $requiredStatus => "Statusnya {$registrant->status->label()}, bukan {$requiredStatus->label()}",
            $registrant->needs_identity_review => 'Perlu cek NIK dulu',
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
}
