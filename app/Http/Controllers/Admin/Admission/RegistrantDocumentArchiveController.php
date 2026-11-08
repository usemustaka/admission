<?php

namespace App\Http\Controllers\Admin\Admission;

use App\Http\Controllers\Controller;
use App\Models\Registrant;
use App\Support\RegistrantDocumentArchive;
use App\Support\RegistrantListFilters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RegistrantDocumentArchiveController extends Controller
{
    /** The most registrations packed into one ZIP, so a request stays within time and disk limits. */
    public const MAX_REGISTRANTS = 100;

    /**
     * All files of one registration as a ZIP.
     */
    public function show(Registrant $registrant): BinaryFileResponse|RedirectResponse
    {
        Gate::authorize('viewAny', Registrant::class);

        $registrant->load(['person', 'documents']);

        return $this->send(collect([$registrant]), 'berkas-'.Str::slug($registrant->person->name));
    }

    /**
     * The files of the registrations matching the list's filters, or only the ones picked in the list
     * (`selected_only` with `ids[]`), as one ZIP with a folder per child.
     */
    public function index(Request $request): BinaryFileResponse|RedirectResponse
    {
        Gate::authorize('export', Registrant::class);

        $validated = $request->validate([
            'selected_only' => ['sometimes', 'boolean'],
            'ids' => ['required_if:selected_only,1,true', 'array', 'max:'.self::MAX_REGISTRANTS],
            'ids.*' => ['integer'],
        ], [
            'ids.required_if' => 'Pilih minimal satu pendaftar untuk diunduh berkasnya.',
            'ids.max' => 'Paling banyak '.self::MAX_REGISTRANTS.' pendaftar yang dipilih sekali unduh.',
        ], ['ids' => 'Pendaftar']);

        $filters = RegistrantListFilters::fromRequest($request);

        $query = $request->boolean('selected_only')
            ? Registrant::query()->select('registrants.*')->whereIn('registrants.id', array_map('intval', $validated['ids']))
            : $filters->apply(Registrant::query()->select('registrants.*'));

        $total = (clone $query)->whereHas('documents')->count();

        if ($total > self::MAX_REGISTRANTS) {
            Inertia::flash('toast', ['type' => 'error', 'message' => "Ada {$total} pendaftar dengan berkas, lebih dari batas ".self::MAX_REGISTRANTS.' sekali unduh. Persempit filter dulu, misalnya per lembaga atau status.']);

            return back();
        }

        return $this->send($filters->applySort($query->whereHas('documents')->with(['person', 'documents']))->get(), 'berkas-pendaftar-'.today()->toDateString());
    }

    /**
     * @param  Collection<int, Registrant>  $registrants
     */
    private function send($registrants, string $name): BinaryFileResponse|RedirectResponse
    {
        $path = RegistrantDocumentArchive::build($registrants);

        if ($path === null) {
            Inertia::flash('toast', ['type' => 'info', 'message' => 'Belum ada berkas yang bisa diunduh.']);

            return back();
        }

        return response()->download($path, "{$name}.zip", ['Content-Type' => 'application/zip'])->deleteFileAfterSend();
    }
}
