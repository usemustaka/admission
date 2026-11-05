<?php

use App\Support\Terbilang;

test('spells amounts in Indonesian', function (int $amount, string $words) {
    expect(Terbilang::rupiah($amount))->toBe($words);
})->with([
    'zero' => [0, 'Nol rupiah'],
    'one' => [1, 'Satu rupiah'],
    'eleven' => [11, 'Sebelas rupiah'],
    'teens' => [15, 'Lima belas rupiah'],
    'tens' => [20, 'Dua puluh rupiah'],
    'tens with ones' => [97, 'Sembilan puluh tujuh rupiah'],
    'a hundred' => [100, 'Seratus rupiah'],
    'a hundred and something' => [123, 'Seratus dua puluh tiga rupiah'],
    'hundreds' => [500, 'Lima ratus rupiah'],
    'a thousand' => [1_000, 'Seribu rupiah'],
    'a thousand and something' => [1_250, 'Seribu dua ratus lima puluh rupiah'],
    'thousands' => [2_000, 'Dua ribu rupiah'],
    'eleven thousand' => [11_000, 'Sebelas ribu rupiah'],
    'a hundred thousand' => [100_000, 'Seratus ribu rupiah'],
    'one and a half million' => [1_500_000, 'Satu juta lima ratus ribu rupiah'],
    'with a trailing hundred' => [2_250_500, 'Dua juta dua ratus lima puluh ribu lima ratus rupiah'],
    'a thousand inside millions' => [1_001_000, 'Satu juta seribu rupiah'],
    'a billion' => [1_000_000_000, 'Satu miliar rupiah'],
    'a trillion and more' => [2_345_678_901_234, 'Dua triliun tiga ratus empat puluh lima miliar enam ratus tujuh puluh delapan juta sembilan ratus satu ribu dua ratus tiga puluh empat rupiah'],
]);

test('refuses a negative amount', function () {
    Terbilang::rupiah(-1);
})->throws(InvalidArgumentException::class);
