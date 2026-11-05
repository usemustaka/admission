<?php

use App\Enums\DefaultRole;
use App\Models\Note;
use App\Models\NoteShare;
use App\Models\Parentship;
use App\Models\Person;
use App\Models\User;
use Spatie\Permission\Models\Role;

function noteFor(Person $person, array $overrides = [])
{
    return test()->post(route('admin.notes.store'), [
        'noteable_type' => 'person',
        'noteable_id' => $person->id,
        'body' => 'Berkas belum lengkap.',
        'urgency' => 'high',
        'visibility' => 'private',
        ...$overrides,
    ]);
}

test('staff can write a note on a person with urgency and sharing', function () {
    $author = userWithRole(DefaultRole::Operator);
    $colleague = userWithRole(DefaultRole::Operator);
    $person = Person::factory()->create();
    $this->actingAs($author);

    noteFor($person, ['user_ids' => [$colleague->id], 'role_ids' => [Role::findByName('admin')->id]])->assertRedirect();

    $note = Note::firstOrFail();

    expect($note->author_id)->toBe($author->id)
        ->and($note->person_id)->toBe($person->id)
        ->and($note->urgency->value)->toBe('high')
        ->and($note->shares)->toHaveCount(2);
});

test('notes can only be attached to allowed entities', function () {
    $this->actingAs(userWithRole(DefaultRole::Operator));

    noteFor(Person::factory()->create(), ['noteable_type' => 'user'])->assertSessionHasErrors('noteable_type');
});

test('users who may not write notes are refused', function () {
    $this->actingAs(User::factory()->create());

    noteFor(Person::factory()->create())->assertForbidden();
});

test('only the author or a note manager can change or delete a note', function () {
    $note = Note::factory()->public()->create();
    $payload = ['body' => 'Diubah', 'urgency' => 'low', 'visibility' => 'public'];

    $this->actingAs(userWithRole(DefaultRole::Operator));
    $this->put(route('admin.notes.update', $note), $payload)->assertForbidden();
    $this->delete(route('admin.notes.destroy', $note))->assertForbidden();

    $this->actingAs(userWithRole(DefaultRole::Admin));
    $this->put(route('admin.notes.update', $note), $payload)->assertRedirect();
    expect($note->refresh()->body)->toBe('Diubah');

    $this->delete(route('admin.notes.destroy', $note))->assertRedirect();
    expect(Note::count())->toBe(0);
});

test('updating a note replaces who it is shared with', function () {
    $author = userWithRole(DefaultRole::Operator);
    $note = Note::factory()->create(['author_id' => $author->id]);
    NoteShare::factory()->create(['note_id' => $note->id]);
    $this->actingAs($author);

    $this->put(route('admin.notes.update', $note), ['body' => 'Sama', 'urgency' => 'normal', 'visibility' => 'private', 'user_ids' => []])->assertRedirect();

    expect($note->shares()->count())->toBe(0);
});

test('a note can be shared with the family of the child', function () {
    $this->actingAs(userWithRole(DefaultRole::Operator));
    $child = Person::factory()->create();

    noteFor($child, ['shared_with_family' => true])->assertSessionHasNoErrors();

    expect(Note::firstOrFail()->shared_with_family)->toBeTrue();
});

test('the person page lists only the notes the viewer may read', function () {
    $viewer = userWithRole(DefaultRole::Operator);
    $person = Person::factory()->create();
    Note::factory()->public()->create(['noteable_id' => $person->id, 'person_id' => $person->id, 'body' => 'Publik']);
    Note::factory()->create(['noteable_id' => $person->id, 'person_id' => $person->id, 'body' => 'Rahasia orang lain']);
    Note::factory()->create(['noteable_id' => $person->id, 'person_id' => $person->id, 'author_id' => $viewer->id, 'body' => 'Milik saya']);

    $this->actingAs($viewer)->get(route('admin.people.show', $person))
        ->assertInertia(fn ($page) => $page->has('notes', 2)
            ->where('notes', fn ($notes) => collect($notes)->pluck('body')->sort()->values()->all() === ['Milik saya', 'Publik']));
});

describe('who can read a note', function () {
    test('the author always can, others cannot read a private note', function () {
        $note = Note::factory()->create();

        expect($note->isVisibleTo($note->author))->toBeTrue()
            ->and($note->isVisibleTo(userWithRole(DefaultRole::Admin)))->toBeFalse();
    });

    test('public notes are visible to staff who may view notes only', function () {
        $note = Note::factory()->public()->create();

        expect($note->isVisibleTo(userWithRole(DefaultRole::Operator)))->toBeTrue()
            ->and($note->isVisibleTo(User::factory()->create()))->toBeFalse();
    });

    test('a note shared with a user or a role is visible to them', function () {
        $note = Note::factory()->create();
        $named = User::factory()->create();
        $roleHolder = userWithRole(DefaultRole::Operator);
        $outsider = userWithRole(DefaultRole::Admin);
        NoteShare::factory()->create(['note_id' => $note->id, 'shareable_id' => $named->id]);
        NoteShare::factory()->create(['note_id' => $note->id, 'shareable_type' => 'role', 'shareable_id' => Role::findByName('operator')->id]);

        expect($note->isVisibleTo($named))->toBeTrue()
            ->and($note->isVisibleTo($roleHolder))->toBeTrue()
            ->and($note->isVisibleTo($outsider))->toBeFalse();
    });

    test('a family-shared note is visible only to parents of that child', function () {
        $child = Person::factory()->create();
        $parent = User::factory()->create();
        $stranger = User::factory()->create();
        Parentship::factory()->create(['person_id' => $child->id, 'related_person_id' => $parent->person_id]);
        $shared = Note::factory()->sharedWithFamily()->create(['noteable_id' => $child->id, 'person_id' => $child->id]);
        $notShared = Note::factory()->public()->create(['noteable_id' => $child->id, 'person_id' => $child->id]);

        expect($shared->isVisibleTo($parent))->toBeTrue()
            ->and($shared->isVisibleTo($stranger))->toBeFalse()
            ->and($notShared->isVisibleTo($parent))->toBeFalse();
    });
});
