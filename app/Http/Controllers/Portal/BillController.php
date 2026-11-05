<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\Payment;
use App\Models\Registrant;
use App\Support\BillPresenter;
use App\Support\RegistrantPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BillController extends Controller
{
    /**
     * The bills of all the parent's children, or of the one child in focus.
     */
    public function index(Request $request, ?Registrant $registrant = null): Response
    {
        $user = $request->user();

        if ($registrant !== null) {
            Gate::authorize('view', $registrant);
        }

        $bills = Bill::query()
            ->whereHas('registrant', fn (Builder $registrants) => $registrants->forParent($user)->when($registrant, fn (Builder $query) => $query->whereKey($registrant?->id)))
            ->with(['registrant.person', 'registrant.organization', 'items' => fn ($items) => $items->withPaymentTotals()])
            ->orderBy('registrant_id')
            ->orderBy('issued_at')
            ->get();

        $payments = Payment::query()
            ->where(fn (Builder $query) => $query->where('payer_person_id', $user->person_id)->orWhere('created_by_user_id', $user->id))
            ->when($registrant, fn (Builder $query) => $query->whereHas('allocations.billItem.bill', fn (Builder $bills) => $bills->where('registrant_id', $registrant?->id)))
            ->with(['payer', 'allocations.billItem.bill.registrant.person'])
            ->latest()
            ->get();

        return Inertia::render('portal/bills/index', [
            'focus' => $registrant === null ? null : RegistrantPresenter::summary($registrant),
            'bills' => $bills->map(fn (Bill $bill): array => BillPresenter::bill($bill))->values(),
            'payments' => $payments->map(fn (Payment $payment): array => BillPresenter::payment($payment))->values(),
            'canPay' => BillPresenter::openGroups($bills) !== [],
        ]);
    }
}
