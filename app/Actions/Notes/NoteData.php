<?php

namespace App\Actions\Notes;

use App\Enums\NoteUrgency;
use App\Enums\NoteVisibility;
use Illuminate\Http\Request;

final readonly class NoteData
{
    /**
     * @param  list<int>  $userIds
     * @param  list<int>  $roleIds
     */
    public function __construct(
        public string $body,
        public NoteUrgency $urgency,
        public NoteVisibility $visibility,
        public bool $sharedWithFamily = false,
        public array $userIds = [],
        public array $roleIds = [],
    ) {}

    /**
     * Build from a request that already passed the note validation rules.
     */
    public static function fromRequest(Request $request): self
    {
        return new self(
            body: $request->string('body')->toString(),
            urgency: $request->enum('urgency', NoteUrgency::class) ?? NoteUrgency::Normal,
            visibility: $request->enum('visibility', NoteVisibility::class) ?? NoteVisibility::Private,
            sharedWithFamily: $request->boolean('shared_with_family'),
            userIds: array_values(array_map(intval(...), $request->array('user_ids'))),
            roleIds: array_values(array_map(intval(...), $request->array('role_ids'))),
        );
    }
}
