<?php

namespace App\Actions\Admission;

use App\Enums\DocumentType;
use App\Enums\Gender;
use App\Models\Ruleset;
use App\Support\RegistrationFields;

class SaveRuleset
{
    /**
     * Create or update the conditions of a ruleset. New rulesets go to the end of the priority order.
     */
    public function handle(?Ruleset $ruleset, string $name, ?int $waveId, ?int $organizationId, ?Gender $gender, bool $isActive): Ruleset
    {
        $isNew = $ruleset === null;
        $ruleset ??= new Ruleset(['priority' => (int) Ruleset::query()->max('priority') + 1, 'required_fields' => RegistrationFields::defaults()]);

        $ruleset->fill([
            'name' => $name,
            'wave_id' => $waveId,
            'organization_id' => $organizationId,
            'gender' => $gender,
            'is_active' => $isActive,
        ])->save();

        if ($isNew) {
            $this->askForDefaultDocuments($ruleset);
        }

        return $ruleset;
    }

    /**
     * A new ruleset starts out asking for the files most schools want: the family card, birth certificate and report
     * cards are required and the diploma is optional. The committee changes them on the ruleset page.
     */
    private function askForDefaultDocuments(Ruleset $ruleset): void
    {
        foreach ([DocumentType::FamilyCard, DocumentType::BirthCertificate, DocumentType::ReportCard] as $type) {
            $ruleset->documents()->create(['type' => $type, 'is_required' => true]);
        }

        $ruleset->documents()->create(['type' => DocumentType::Diploma, 'is_required' => false]);
    }
}
