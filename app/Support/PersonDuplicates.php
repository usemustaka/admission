<?php

namespace App\Support;

use App\Models\Person;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Finds people who are probably the same human as someone being added or linked: the same name (ignoring case,
 * spacing and punctuation) with the same birth date, the same phone number, the same email, or the same family card
 * number. A person with the same NIK is not a probable duplicate but a certain one and is handled before this.
 */
class PersonDuplicates
{
    /** The most candidates shown; more than this is already a reason to look at the people list. */
    public const LIMIT = 5;

    /**
     * @param  array{name?: string|null, birth_date?: string|CarbonInterface|null, phone?: string|null, email?: string|null, family_card_number?: string|null, nik?: string|null}  $attributes
     * @return Collection<int, array{person: Person, reasons: list<string>}>
     */
    public static function find(array $attributes, ?int $exceptId = null): Collection
    {
        $name = (string) ($attributes['name'] ?? '');
        $birthDate = isset($attributes['birth_date']) && $attributes['birth_date'] !== '' ? (string) (($attributes['birth_date'] instanceof CarbonInterface) ? $attributes['birth_date']->toDateString() : $attributes['birth_date']) : null;
        $phone = filled($attributes['phone'] ?? null) ? (string) $attributes['phone'] : null;
        $email = filled($attributes['email'] ?? null) ? mb_strtolower(trim((string) $attributes['email'])) : null;
        $card = filled($attributes['family_card_number'] ?? null) ? (string) $attributes['family_card_number'] : null;
        $nik = filled($attributes['nik'] ?? null) ? (string) $attributes['nik'] : null;
        $normalizedName = PersonName::normalize($name);

        $checkName = $normalizedName !== '' && $birthDate !== null;

        if (! $checkName && $phone === null && $email === null && $card === null) {
            return collect();
        }

        $candidates = Person::query()
            ->when($exceptId !== null, fn (Builder $query) => $query->whereKeyNot($exceptId))
            ->when($nik !== null, fn (Builder $query) => $query->where(fn (Builder $query) => $query->whereNull('nik')->orWhere('nik', '<>', $nik)))
            ->where(function (Builder $query) use ($checkName, $birthDate, $phone, $email, $card): void {
                $query->when($checkName, fn (Builder $query) => $query->orWhereDate('birth_date', $birthDate))
                    ->when($phone !== null, fn (Builder $query) => $query->orWhereRaw(PhoneNumber::strippedColumn().' in (?, ?, ?)', PhoneNumber::variants($phone)))
                    ->when($email !== null, fn (Builder $query) => $query->orWhereRaw('lower(email) = ?', [$email]))
                    ->when($card !== null, fn (Builder $query) => $query->orWhere('family_card_number', $card));
            })
            ->orderBy('name')
            ->orderBy('id')
            ->limit(50)
            ->get();

        return $candidates
            ->map(function (Person $candidate) use ($checkName, $normalizedName, $phone, $email, $card): array {
                $reasons = array_values(array_filter([
                    $checkName && $candidate->birth_date !== null && PersonName::normalize($candidate->name) === $normalizedName ? 'Nama dan tanggal lahir sama' : null,
                    PhoneNumber::same($phone, $candidate->phone) ? 'Nomor HP sama' : null,
                    $email !== null && mb_strtolower((string) $candidate->email) === $email ? 'Email sama' : null,
                    $card !== null && $candidate->family_card_number === $card ? 'Nomor KK sama' : null,
                ]));

                return ['person' => $candidate, 'reasons' => $reasons];
            })
            ->filter(fn (array $match): bool => $match['reasons'] !== [])
            ->take(self::LIMIT)
            ->values();
    }

    /**
     * Probable duplicates of a person who already exists (never the person themselves).
     *
     * @return Collection<int, array{person: Person, reasons: list<string>}>
     */
    public static function of(Person $person): Collection
    {
        return self::find([
            'name' => $person->name,
            'birth_date' => $person->birth_date,
            'phone' => $person->phone,
            'email' => $person->email,
            'family_card_number' => $person->family_card_number,
            'nik' => $person->nik,
        ], $person->id);
    }

    /**
     * What the page shows for each candidate: enough to recognise them, with the phone masked.
     *
     * @param  Collection<int, array{person: Person, reasons: list<string>}>  $matches
     * @return list<array{id: int, name: string, birth_date: string|null, phone_masked: string|null, has_nik: bool, has_family_card: bool, reasons: list<string>}>
     */
    public static function present(Collection $matches): array
    {
        return $matches->map(fn (array $match): array => [
            'id' => $match['person']->id,
            'name' => $match['person']->name,
            'birth_date' => $match['person']->birth_date?->toDateString(),
            'phone_masked' => PhoneNumber::mask($match['person']->phone),
            'has_nik' => filled($match['person']->nik),
            'has_family_card' => filled($match['person']->family_card_number),
            'reasons' => $match['reasons'],
        ])->all();
    }
}
