<?php

namespace App\Support;

use Illuminate\Support\Str;

class PersonName
{
    /**
     * Whether two names are the same person's name, ignoring case, spacing, and punctuation.
     */
    public static function same(string $first, string $second): bool
    {
        return self::normalize($first) === self::normalize($second);
    }

    /**
     * The name lower-cased with accents, spaces and punctuation removed, to compare names that were typed differently.
     */
    public static function normalize(string $name): string
    {
        return (string) preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($name)));
    }
}
