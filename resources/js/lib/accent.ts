/**
 * Browser-side twin of `App\Support\Brand::accentScale()`, used only to preview a colour while it is being
 * picked. The server is the source of truth for what ships to every page.
 */

type Oklch = { l: number; c: number; h: number };

function toLinear(channel: number): number {
    return channel <= 0.04045
        ? channel / 12.92
        : ((channel + 0.055) / 1.055) ** 2.4;
}

function channels(hex: string): [number, number, number] {
    const value = hex.replace('#', '');

    return [0, 2, 4].map((start) =>
        toLinear(parseInt(value.slice(start, start + 2), 16) / 255),
    ) as [number, number, number];
}

export function isHexColor(value: string): boolean {
    return /^#[0-9a-fA-F]{6}$/u.test(value);
}

function toOklch(hex: string): Oklch {
    const [red, green, blue] = channels(hex);
    const l = Math.cbrt(
        0.4122214708 * red + 0.5363325363 * green + 0.0514459929 * blue,
    );
    const m = Math.cbrt(
        0.2119034982 * red + 0.6806995451 * green + 0.1073969566 * blue,
    );
    const s = Math.cbrt(
        0.0883024619 * red + 0.2817188376 * green + 0.6299787005 * blue,
    );
    const a = 1.9779984951 * l - 2.428592205 * m + 0.4505937099 * s;
    const b = 0.0259040371 * l + 0.7827717662 * m - 0.808675766 * s;
    const hue = (Math.atan2(b, a) * 180) / Math.PI;

    return {
        l: 0.2104542553 * l + 0.793617785 * m - 0.0040720468 * s,
        c: Math.hypot(a, b),
        h: hue < 0 ? hue + 360 : hue,
    };
}

function css(lightness: number, chroma: number, hue: number): string {
    return `oklch(${(Math.max(0, Math.min(1, lightness)) * 100).toFixed(1)}% ${Math.max(0, chroma).toFixed(3)} ${hue.toFixed(1)})`;
}

function luminance(hex: string): number {
    const [red, green, blue] = channels(hex);

    return 0.2126 * red + 0.7152 * green + 0.0722 * blue;
}

/** Near-black or white, whichever reads better on the colour. */
export function textOn(hex: string): string {
    const own = luminance(hex);
    const dark = (own + 0.05) / (luminance('#171717') + 0.05);
    const light = 1.05 / (own + 0.05);

    return dark >= light ? '#171717' : '#ffffff';
}

/** CSS variables that recolour whatever element they are set on. */
export function accentVariables(hex: string): Record<string, string> {
    const { l, c, h } = toOklch(hex);
    const lighter: [number, number, number][] = [
        [50, 0.899, 0.13],
        [100, 0.792, 0.28],
        [200, 0.613, 0.53],
        [300, 0.352, 0.82],
    ];
    const darker: [number, number, number, number][] = [
        [500, 0.768, 0.98, 0.03],
        [600, 0.648, 0.84, 0.07],
        [700, 0.532, 0.66, 0.11],
        [800, 0.453, 0.52, 0.15],
        [900, 0.405, 0.42, 0.19],
    ];
    const variables: Record<string, string> = {};

    lighter.forEach(([step, fraction, ratio]) => {
        variables[`--color-lime-${step}`] = css(
            l + (1 - l) * fraction,
            c * ratio,
            h,
        );
    });
    variables['--color-lime-400'] = css(l, c, h);
    darker.forEach(([step, lightness, ratio, drop]) => {
        variables[`--color-lime-${step}`] = css(
            Math.max(0.2, Math.min(lightness, l - drop)),
            c * ratio,
            h,
        );
    });
    variables['--primary'] = variables['--color-lime-400'];
    variables['--primary-foreground'] = textOn(hex);

    return variables;
}
