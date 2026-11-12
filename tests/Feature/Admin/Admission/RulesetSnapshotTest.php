<?php

use App\Enums\BillType;
use App\Enums\DocumentType;
use App\Models\ExamAspect;
use App\Models\Ruleset;
use App\Models\RulesetBill;
use App\Models\RulesetBillItem;
use App\Models\RulesetDocument;

test('a snapshot freezes bills and exam aspects so later edits do not change it', function () {
    $ruleset = Ruleset::factory()->create(['name' => 'SMP putra']);
    $settlement = RulesetBill::factory()->create(['ruleset_id' => $ruleset->id, 'type' => BillType::Settlement]);
    $registration = RulesetBill::factory()->create(['ruleset_id' => $ruleset->id, 'type' => BillType::Registration]);
    RulesetBillItem::factory()->create(['ruleset_bill_id' => $registration->id, 'name' => 'Formulir', 'amount' => 150000]);
    RulesetBillItem::factory()->create(['ruleset_bill_id' => $settlement->id, 'name' => 'Uang pangkal', 'amount' => 4000000]);
    ExamAspect::factory()->create(['ruleset_id' => $ruleset->id, 'name' => 'Tes Tulis', 'passing_score' => 70]);
    RulesetDocument::factory()->create(['ruleset_id' => $ruleset->id, 'type' => DocumentType::FamilyCard, 'is_required' => true]);
    RulesetDocument::factory()->optional()->create(['ruleset_id' => $ruleset->id, 'type' => DocumentType::Diploma]);

    $snapshot = $ruleset->toSnapshot();

    $ruleset->bills()->delete();
    $ruleset->examAspects()->delete();
    $ruleset->documents()->delete();

    expect($snapshot)->toBe([
        'ruleset_id' => $ruleset->id,
        'name' => 'SMP putra',
        'bills' => [
            ['type' => 'registration', 'items' => [['name' => 'Formulir', 'amount' => 150000]]],
            ['type' => 'settlement', 'items' => [['name' => 'Uang pangkal', 'amount' => 4000000]]],
        ],
        'exam_aspects' => [['name' => 'Tes Tulis', 'scale' => 'number', 'passing_score' => 70]],
        'documents' => [['type' => 'family-card', 'required' => true], ['type' => 'diploma', 'required' => false]],
        'documents_configured' => true,
        'required_fields' => null,
    ]);
});

test('a ruleset without bills has none in its snapshot, which skips that stage', function () {
    $snapshot = Ruleset::factory()->create()->toSnapshot();

    expect($snapshot['bills'])->toBe([])->and($snapshot['exam_aspects'])->toBe([]);
});
