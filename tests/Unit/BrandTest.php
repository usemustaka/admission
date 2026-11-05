<?php

use App\Support\Brand;
use App\Support\Oklch;

test('a hex colour converts to oklch', function () {
    ['l' => $lightness, 'c' => $chroma, 'h' => $hue] = Oklch::fromHex('#a3e635');

    expect($lightness)->toBeGreaterThan(0.83)->toBeLessThan(0.86)
        ->and($chroma)->toBeGreaterThan(0.2)
        ->and($hue)->toBeGreaterThan(125)->toBeLessThan(133);
});

test('the brand colour becomes step 400 of the accent scale', function () {
    $scale = Brand::accentScale('#2563eb');
    ['l' => $lightness, 'h' => $hue] = Oklch::fromHex('#2563eb');

    expect($scale)->toHaveCount(10)
        ->and($scale['--color-lime-400'])->toBe(Oklch::css($lightness, Oklch::fromHex('#2563eb')['c'], $hue));
});

test('lighter steps get lighter and darker steps get darker', function () {
    $lightness = fn (string $css): float => (float) explode('%', substr($css, 6))[0];
    $scale = Brand::accentScale('#fb923c');

    $steps = array_map($lightness, [$scale['--color-lime-50'], $scale['--color-lime-100'], $scale['--color-lime-300'], $scale['--color-lime-400'], $scale['--color-lime-600'], $scale['--color-lime-800']]);

    $sorted = $steps;
    rsort($sorted);

    expect($steps)->toBe($sorted);
});

test('text on a brand colour is whichever of dark and white reads better', function () {
    expect(Brand::textOn('#a3e635'))->toBe('#171717')
        ->and(Brand::textOn('#1e3a8a'))->toBe('#ffffff')
        ->and(Brand::textOn('#fbbf24'))->toBe('#171717');
});

test('a dark brand colour keeps its text steps readable on white', function () {
    $scale = Brand::accentScale('#1e3a8a');
    $lightness = (float) explode('%', substr($scale['--color-lime-700'], 6))[0];

    expect($lightness)->toBeLessThan(55);
});
