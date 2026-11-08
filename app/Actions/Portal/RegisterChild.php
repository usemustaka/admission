<?php

namespace App\Actions\Portal;

use App\Actions\Admission\ResolveRuleset;
use App\Actions\Billing\IssueBill;
use App\Enums\BillType;
use App\Enums\NoteUrgency;
use App\Enums\NoteVisibility;
use App\Enums\ParentKind;
use App\Enums\RegistrantStatus;
use App\Models\Note;
use App\Models\Person;
use App\Models\Registrant;
use App\Models\User;
use App\Support\PersonName;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegisterChild
{
    public function __construct(
        private ResolveRuleset $resolveRuleset,
        private LinkRegisteringParent $linkRegisteringParent,
        private IssueBill $issueBill,
    ) {}

    /**
     * Register a child for admission on behalf of a parent. A NIK that is already known is linked to the same
     * person. When the name differs, the registration is flagged and the committee gets a note; until they
     * confirm, the parent is not linked to the child and sees none of the child's data.
     *
     * @throws ValidationException
     */
    public function handle(User $parent, RegisterChildData $data): Registrant
    {
        $parentPerson = $parent->person ?? abort(403);

        return DB::transaction(function () use ($parentPerson, $data): Registrant {
            $existing = Person::query()->where('nik', $data->nik)->first();

            if ($existing?->is($parentPerson)) {
                throw ValidationException::withMessages(['nik' => 'NIK ini adalah NIK kamu sendiri. Isi dengan NIK anak ya.']);
            }

            $ruleset = $this->resolveRuleset->handle($data->waveId, $data->organizationId, $data->gender);

            if ($ruleset === null) {
                throw ValidationException::withMessages(['organization_id' => 'Pendaftaran untuk pilihan ini belum tersedia. Silakan hubungi sekolah.']);
            }

            $child = $existing ?? Person::create([
                'nik' => $data->nik,
                'name' => $data->name,
                'gender' => $data->gender,
                'birth_date' => $data->birthDate,
            ]);

            $needsIdentityReview = $existing !== null && ! PersonName::same($existing->name, $data->name);

            if ($existing !== null && ! $needsIdentityReview) {
                $child->fill(array_filter([
                    'gender' => $child->gender === null ? $data->gender : null,
                    'birth_date' => $child->birth_date === null ? $data->birthDate : null,
                ]))->save();
            }

            if ($child->registrants()->where('organization_id', $data->organizationId)->where('wave_id', $data->waveId)->exists()) {
                throw ValidationException::withMessages(['nik' => 'Anak ini sudah didaftarkan di lembaga dan gelombang tersebut.']);
            }

            $kind = $data->relation->hasKind() ? ($data->kind ?? ParentKind::Biological) : null;

            $registrant = Registrant::create([
                'person_id' => $child->id,
                'organization_id' => $data->organizationId,
                'wave_id' => $data->waveId,
                'ruleset_id' => $ruleset->id,
                'ruleset_snapshot' => $ruleset->toSnapshot(),
                'registered_by_person_id' => $parentPerson->id,
                'submitted_name' => $data->name,
                'registered_as_relation' => $data->relation,
                'registered_as_kind' => $kind,
                'status' => RegistrantStatus::Draft,
                'admission_type' => $data->admissionType,
                'needs_identity_review' => $needsIdentityReview,
            ]);

            $this->issueBill->handle($registrant, BillType::Registration);

            if ($needsIdentityReview) {
                $this->flagNameMismatch($child, $data);
            } else {
                $this->linkRegisteringParent->handle($registrant);
            }

            return $registrant;
        });
    }

    private function flagNameMismatch(Person $child, RegisterChildData $data): void
    {
        Note::create([
            'noteable_type' => $child->getMorphClass(),
            'noteable_id' => $child->id,
            'person_id' => $child->id,
            'author_id' => null,
            'body' => "Orang tua mendaftarkan anak dengan nama \"{$data->name}\", tetapi NIK {$data->nik} sudah tercatat atas nama \"{$child->name}\". Mohon dicek apakah NIK atau namanya yang keliru.",
            'urgency' => NoteUrgency::High,
            'visibility' => NoteVisibility::Public,
            'shared_with_family' => false,
        ]);
    }
}
