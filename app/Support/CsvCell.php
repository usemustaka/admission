<?php

namespace App\Support;

class CsvCell
{
    /**
     * Spreadsheets run text that starts with these characters as a formula, so such cells get a leading quote.
     */
    public static function safe(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'{$value}";
        }

        return $value;
    }
}
