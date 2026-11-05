<?php

namespace App\Contracts;

/**
 * An entity that notes can be attached to. The subject is the person (a
 * child) the note is about, which decides who counts as "the family".
 */
interface HasNoteSubject
{
    public function notePersonId(): ?int;
}
