<?php

use App\Enums\RegistrantStatus;

test('staff who may only view cannot work on a registrant', function (string $method, string $route, array $payload) {
    $registrant = admissionRegistrant();
    $this->actingAs(registrantViewer());

    $this->call($method, route($route, $registrant), $payload)->assertForbidden();

    expect($registrant->refresh()->status)->toBe(RegistrantStatus::Review)
        ->and($registrant->statusChanges()->count())->toBe(0)
        ->and($registrant->exam_scores)->toBeNull()
        ->and($registrant->exam_session_id)->toBeNull();
})->with([
    'accept' => ['POST', 'admin.registrants.accept', []],
    'revise' => ['POST', 'admin.registrants.revise', ['note' => 'Foto KK buram']],
    'bypass' => ['POST', 'admin.registrants.bypass', ['status' => 'validated', 'note' => 'Langsung']],
    'announce' => ['POST', 'admin.registrants.announce', []],
    'place' => ['POST', 'admin.registrants.place', ['year_id' => 1, 'nis' => '2026001']],
    'scores' => ['PUT', 'admin.registrants.scores', ['scores' => [80, 70]]],
    'exam session' => ['PUT', 'admin.registrants.exam-session', ['exam_session_id' => 1]],
    'resolve identity' => ['POST', 'admin.registrants.resolve-identity', []],
    'edit the data' => ['GET', 'admin.registrants.data.edit', []],
    'update the data' => ['POST', 'admin.registrants.data.update', ['child' => ['address' => 'Jl. Baru 9'], 'parents' => []]],
]);
