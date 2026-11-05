<?php

use App\Models\Parentship;
use App\Models\Person;
use App\Models\Registrant;
use App\Support\Announcement;

beforeEach(function () {
    $this->registrant = Registrant::factory()->create();
    $this->registrant->registeredBy->update(['phone' => '0899-0000-0000']);
});

function linkedParent(Registrant $registrant, ?string $phone, bool $primary = false): Person
{
    $parent = Person::factory()->create(['phone' => $phone]);
    Parentship::factory()->create(['person_id' => $registrant->person_id, 'related_person_id' => $parent->id, 'is_primary_contact' => $primary]);

    return $parent;
}

test('the primary contact receives the announcement when they have a phone', function () {
    linkedParent($this->registrant, '0811-1111-1111');
    $primary = linkedParent($this->registrant, '0812-2222-2222', primary: true);

    expect(Announcement::recipient($this->registrant))->toBe(['name' => $primary->name, 'phone' => '0812-2222-2222']);
});

test('another parent with a phone takes over when the primary contact has none', function () {
    linkedParent($this->registrant, null, primary: true);
    linkedParent($this->registrant, null);
    $reachable = linkedParent($this->registrant, '0813-3333-3333');
    linkedParent($this->registrant, '0814-4444-4444');

    expect(Announcement::recipient($this->registrant))->toBe(['name' => $reachable->name, 'phone' => '0813-3333-3333']);
});

test('the person who registered the child is the last resort', function () {
    linkedParent($this->registrant, null, primary: true);
    linkedParent($this->registrant, '');

    expect(Announcement::recipient($this->registrant))->toBe(['name' => $this->registrant->registeredBy->name, 'phone' => '0899-0000-0000']);
});

test('there is no recipient when nobody reachable is known', function () {
    linkedParent($this->registrant, null, primary: true);
    $this->registrant->registeredBy->update(['phone' => null]);

    expect(Announcement::recipient($this->registrant))->toBeNull();
});
