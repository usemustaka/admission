<?php

namespace App\Support;

use App\Enums\Ability;
use App\Enums\AdmissionType;
use App\Enums\DefaultRole;
use App\Enums\EducationLevel;
use App\Enums\Gender;
use App\Enums\NoteUrgency;
use App\Enums\NoteVisibility;
use App\Enums\ParentKind;
use App\Enums\ParentRelation;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RegistrantStatus;
use App\Models\EducationHistory;
use App\Models\Note;
use App\Models\NoteShare;
use App\Models\Parentship;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Person;
use App\Models\Registrant;
use App\Models\RegistrantStatusChange;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Permission\Models\Role;

class RegistrantPresenter
{
    /**
     * The child's name as a parent may see it. While the committee confirms the NIK, that is only the name the
     * parent typed, never the one already on record.
     */
    public static function childName(Registrant $registrant): string
    {
        return $registrant->needs_identity_review ? $registrant->submitted_name : $registrant->person->name;
    }

    public static function childPhotoUrl(Registrant $registrant): ?string
    {
        return $registrant->needs_identity_review ? null : $registrant->person->photo_url;
    }

    /**
     * What the committee member may do with a registration right now: the workflow actions are only offered when
     * the status and the NIK check allow them. While the NIK is unconfirmed nothing moves forward and nothing is
     * sent back (the parent cannot edit yet); `bypass` stays, but the status picker leaves out the steps ahead.
     *
     * @return array{manage: bool, score: bool, accept: bool, revise: bool, announce: bool, place: bool, bypass: bool, cancel_placement: bool}
     */
    public static function abilities(Registrant $registrant, User $user): array
    {
        $canManage = $user->can(Ability::RegistrantManage->value);
        $canAnnounce = $user->can('announce', Registrant::class);
        $canScore = $user->can('score', Registrant::class);
        $canPlace = $user->can('place', Registrant::class);
        $canBypass = $canManage && $user->can(Ability::RegistrantBypass->value);
        $canCancelPlacement = $user->can(Ability::StudentManage->value);
        $status = $registrant->status;

        return [
            'manage' => $canManage,
            'score' => $canScore,
            'accept' => $canManage && $status === RegistrantStatus::Review && ! $registrant->needs_identity_review,
            'revise' => $canManage && $status === RegistrantStatus::Review && ! $registrant->needs_identity_review,
            'announce' => $canAnnounce && $status === RegistrantStatus::Validated && ! $registrant->needs_identity_review,
            'place' => $canPlace && $status === RegistrantStatus::Announced && ! $registrant->needs_identity_review,
            'bypass' => $canBypass && $status !== RegistrantStatus::Placed,
            'cancel_placement' => $canCancelPlacement && $status === RegistrantStatus::Placed,
        ];
    }

    /**
     * Who the committee should call or message about this registration: the primary contact of the family. While
     * the NIK is unconfirmed the family on record may be someone else's, so it is the parent who registered them.
     * Needs `person.parentships.relatedPerson` and `registeredBy` loaded.
     *
     * @return array{name: string, phone: string, label: string, whatsapp_url: string}|null
     */
    public static function contact(Registrant $registrant): ?array
    {
        if ($registrant->needs_identity_review) {
            $contact = $registrant->registeredBy;
            $label = 'Yang mendaftarkan';
        } else {
            $contact = $registrant->person->parentships->first(fn (Parentship $parentship): bool => $parentship->is_primary_contact)?->relatedPerson;
            $label = 'Kontak utama';
        }

        if ($contact === null || blank($contact->phone)) {
            return null;
        }

        return [
            'name' => $contact->name,
            'phone' => $contact->phone,
            'label' => $label,
            'whatsapp_url' => 'https://wa.me/'.Announcement::whatsappNumber($contact->phone),
        ];
    }

    /**
     * WhatsApp messages the committee can send to the family right now, each with its template already filled in.
     * Only the ones that apply to the registration's situation are listed: a revision reminder while the parent has
     * to correct data, a payment reminder while a bill is open, an exam reminder once a session is chosen, and the
     * result message after the data is valid. Nothing is offered while the NIK is unconfirmed, because the family on
     * record may be someone else's. A kind without anybody to message is left out.
     *
     * @return list<array{kind: string, label: string, url: string}>
     */
    public static function messages(Registrant $registrant): array
    {
        if ($registrant->needs_identity_review) {
            return [];
        }

        $registrant->loadMissing(['bills.items' => fn ($items) => $items->withPaymentTotals()]);

        $status = $registrant->status;
        $kinds = [];

        if ($status === RegistrantStatus::Revision) {
            $kinds[MessageTemplates::REVISION] = 'Pengingat revisi data';
        }

        if ($status !== RegistrantStatus::Placed && $registrant->bills->contains(fn ($bill): bool => $bill->outstandingAmount() > 0)) {
            $kinds[MessageTemplates::PAYMENT] = 'Pengingat pembayaran';
        }

        if (! in_array($status, [RegistrantStatus::Announced, RegistrantStatus::Placed], true) && $registrant->exam_session_id !== null) {
            $kinds[MessageTemplates::EXAM] = 'Pengingat jadwal ujian';
        }

        if (in_array($status, [RegistrantStatus::Validated, RegistrantStatus::Announced], true)) {
            $kinds[MessageTemplates::ANNOUNCEMENT] = 'Pengumuman hasil';
        }

        $messages = [];

        foreach ($kinds as $kind => $label) {
            $url = Announcement::whatsappUrlFor($registrant, $kind);

            if ($url !== null) {
                $messages[] = ['kind' => $kind, 'label' => $label, 'url' => $url];
            }
        }

        return $messages;
    }

    /**
     * What a parent sees of a registration on the portal.
     *
     * @return array<string, mixed>
     */
    public static function summary(Registrant $registrant): array
    {
        $registrant->loadMissing(['person', 'organization', 'wave']);

        return [
            'id' => $registrant->id,
            'child' => [
                'id' => $registrant->person->id,
                'name' => self::childName($registrant),
                'photo_url' => self::childPhotoUrl($registrant),
            ],
            'identity_pending' => $registrant->needs_identity_review,
            'organization' => $registrant->organization->name,
            'wave' => $registrant->wave->name,
            'status' => $registrant->status->value,
            'status_label' => $registrant->status->label(),
            'steps' => $registrant->steps(),
            'current_step' => $registrant->currentStep(),
            'can_edit' => $registrant->status->isEditableByParent() && ! $registrant->needs_identity_review,
            'revision_reason' => self::revisionReason($registrant),
        ];
    }

    /**
     * How much of the draft is filled in: the details this registration's ruleset requires.
     *
     * @return array{filled: int, total: int}
     */
    public static function completeness(Registrant $registrant): array
    {
        return RegistrationFields::progress($registrant);
    }

    /**
     * The history of a registration. Parents read the reason of a revision, but not the internal note of a bypass
     * nor the name of the committee member.
     *
     * @return list<array{id: int, from: string|null, to: string, to_label: string, note: string|null, by: string|null, is_bypass: bool, at: string}>
     */
    public static function timeline(Registrant $registrant, bool $forParent): array
    {
        return array_values($registrant->statusChanges()->with('user.person')->get()->map(fn (RegistrantStatusChange $change): array => [
            'id' => $change->id,
            'from' => $change->from_status?->value,
            'to' => $change->to_status->value,
            'to_label' => $change->to_status->label(),
            'note' => $forParent && $change->is_bypass ? null : $change->note,
            'by' => $forParent ? null : $change->user?->name,
            'is_bypass' => $forParent ? false : $change->is_bypass,
            'at' => $change->created_at?->toIso8601String() ?? '',
        ])->all());
    }

    /**
     * Why the committee sent the data back, while it is waiting for the parent to fix it.
     */
    public static function revisionReason(Registrant $registrant): ?string
    {
        if ($registrant->status !== RegistrantStatus::Revision) {
            return null;
        }

        return $registrant->statusChanges()->where('to_status', RegistrantStatus::Revision)->where('is_bypass', false)->latest('id')->value('note');
    }

    /**
     * What the data form shows: the child, the parents by relation, the photos and the choices to pick from.
     * The same form is used by the parent on the portal and by the committee when helping out.
     *
     * @return array{child: array<string, mixed>, parents: array<string, array<string, mixed>|null>, options: array<string, mixed>}
     */
    public static function dataForm(Registrant $registrant): array
    {
        return [
            ...self::profile($registrant),
            'options' => [
                ...PersonOptions::all(),
                'genders' => array_map(fn (Gender $case): array => ['value' => $case->value, 'label' => $case->label()], Gender::cases()),
                'admissionTypes' => array_map(fn (AdmissionType $case): array => ['value' => $case->value, 'label' => $case->label()], AdmissionType::cases()),
                'requiredFields' => RegistrationFields::requiredFor($registrant),
                'educationLevels' => array_map(fn (EducationLevel $case): array => ['value' => $case->value, 'label' => $case->label()], EducationLevel::cases()),
                'kinds' => array_map(fn (ParentKind $case): array => ['value' => $case->value, 'label' => $case->label()], ParentKind::cases()),
            ],
        ];
    }

    /**
     * Everything on record about the child and the parents by relation: the fields of the data form.
     *
     * @return array{child: array<string, mixed>, parents: array<string, array<string, mixed>|null>}
     */
    public static function profile(Registrant $registrant): array
    {
        $registrant->loadMissing(['person.parentships.relatedPerson', 'person.educationHistories']);
        $child = $registrant->person;

        return [
            'admission_type' => $registrant->admission_type->value,
            'education' => $child->educationHistories->map(fn (EducationHistory $history): array => [
                'level' => $history->level->value,
                'level_label' => $history->level->label(),
                'school_name' => $history->school_name,
                'npsn' => $history->npsn,
                'school_address' => $history->school_address,
            ])->values()->all(),
            'child' => [
                'name' => $child->name,
                'nik' => $child->nik,
                'gender' => $child->gender?->value,
                'birth_place' => $child->birth_place,
                'birth_date' => $child->birth_date?->toDateString(),
                'nisn' => $child->nisn,
                'family_card_number' => $child->family_card_number,
                'religion' => $child->religion,
                'blood_type' => $child->blood_type,
                'child_order' => $child->child_order,
                'siblings_count' => $child->siblings_count,
                'address' => $child->address,
                'photo_url' => $child->photo_url,
            ],
            'parents' => collect(ParentRelation::cases())->mapWithKeys(function (ParentRelation $relation) use ($child): array {
                $parentship = $child->parentships->first(fn (Parentship $link): bool => $link->relation === $relation);

                return [$relation->value => $parentship === null ? null : self::parent($parentship)];
            })->all(),
        ];
    }

    /**
     * The read-only view of everything on the data form, for the committee's detail page. While the NIK is
     * unconfirmed the person on record may be someone else's child, so nothing of them is shown: only the name the
     * parent typed.
     *
     * @return array{restricted: bool, child: array<string, mixed>, parents: list<array<string, mixed>>}
     */
    public static function detail(Registrant $registrant): array
    {
        if ($registrant->needs_identity_review) {
            return ['restricted' => true, 'child' => ['name' => $registrant->submitted_name], 'parents' => []];
        }

        $registrant->loadMissing(['person.parentships.relatedPerson']);
        $child = $registrant->person;

        return [
            'restricted' => false,
            'admission_type' => $registrant->admission_type->value,
            'admission_type_label' => $registrant->admission_type->label(),
            'education' => self::profile($registrant)['education'],
            'child' => [
                ...self::profile($registrant)['child'],
                'person_id' => $child->id,
                'gender_label' => $child->gender?->label(),
                'birth' => collect([$child->birth_place, $child->birth_date?->translatedFormat('j F Y')])->filter()->implode(', ') ?: null,
            ],
            'parents' => collect(ParentRelation::cases())
                ->map(fn (ParentRelation $relation): ?Parentship => $child->parentships->first(fn (Parentship $link): bool => $link->relation === $relation))
                ->filter()
                ->map(fn (Parentship $parentship): array => [
                    ...self::parent($parentship),
                    'relation' => $parentship->relation->value,
                    'relation_label' => $parentship->relation->label(),
                    'kind_label' => $parentship->kind?->label(),
                    'is_primary_contact' => $parentship->is_primary_contact,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Every payment that touched a bill of this registration, newest first: what it paid for this child (not the whole
     * transfer, which may cover siblings too), by which method, and whether it is counted yet.
     *
     * @return list<array<string, mixed>>
     */
    public static function payments(Registrant $registrant): array
    {
        $registrantId = $registrant->id;

        return Payment::query()
            ->whereHas('allocations.billItem.bill', fn ($bill) => $bill->where('registrant_id', $registrantId))
            ->with(['payer', 'allocations.billItem.bill.registrant.person'])
            ->latest('paid_on')
            ->latest('id')
            ->get()
            ->map(function (Payment $payment) use ($registrantId): array {
                $mine = $payment->allocations->filter(fn (PaymentAllocation $allocation): bool => $allocation->billItem->bill->registrant_id === $registrantId);

                return [
                    ...BillPresenter::payment($payment),
                    'method_value' => $payment->method->value,
                    'note' => $payment->note,
                    'for_child' => (int) $mine->sum('amount'),
                    'items' => $mine->map(fn (PaymentAllocation $allocation): string => $allocation->billItem->name)->values()->all(),
                    'receipt' => $payment->status === PaymentStatus::Verified && $payment->method !== PaymentMethod::Scholarship,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * The notes on the child that this staff member may read, the urgent ones first and the newest first within
     * the same urgency. System notes (such as the NIK check) are in there too.
     *
     * @return list<array<string, mixed>>
     */
    public static function notes(Person $child, User $user): array
    {
        $rank = collect([NoteUrgency::Critical, NoteUrgency::High, NoteUrgency::Normal, NoteUrgency::Low])
            ->map(fn (NoteUrgency $urgency, int $index): string => "when '{$urgency->value}' then {$index}")
            ->implode(' ');

        return $child->notes()
            ->visibleTo($user)
            ->with([
                'author.person',
                'shares.shareable' => fn (MorphTo $morph) => $morph->morphWith([User::class => ['person']]),
            ])
            ->orderByRaw("case urgency {$rank} else 4 end")
            ->latest()
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (Note $note): array => [
                'id' => $note->id,
                'body' => $note->body,
                'urgency' => $note->urgency->value,
                'visibility' => $note->visibility->value,
                'shared_with_family' => $note->shared_with_family,
                'created_at' => $note->created_at?->translatedFormat('j M Y, H:i'),
                'author' => $note->author_id === null
                    ? ['name' => 'Sistem', 'photo_url' => null]
                    : ['name' => $note->author->name, 'photo_url' => $note->author->avatar],
                'shared_with' => $note->shares->map(fn (NoteShare $share): string => match (true) {
                    $share->shareable instanceof User, $share->shareable instanceof Role => $share->shareable->name,
                    default => '—',
                })->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * What the note form offers: how urgent, who can read it, and who to share it with.
     *
     * @return array{urgencies: list<array{value: string, label: string}>, visibilities: list<array{value: string, label: string}>, users: list<array{value: int, label: string}>, roles: list<array{value: int, label: string}>}
     */
    public static function noteOptions(User $user): array
    {
        return [
            'urgencies' => array_map(fn (NoteUrgency $case): array => ['value' => $case->value, 'label' => $case->label()], NoteUrgency::cases()),
            'visibilities' => array_map(fn (NoteVisibility $case): array => ['value' => $case->value, 'label' => $case->label()], NoteVisibility::cases()),
            'users' => User::query()
                ->with('person')
                ->whereHas('roles', fn ($roles) => $roles->whereIn('name', [DefaultRole::SuperAdmin->value, DefaultRole::Admin->value, DefaultRole::Operator->value]))
                ->whereKeyNot($user->id)
                ->get()
                ->map(fn (User $staff): array => ['value' => $staff->id, 'label' => $staff->name])
                ->sortBy('label')
                ->values()
                ->all(),
            'roles' => Role::query()
                ->where('name', '!=', DefaultRole::User->value)
                ->orderBy('name')
                ->get()
                ->map(fn (Role $role): array => ['value' => $role->id, 'label' => $role->name])
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function parent(Parentship $parentship): array
    {
        /** @var Person $person */
        $person = $parentship->relatedPerson;

        return [
            'person_id' => $person->id,
            'name' => $person->name,
            'nik' => $person->nik,
            'phone' => $person->phone,
            'job' => $person->job,
            'last_education' => $person->last_education,
            'monthly_income' => $person->monthly_income,
            'address' => $person->address,
            'is_alive' => $person->is_alive,
            'kind' => $parentship->kind?->value,
        ];
    }
}
