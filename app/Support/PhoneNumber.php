<?php

namespace App\Support;

use Illuminate\Support\Str;

class PhoneNumber
{
    /**
     * What gets stored: digits with an optional leading `+`, so `0812-3456-7890` and `0812 3456 7890` are one number.
     * Returns null for a blank value.
     */
    public static function clean(?string $phone): ?string
    {
        $phone = trim((string) $phone);

        if ($phone === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return str_starts_with($phone, '+') ? "+{$digits}" : $digits;
    }

    /**
     * The number with the country code and nothing else (6281234567890), whichever way it was typed.
     */
    public static function canonical(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return match (true) {
            str_starts_with($digits, '62') => $digits,
            str_starts_with($digits, '0') => '62'.substr($digits, 1),
            default => '62'.$digits,
        };
    }

    public static function same(?string $first, ?string $second): bool
    {
        return filled($first) && filled($second) && self::canonical($first) === self::canonical($second);
    }

    /**
     * The ways the same number can be written once spaces and dashes are removed (081…, 6281…, 81…).
     *
     * @return list<string>
     */
    public static function variants(string $phone): array
    {
        $rest = substr(self::canonical($phone), 2);

        return ["0{$rest}", "62{$rest}", $rest];
    }

    /**
     * SQL that strips the separators people type from a phone column, to compare it with `variants()`.
     */
    public static function strippedColumn(string $column = 'phone'): string
    {
        return collect(['-', ' ', '+', '.', '(', ')'])->reduce(fn (string $sql, string $character): string => "replace({$sql}, '{$character}', '')", $column);
    }

    /**
     * Enough of the number to tell two people apart (0812••••7890) without exposing it.
     */
    public static function mask(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        return strlen($digits) >= 9 ? Str::mask($digits, '•', 4, -4) : Str::mask($digits, '•', 2, -2);
    }
}
