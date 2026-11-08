<?php

namespace App\Http\Controllers\Portal;

use App\Actions\Billing\CreatePayment;
use App\Actions\Billing\PaymentData;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\StorePaymentRequest;
use App\Models\Bill;
use App\Models\Registrant;
use App\Support\BillPresenter;
use App\Support\RegistrantPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    /**
     * The form to confirm a payment. Bills of all children can be paid at once; `anak` limits it to one child.
     */
    public function create(Request $request): Response
    {
        $user = $request->user();
        $registrant = $request->filled('anak') ? Registrant::query()->findOrFail($request->integer('anak')) : null;

        if ($registrant !== null) {
            Gate::authorize('view', $registrant);
        }

        $bills = Bill::query()
            ->whereHas('registrant', fn (Builder $registrants) => $registrants->forParent($user)->when($registrant, fn (Builder $query) => $query->whereKey($registrant?->id)))
            ->with(['registrant.person', 'registrant.organization', 'items' => fn ($items) => $items->withPaymentTotals()])
            ->orderBy('registrant_id')
            ->orderBy('issued_at')
            ->get();

        return Inertia::render('portal/payments/create', [
            'focus' => $registrant === null ? null : RegistrantPresenter::summary($registrant),
            'groups' => BillPresenter::openGroups($bills),
            'methods' => array_map(fn (PaymentMethod $method): array => ['value' => $method->value, 'label' => $method->label()], PaymentMethod::selectable()),
        ]);
    }

    public function store(StorePaymentRequest $request, CreatePayment $createPayment): RedirectResponse
    {
        $user = $request->user();

        /** @var list<array{bill_item_id: int, amount: int}> $items */
        $items = $request->validated('items');

        $createPayment->handle(new PaymentData(
            payerPersonId: (int) $user->person_id,
            paidOn: $request->string('paid_on')->toString(),
            method: $request->enum('method', PaymentMethod::class) ?? PaymentMethod::Transfer,
            items: $items,
            createdBy: $user,
            note: $request->filled('note') ? $request->string('note')->toString() : null,
            proof: $request->file('proof'),
        ));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Konfirmasi pembayaran sudah terkirim. Panitia akan memeriksanya ya.']);

        return to_route('portal.bills.index');
    }
}
