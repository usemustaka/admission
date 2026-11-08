<?php

namespace App\Support;

use App\Enums\DefaultRole;
use App\Enums\PaymentStatus;
use App\Models\Employee;
use App\Models\Parentship;
use App\Models\Payment;
use App\Models\Person;
use App\Models\Registrant;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Everything the committee may want to know about a person on one page, beyond the name and contact: the full profile,
 * the schools before, where they study or work, their login, their registrations with the money and files that go
 * with them, and who else is in the family.
 */
class PersonProfile
{
    /**
     * The profile fields the data form fills in, plus when the record was made and last changed.
     *
     * @return array<string, mixed>
     */
    public static function details(Person $person): array
    {
        return [
            'nisn' => $person->nisn,
            'religion' => $person->religion,
            'blood_type' => $person->blood_type,
            'child_order' => $person->child_order,
            'siblings_count' => $person->siblings_count,
            'address' => $person->address,
            'last_education' => $person->last_education,
            'job' => $person->job,
            'monthly_income' => $person->monthly_income,
            'created_at' => $person->created_at?->translatedFormat('j M Y, H:i'),
            'updated_at' => $person->updated_at?->translatedFormat('j M Y, H:i'),
        ];
    }

    /**
     * @return list<array{level: string, level_label: string, school_name: string, npsn: string|null, school_address: string|null}>
     */
    public static function education(Person $person): array
    {
        return $person->educationHistories->map(fn ($history): array => [
            'level' => $history->level->value,
            'level_label' => $history->level->label(),
            'school_name' => $history->school_name,
            'npsn' => $history->npsn,
            'school_address' => $history->school_address,
        ])->values()->all();
    }

    /**
     * @return list<array{id: int, nis: string|null, organization: string, year: string|null, registrant_id: int|null}>
     */
    public static function students(Person $person): array
    {
        return $person->students()->with(['organization', 'year'])->latest('id')->get()->map(fn (Student $student): array => [
            'id' => $student->id,
            'nis' => $student->nis,
            'organization' => $student->organization->name,
            'year' => $student->year?->name,
            'registrant_id' => $student->registrant_id,
        ])->all();
    }

    /**
     * @return list<array{organization: string, position: string|null}>
     */
    public static function employees(Person $person): array
    {
        return $person->employees()->with('organization')->get()->map(fn (Employee $employee): array => [
            'organization' => $employee->organization->name,
            'position' => $employee->position,
        ])->all();
    }

    /**
     * The login account of the person, if there is one: whether the email is verified, the roles, and whether it was
     * switched off.
     *
     * @return array{id: int, email: string, verified: bool, roles: list<string>, is_staff: bool, is_active: bool, two_factor: bool, created_at: string|null}|null
     */
    public static function account(Person $person): ?array
    {
        $account = User::query()->with('roles')->where('person_id', $person->id)->first();

        if ($account === null) {
            return null;
        }

        $roles = $account->roles->pluck('name');

        return [
            'id' => $account->id,
            'email' => $account->email,
            'verified' => $account->hasVerifiedEmail(),
            'roles' => $roles->reject(fn (string $name): bool => $name === DefaultRole::Inactive->value)->values()->all(),
            'is_staff' => $roles->contains(fn (string $name): bool => ! in_array($name, [DefaultRole::User->value], true)),
            'is_active' => ! $roles->contains(DefaultRole::Inactive->value),
            'two_factor' => $account->two_factor_confirmed_at !== null,
            'created_at' => $account->created_at?->translatedFormat('j M Y'),
        ];
    }

    /**
     * Other children of the same parents.
     *
     * @return list<array{id: int, name: string, photo_url: string|null, relation: string}>
     */
    public static function siblings(Person $person): array
    {
        $parentIds = $person->parentships->pluck('related_person_id');

        if ($parentIds->isEmpty()) {
            return [];
        }

        return Parentship::query()
            ->with('person')
            ->whereIn('related_person_id', $parentIds)
            ->where('person_id', '!=', $person->id)
            ->get()
            ->unique('person_id')
            ->sortBy(fn (Parentship $parentship): string => $parentship->person->name)
            ->map(fn (Parentship $parentship): array => [
                'id' => $parentship->person->id,
                'name' => $parentship->person->name,
                'photo_url' => $parentship->person->photo_url,
                'relation' => $parentship->relation->value === 'guardian' ? 'Saudara (wali sama)' : 'Saudara',
            ])
            ->values()
            ->all();
    }

    /**
     * The person's own registrations and those of the children they are a parent or guardian of, each with where it
     * stands: status, type, money, files, exam result and scholarship hints.
     *
     * @return list<array<string, mixed>>
     */
    public static function registrations(Person $person, int $limit = 50): array
    {
        $childIds = $person->childrenParentships()->pluck('person_id');

        return Registrant::query()
            ->with(['person', 'organization', 'wave', 'documents', 'bills.items' => fn ($items) => $items->withPaymentTotals()])
            ->whereIn('person_id', $childIds->push($person->id))
            ->latest()
            ->limit($limit)
            ->get()
            ->map(function (Registrant $registrant) use ($person): array {
                $totals = RegistrantBillSummary::totals($registrant->bills);
                $result = ExamResult::for($registrant);

                return [
                    'id' => $registrant->id,
                    'child_name' => $registrant->person->name,
                    'is_self' => $registrant->person_id === $person->id,
                    'organization' => $registrant->organization->name,
                    'wave' => $registrant->wave->name,
                    'status' => $registrant->status->value,
                    'status_label' => $registrant->status->label(),
                    'admission_type' => $registrant->admission_type->label(),
                    'payment' => $totals['status'],
                    'outstanding' => $totals['outstanding'],
                    'documents_uploaded' => $registrant->documents->count(),
                    'documents_missing' => RegistrantDocuments::missing($registrant),
                    'exam' => match ($result['passed']) {
                        true => 'Tuntas',
                        false => 'Belum tuntas',
                        null => $result['aspects'] === [] ? null : 'Nilai belum lengkap',
                    },
                    'scholarship' => array_map(fn ($kind): string => $kind->label(), RegistrantDocuments::scholarshipKinds($registrant)),
                ];
            })
            ->all();
    }

    /**
     * What the person paid or sent for payment, newest first, and what their registrations still owe.
     *
     * @param  list<array<string, mixed>>  $registrations
     * @return array{owed: int, payments: list<array<string, mixed>>}
     */
    public static function finance(Person $person, array $registrations): array
    {
        $payments = Payment::query()
            ->with(['payer', 'allocations.billItem.bill.registrant.person'])
            ->where('payer_person_id', $person->id)
            ->latest('paid_on')
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn (Payment $payment): array => [
                ...BillPresenter::payment($payment),
                'method_value' => $payment->method->value,
                'receipt' => $payment->status === PaymentStatus::Verified && $payment->method->value !== 'scholarship',
            ])
            ->all();

        return [
            'owed' => (int) Collection::make($registrations)->sum('outstanding'),
            'payments' => $payments,
        ];
    }
}
