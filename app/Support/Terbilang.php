<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Amounts spelled out in Indonesian, as printed on a receipt: 1500000 becomes "Satu juta lima ratus ribu rupiah".
 */
class Terbilang
{
    private const ONES = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];

    private const SCALES = [1_000_000_000_000 => 'triliun', 1_000_000_000 => 'miliar', 1_000_000 => 'juta'];

    public static function rupiah(int $amount): string
    {
        return ucfirst(self::words($amount)).' rupiah';
    }

    public static function words(int $amount): string
    {
        if ($amount < 0) {
            throw new InvalidArgumentException('Nominal tidak boleh negatif.');
        }

        if ($amount === 0) {
            return 'nol';
        }

        return self::spell($amount);
    }

    private static function spell(int $number): string
    {
        if ($number < 12) {
            return self::ONES[$number];
        }

        if ($number < 20) {
            return self::ONES[$number - 10].' belas';
        }

        if ($number < 100) {
            return self::join(self::ONES[intdiv($number, 10)].' puluh', $number % 10);
        }

        if ($number < 200) {
            return self::join('seratus', $number - 100);
        }

        if ($number < 1000) {
            return self::join(self::ONES[intdiv($number, 100)].' ratus', $number % 100);
        }

        if ($number < 2000) {
            return self::join('seribu', $number - 1000);
        }

        if ($number < 1_000_000) {
            return self::join(self::spell(intdiv($number, 1000)).' ribu', $number % 1000);
        }

        foreach (self::SCALES as $size => $name) {
            if ($number >= $size) {
                return self::join(self::spell(intdiv($number, $size)).' '.$name, $number % $size);
            }
        }

        return (string) $number;
    }

    private static function join(string $head, int $rest): string
    {
        return $rest === 0 ? $head : $head.' '.self::spell($rest);
    }
}
