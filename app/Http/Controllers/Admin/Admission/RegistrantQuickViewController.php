<?php

namespace App\Http\Controllers\Admin\Admission;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\Parentship;
use App\Models\Registrant;
use App\Support\Announcement;
use App\Support\AnnouncementReadiness;
use App\Support\ExamResult;
use App\Support\ExamSchedulePresenter;
use App\Support\RegistrantBillSummary;
use App\Support\RegistrantPresenter;
use App\Support\RegistrantReadiness;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RegistrantQuickViewController extends Controller
{
    /**
     * A short summary of one registrant for the side panel of the list: enough to decide what to do next
     * without opening the detail page.
     */
    public function __invoke(Request $request, Registrant $registrant): JsonResponse
    {
        Gate::authorize('viewAny', Registrant::class);

        $registrant->load([
            'person.parentships.relatedPerson',
            'registeredBy',
            'organization',
            'wave',
            'bills.items' => fn ($items) => $items->withPaymentTotals(),
        ]);

        $person = $registrant->person;
        $status = $registrant->status;
        $can = RegistrantPresenter::abilities($registrant, $request->user());

        return response()->json([
            'id' => $registrant->id,
            'status' => $status->value,
            'status_label' => $status->label(),
            'needs_identity_review' => $registrant->needs_identity_review,
            'organization' => $registrant->organization->name,
            'wave' => $registrant->wave->name,
            'registered_at' => $registrant->created_at?->toIso8601String(),
            'child' => [
                'name' => $person->name,
                'nik' => $person->nik,
                'gender' => $person->gender?->label(),
                'birth' => collect([$person->birth_place, $person->birth_date?->translatedFormat('j F Y')])->filter()->implode(', ') ?: null,
                'photo_url' => $person->photo_url,
            ],
            'family' => $person->parentships->map(fn (Parentship $parentship): array => [
                'id' => $parentship->id,
                'name' => $parentship->relatedPerson->name,
                'relation' => $parentship->relation->label(),
                'phone' => $parentship->relatedPerson->phone,
                'whatsapp_url' => blank($parentship->relatedPerson->phone) ? null : 'https://wa.me/'.Announcement::whatsappNumber($parentship->relatedPerson->phone),
                'is_primary_contact' => $parentship->is_primary_contact,
            ])->values(),
            'contact' => RegistrantPresenter::contact($registrant),
            'messages' => RegistrantPresenter::messages($registrant),
            'bills' => [
                ...RegistrantBillSummary::totals($registrant->bills),
                'items' => $registrant->bills->map(fn (Bill $bill): array => [
                    'id' => $bill->id,
                    'type_label' => $bill->type->label(),
                    'status' => $bill->status()->value,
                    'total' => $bill->totalAmount(),
                    'paid' => $bill->paidAmount(),
                ])->values(),
            ],
            'exam' => [
                'session' => ExamSchedulePresenter::chosen($registrant),
                'result' => ExamResult::for($registrant),
            ],
            'readiness' => RegistrantReadiness::forCommittee($registrant),
            'announce_warnings' => $can['announce'] ? AnnouncementReadiness::for($registrant)['messages'] : [],
            'timeline' => array_reverse(array_slice(RegistrantPresenter::timeline($registrant, forParent: false), -4)),
            'can' => $can,
        ]);
    }
}
