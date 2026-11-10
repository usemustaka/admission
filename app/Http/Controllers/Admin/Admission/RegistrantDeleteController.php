<?php

namespace App\Http\Controllers\Admin\Admission;

use App\Actions\Admission\DeleteRegistrant;
use App\Enums\Ability;
use App\Http\Controllers\Controller;
use App\Models\Registrant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Throwable;

class RegistrantDeleteController extends Controller
{
    /** Registrants one bulk delete handles; each one is checked on its own. */
    public const MAX_IDS = 100;

    public function destroy(Request $request, Registrant $registrant, DeleteRegistrant $deleteRegistrant): RedirectResponse
    {
        Gate::authorize('delete', $registrant);

        $validated = $this->validated($request);
        $result = $deleteRegistrant->handle($registrant, $request->user(), $validated['reason'], (bool) ($validated['delete_account'] ?? false));

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$result['name']} sudah dihapus".($result['account_deleted'] ? ' beserta akun orang tuanya.' : '.')]);

        return to_route('admin.registrants.index');
    }

    /**
     * Delete the ticked registrants one by one. Each is checked on its own (placed ones and ones with payments are
     * skipped), so one that does not qualify never blocks the others.
     */
    public function bulk(Request $request, DeleteRegistrant $deleteRegistrant): RedirectResponse
    {
        abort_unless($request->user()->can(Ability::RegistrantDelete->value) && $request->user()->can('viewAny', Registrant::class), 403);

        $validated = $this->validated($request, withIds: true);
        $done = 0;
        $skipped = [];

        foreach (Registrant::query()->with('person')->whereIn('id', array_unique($request->array('ids')))->get() as $registrant) {
            try {
                $deleteRegistrant->handle($registrant, $request->user(), $validated['reason'], (bool) ($validated['delete_account'] ?? false));
                $done++;
            } catch (ValidationException $exception) {
                $skipped[] = $registrant->person->name.': '.(collect($exception->errors())->flatten()->first() ?? 'Tidak bisa dihapus');
            } catch (Throwable $exception) {
                report($exception);
                $skipped[] = $registrant->person->name.': Gagal, coba lagi';
            }
        }

        $message = "{$done} pendaftar dihapus".($skipped === [] ? '.' : ', '.count($skipped).' dilewati: '.implode('; ', array_slice($skipped, 0, 5)).'.');
        Inertia::flash('toast', ['type' => $skipped === [] ? 'success' : 'warning', 'message' => $message]);

        return back();
    }

    /**
     * @return array{reason: string, delete_account?: bool, ids?: list<int|string>}
     */
    private function validated(Request $request, bool $withIds = false): array
    {
        return $request->validate([
            'reason' => ['required', 'string', 'max:500'],
            'delete_account' => ['sometimes', 'boolean'],
            ...($withIds ? ['ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_IDS], 'ids.*' => ['integer']] : []),
        ], [
            'reason.required' => 'Tulis alasan menghapus pendaftar.',
            'ids.required' => 'Pilih minimal satu pendaftar.',
            'ids.max' => 'Paling banyak '.self::MAX_IDS.' pendaftar sekali hapus.',
        ], ['reason' => 'Alasan', 'ids' => 'Pendaftar']);
    }
}
