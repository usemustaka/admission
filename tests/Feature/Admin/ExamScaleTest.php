<?php

use App\Enums\DefaultRole;
use App\Enums\ExamScale;
use App\Enums\RegistrantStatus;
use App\Models\ExamAspect;
use App\Models\Ruleset;
use App\Support\ExamResult;

beforeEach(function () {
    $this->admin = userWithRole(DefaultRole::Admin);
    $this->ruleset = Ruleset::factory()->create();
});

test('an aspect can be graded with levels, and its KKM is the lowest passing level', function () {
    $this->actingAs($this->admin)->put(route('admin.rulesets.exam-aspects', $this->ruleset), ['aspects' => [
        ['name' => 'Literasi', 'scale' => 'number', 'passing_score' => 60],
        ['name' => 'Hafalan', 'scale' => 'memorized-fair', 'passing_score' => 50],
        ['name' => 'Menulis Arab', 'scale' => 'able-only', 'passing_score' => 100],
    ]])->assertSessionHasNoErrors();

    expect($this->ruleset->examAspects->map(fn ($a) => [$a->scale, $a->passing_score])->all())
        ->toBe([[ExamScale::Number, 60], [ExamScale::MemorizedFair, 50], [ExamScale::AbleOnly, 100]]);
});

test('a level scale needs a KKM that is one of its levels and never "Belum"', function () {
    $this->actingAs($this->admin);

    $this->put(route('admin.rulesets.exam-aspects', $this->ruleset), ['aspects' => [['name' => 'Hafalan', 'scale' => 'memorized-fair', 'passing_score' => 70]]])
        ->assertSessionHasErrors('aspects.0.passing_score');
    $this->put(route('admin.rulesets.exam-aspects', $this->ruleset), ['aspects' => [['name' => 'Hafalan', 'scale' => 'good-only', 'passing_score' => 0]]])
        ->assertSessionHasErrors('aspects.0.passing_score');
    $this->put(route('admin.rulesets.exam-aspects', $this->ruleset), ['aspects' => [['name' => 'X', 'scale' => 'nonsense', 'passing_score' => 60]]])
        ->assertSessionHasErrors('aspects.0.scale');
});

test('scores follow the scale of each aspect and the result reads in its words', function () {
    ExamAspect::factory()->create(['ruleset_id' => $this->ruleset->id, 'name' => 'Literasi', 'scale' => 'number', 'passing_score' => 60, 'sort_order' => 0]);
    ExamAspect::factory()->create(['ruleset_id' => $this->ruleset->id, 'name' => 'Hafalan', 'scale' => 'memorized-fair', 'passing_score' => 50, 'sort_order' => 1]);

    $registrant = admissionRegistrant(RegistrantStatus::Review, ['ruleset_snapshot' => $this->ruleset->toSnapshot()]);

    $this->actingAs($this->admin)->put(route('admin.registrants.scores', $registrant), ['scores' => [0 => 75, 1 => 70]])->assertSessionHasErrors('scores.1');
    $this->put(route('admin.registrants.scores', $registrant), ['scores' => [0 => 150]])->assertSessionHasErrors('scores.0');
    $this->put(route('admin.registrants.scores', $registrant), ['scores' => [0 => 75, 1 => 50]])->assertSessionHasNoErrors();

    $result = ExamResult::for($registrant->refresh());

    expect($result['passed'])->toBeTrue()
        ->and($result['aspects'][1]['score_label'])->toBe('Kurang')
        ->and($result['aspects'][1]['passing_label'])->toBe('Kurang')
        ->and($result['aspects'][0]['score_label'])->toBe('75');

    $this->put(route('admin.registrants.scores', $registrant), ['scores' => [1 => 0]])->assertSessionHasNoErrors();

    expect(ExamResult::for($registrant->refresh())['passed'])->toBeFalse();
});

test('a registration made before scales existed still reads as plain numbers', function () {
    $registrant = admissionRegistrant();

    expect($registrant->ruleset_snapshot['exam_aspects'][0])->not->toHaveKey('scale');

    $registrant->update(['exam_scores' => [80, 65]]);

    expect(ExamResult::for($registrant)['aspects'][0])->toMatchArray(['scale' => 'number', 'score_label' => '80', 'passed' => true]);
});
