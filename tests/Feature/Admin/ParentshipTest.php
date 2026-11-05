<?php

use App\Enums\DefaultRole;
use App\Models\Parentship;
use App\Models\Person;
use Spatie\Permission\Models\Role;

function linkParent(Person $child, Person $parent, array $overrides = [])
{
    return test()->post(route('admin.people.parentships.store', $child), [
        'related_person_id' => $parent->id,
        'relation' => 'father',
        'kind' => 'biological',
        ...$overrides,
    ]);
}

beforeEach(function () {
    $this->actingAs(userWithRole(DefaultRole::Operator));
    $this->child = Person::factory()->create();
});

test('the first parent of a child automatically becomes the primary contact', function () {
    $father = Person::factory()->create();

    linkParent($this->child, $father)->assertRedirect(route('admin.people.show', $this->child));

    expect($this->child->parentships()->first()->is_primary_contact)->toBeTrue();
});

test('only one parent can be the primary contact at a time', function () {
    $father = Person::factory()->create();
    $mother = Person::factory()->create();

    linkParent($this->child, $father);
    linkParent($this->child, $mother, ['relation' => 'mother', 'is_primary_contact' => true]);

    $primaries = $this->child->parentships()->where('is_primary_contact', true)->get();

    expect($primaries)->toHaveCount(1)->and($primaries->first()->related_person_id)->toBe($mother->id);
});

test('the primary contact can be switched later', function () {
    $first = Parentship::factory()->primaryContact()->create(['person_id' => $this->child->id]);
    $second = Parentship::factory()->create(['person_id' => $this->child->id, 'relation' => 'mother']);

    $this->put(route('admin.people.parentships.update', [$this->child, $second]))->assertRedirect();

    expect($first->refresh()->is_primary_contact)->toBeFalse()
        ->and($second->refresh()->is_primary_contact)->toBeTrue();
});

test('a guardian has no biological or step kind', function () {
    $guardian = Person::factory()->create();

    linkParent($this->child, $guardian, ['relation' => 'guardian', 'kind' => null])->assertSessionHasNoErrors();

    expect($this->child->parentships()->first()->kind)->toBeNull();
});

test('a father or mother needs a kind', function () {
    linkParent($this->child, Person::factory()->create(), ['kind' => null])->assertSessionHasErrors('kind');
});

test('a person cannot be their own parent or be linked twice', function () {
    $father = Person::factory()->create();

    linkParent($this->child, $this->child)->assertSessionHasErrors('related_person_id');

    linkParent($this->child, $father);
    linkParent($this->child, $father, ['relation' => 'guardian', 'kind' => null])->assertSessionHasErrors('related_person_id');
});

test('a relation can be removed', function () {
    $parentship = Parentship::factory()->create(['person_id' => $this->child->id]);

    $this->delete(route('admin.people.parentships.destroy', [$this->child, $parentship]))->assertRedirect();

    expect(Parentship::count())->toBe(0);
});

test('a relation of another child cannot be changed through this childs url', function () {
    $foreign = Parentship::factory()->create();

    $this->delete(route('admin.people.parentships.destroy', [$this->child, $foreign]))->assertNotFound();
});

test('staff without the family permission are refused', function () {
    $viewer = userWithRole(DefaultRole::Operator);
    $viewer->syncRoles([]);
    Role::findByName('operator')->revokePermissionTo('core::family.manage');
    $this->actingAs($viewer->assignRole('operator'));

    linkParent($this->child, Person::factory()->create())->assertForbidden();
});
