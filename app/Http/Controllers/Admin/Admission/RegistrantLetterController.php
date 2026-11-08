<?php

namespace App\Http\Controllers\Admin\Admission;

use App\Http\Controllers\Controller;
use App\Models\Registrant;
use App\Support\AdmissionLetter;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RegistrantLetterController extends Controller
{
    /**
     * The printable acceptance letter of a child who passed. Children who did not pass, or whose data is not valid
     * yet, have none.
     */
    public function __invoke(Registrant $registrant): Response
    {
        Gate::authorize('announce', Registrant::class);

        abort_unless(AdmissionLetter::canPrint($registrant), 404, 'Surat penerimaan hanya tersedia untuk pendaftar yang tuntas dan datanya sudah valid.');

        return Inertia::render('letters/admission', ['letter' => AdmissionLetter::data($registrant)]);
    }
}
