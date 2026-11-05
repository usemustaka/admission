<?php

namespace App\Support;

/**
 * Just enough colour maths to turn one brand colour into a full accent scale: sRGB hex to OKLCH, and back to a
 * CSS `oklch()` string.
 */
class Oklch
{
    /**
     * @return array{l: float, c: float, h: float}
     */
    public static function fromHex(string $hex): array
    {
        [$red, $green, $blue] = array_map(
            fn (string $pair): float => self::toLinear(hexdec($pair) / 255),
            str_split(ltrim($hex, '#'), 2),
        );

        $l = 0.4122214708 * $red + 0.5363325363 * $green + 0.0514459929 * $blue;
        $m = 0.2119034982 * $red + 0.6806995451 * $green + 0.1073969566 * $blue;
        $s = 0.0883024619 * $red + 0.2817188376 * $green + 0.6299787005 * $blue;

        [$l, $m, $s] = [$l ** (1 / 3), $m ** (1 / 3), $s ** (1 / 3)];

        $lightness = 0.2104542553 * $l + 0.7936177850 * $m - 0.0040720468 * $s;
        $a = 1.9779984951 * $l - 2.4285922050 * $m + 0.4505937099 * $s;
        $b = 0.0259040371 * $l + 0.7827717662 * $m - 0.8086757660 * $s;

        $hue = rad2deg(atan2($b, $a));

        return ['l' => $lightness, 'c' => sqrt($a ** 2 + $b ** 2), 'h' => $hue < 0 ? $hue + 360 : $hue];
    }

    public static function css(float $lightness, float $chroma, float $hue): string
    {
        return sprintf('oklch(%.1f%% %.3f %.1f)', max(0, min(1, $lightness)) * 100, max(0, $chroma), $hue);
    }

    /**
     * WCAG contrast ratio between two sRGB hex colours.
     */
    public static function contrast(string $first, string $second): float
    {
        $one = self::luminance($first);
        $two = self::luminance($second);

        return (max($one, $two) + 0.05) / (min($one, $two) + 0.05);
    }

    private static function luminance(string $hex): float
    {
        [$red, $green, $blue] = array_map(
            fn (string $pair): float => self::toLinear(hexdec($pair) / 255),
            str_split(ltrim($hex, '#'), 2),
        );

        return 0.2126 * $red + 0.7152 * $green + 0.0722 * $blue;
    }

    private static function toLinear(float $channel): float
    {
        return $channel <= 0.04045 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4;
    }
}
