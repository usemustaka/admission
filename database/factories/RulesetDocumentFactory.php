<?php

namespace Database\Factories;

use App\Enums\DocumentType;
use App\Models\Ruleset;
use App\Models\RulesetDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RulesetDocument>
 */
class RulesetDocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ruleset_id' => Ruleset::factory(),
            'type' => DocumentType::FamilyCard,
            'is_required' => true,
        ];
    }

    public function optional(): static
    {
        return $this->state(['is_required' => false]);
    }
}
