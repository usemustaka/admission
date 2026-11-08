<?php

namespace Database\Factories;

use App\Enums\DocumentType;
use App\Models\Registrant;
use App\Models\RegistrantDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RegistrantDocument>
 */
class RegistrantDocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'registrant_id' => Registrant::factory(),
            'type' => DocumentType::FamilyCard,
            'slot' => 1,
            'path' => 'registrant-documents/test/'.fake()->uuid().'.pdf',
            'original_name' => 'kk.pdf',
            'mime_type' => 'application/pdf',
            'size' => 20480,
        ];
    }
}
