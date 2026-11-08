<?php

namespace App\Support;

use App\Enums\DocumentType;
use App\Enums\ScholarshipKind;
use App\Models\Registrant;
use App\Models\RegistrantDocument;
use Illuminate\Database\Eloquent\Collection;

/**
 * What files a registration is asked for and what has been uploaded. The ruleset copy on the registration says which
 * core files are asked for and whether each is required; the supporting files for a scholarship are always offered.
 */
class RegistrantDocuments
{
    /**
     * The uploaded files, from the relation when it is loaded so a list of registrations does not query one by one.
     *
     * @return Collection<int, RegistrantDocument>
     */
    private static function uploaded(Registrant $registrant): Collection
    {
        return $registrant->relationLoaded('documents') ? $registrant->documents : $registrant->documents()->get();
    }

    /**
     * The core files this registration is asked for, in the order of the ruleset copy.
     *
     * @return list<array{type: DocumentType, required: bool}>
     */
    public static function requirements(Registrant $registrant): array
    {
        $asked = [];

        foreach ($registrant->ruleset_snapshot['documents'] ?? [] as $row) {
            $type = DocumentType::tryFrom($row['type']);

            if ($type !== null && ! $type->isSupporting()) {
                $asked[] = ['type' => $type, 'required' => (bool) $row['required']];
            }
        }

        return $asked;
    }

    /**
     * Required files that are not all there yet, in plain words. A report card counts as there once every semester
     * has a file.
     *
     * @return list<string>
     */
    public static function missing(Registrant $registrant): array
    {
        $uploaded = self::uploaded($registrant)->countBy(fn (RegistrantDocument $document): string => $document->type->value);
        $missing = [];

        foreach (self::requirements($registrant) as $requirement) {
            $type = $requirement['type'];
            $have = (int) ($uploaded[$type->value] ?? 0);
            $needed = $type->maxFiles();

            if (! $requirement['required'] || $have >= $needed) {
                continue;
            }

            $missing[] = $needed > 1 ? "{$type->label()} (kurang ".($needed - $have).' berkas)' : $type->label();
        }

        return $missing;
    }

    /**
     * Why this child might be given a scholarship, judged from what the parent uploaded or typed. Nothing here
     * decides anything: the committee looks at the evidence and decides.
     *
     * @return list<ScholarshipKind>
     */
    public static function scholarshipKinds(Registrant $registrant): array
    {
        $kinds = self::uploaded($registrant)
            ->toBase()
            ->map(fn (RegistrantDocument $document): ?ScholarshipKind => $document->type->scholarshipKind())
            ->filter();

        if (filled($registrant->kip_number)) {
            $kinds->push(ScholarshipKind::Hardship);
        }

        return $kinds->unique()->values()->all();
    }

    /**
     * Whether the parent gave anything as supporting evidence, even if it points at no particular scholarship.
     */
    public static function hasSupportingEvidence(Registrant $registrant): bool
    {
        return filled($registrant->kip_number)
            || filled($registrant->support_note)
            || self::uploaded($registrant)->contains(fn (RegistrantDocument $document): bool => $document->type->isSupporting());
    }

    /**
     * Every file slot of a registration for the upload screen: the core files asked for, then the supporting files.
     *
     * @return array{requirements: list<array<string, mixed>>, supporting: list<array<string, mixed>>}
     */
    public static function overview(Registrant $registrant): array
    {
        $uploaded = self::uploaded($registrant)->groupBy(fn (RegistrantDocument $document): string => $document->type->value);

        $describe = fn (DocumentType $type, ?bool $required) => [
            'type' => $type->value,
            'label' => $type->label(),
            'required' => $required,
            'max_files' => $type->maxFiles(),
            'slots' => collect(range(1, $type->maxFiles()))->map(function (int $slot) use ($type, $uploaded): array {
                $document = ($uploaded[$type->value] ?? collect())->firstWhere('slot', $slot);

                return [
                    'slot' => $slot,
                    'label' => $type->slotLabel($slot),
                    'document' => $document === null ? null : [
                        'id' => $document->id,
                        'name' => $document->original_name,
                        'size' => $document->size,
                        'mime_type' => $document->mime_type,
                    ],
                ];
            })->all(),
        ];

        return [
            'requirements' => array_map(fn (array $row): array => $describe($row['type'], $row['required']), self::requirements($registrant)),
            'supporting' => array_map(fn (DocumentType $type): array => $describe($type, null), DocumentType::supporting()),
        ];
    }

    /**
     * What the committee sees on a registration: every file asked for or uploaded with the files that came in, what
     * is still missing, and the KIP number and note the parent typed.
     *
     * @return array{groups: list<array<string, mixed>>, missing: list<string>, kip_number: string|null, support_note: string|null, count: int}
     */
    public static function forCommittee(Registrant $registrant): array
    {
        $overview = self::overview($registrant);
        $groups = [];

        foreach ([...$overview['requirements'], ...$overview['supporting']] as $group) {
            $files = array_values(array_filter(array_map(
                fn (array $slot): ?array => $slot['document'] === null ? null : ['slot' => $slot['slot'], 'label' => $slot['label'], ...$slot['document']],
                $group['slots'],
            )));

            if ($group['required'] === null && $files === []) {
                continue;
            }

            $groups[] = ['type' => $group['type'], 'label' => $group['label'], 'required' => $group['required'], 'max_files' => $group['max_files'], 'files' => $files];
        }

        return [
            'groups' => $groups,
            'missing' => self::missing($registrant),
            'kip_number' => $registrant->kip_number,
            'support_note' => $registrant->support_note,
            'count' => (int) array_sum(array_map(fn (array $group): int => count($group['files']), $groups)),
        ];
    }
}
