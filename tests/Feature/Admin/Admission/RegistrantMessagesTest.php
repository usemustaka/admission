<?php

use App\Enums\DefaultRole;
use App\Enums\RegistrantStatus;
use App\Models\Registrant;
use App\Support\RegistrantPresenter;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->registrant = Registrant::factory()->create(['status' => RegistrantStatus::Validated]);
    $this->registrant->registeredBy->update(['phone' => '0812-3456-7890']);
});

function messageKinds(Registrant $registrant): array
{
    return array_column(RegistrantPresenter::messages($registrant->fresh()), 'kind');
}

test('a validated registrant can be sent the result message with the template filled in', function () {
    $messages = RegistrantPresenter::messages($this->registrant);

    expect(array_column($messages, 'kind'))->toContain('announcement')
        ->and($messages[0]['url'])->toStartWith('https://wa.me/6281234567890?text=')
        ->and(urldecode($messages[0]['url']))->toContain($this->registrant->person->name);
});

test('only the messages that fit the situation are offered', function () {
    $this->registrant->update(['status' => RegistrantStatus::Revision]);

    expect(messageKinds($this->registrant))->toContain('revision')->not->toContain('announcement');

    $this->registrant->update(['status' => RegistrantStatus::Placed]);

    expect(messageKinds($this->registrant))->toBe([]);
});

test('nothing is offered while the NIK is unconfirmed', function () {
    $this->registrant->update(['needs_identity_review' => true]);

    expect(messageKinds($this->registrant))->toBe([]);
});

test('nothing is offered when nobody has a phone number', function () {
    $this->registrant->registeredBy->update(['phone' => null]);

    expect(messageKinds($this->registrant))->toBe([]);
});

test('the quick view carries the messages', function () {
    actingAs(userWithRole(DefaultRole::Operator));

    $this->getJson(route('admin.registrants.quick-view', $this->registrant))
        ->assertOk()
        ->assertJsonPath('messages.0.kind', 'announcement');
});
