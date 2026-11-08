<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Registrant;
use App\Support\SettlementReceipt;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SettlementReceiptController extends Controller
{
    /**
     * The printable receipt that says a child's registration is fully paid. It exists only once every bill is paid.
     */
    public function __invoke(Registrant $registrant): Response
    {
        Gate::authorize('viewAny', Payment::class);

        abort_unless(SettlementReceipt::isAvailable($registrant), 404, 'Kwitansi lunas hanya tersedia setelah seluruh tagihan lunas.');

        return Inertia::render('payments/settlement-receipt', ['receipt' => SettlementReceipt::data($registrant)]);
    }
}
