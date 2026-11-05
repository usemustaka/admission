<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

/**
 * The identity of this installation: name, logo, colours, contact and payment details, all editable by staff so
 * the application can be white-labelled. Missing values fall back to defaults, so a fresh install still looks right.
 */
class Brand
{
    public const DEFAULT_ACCENT = '#a3e635';

    public const DEFAULT_WELCOME_TITLE = 'Pendaftaran siswa baru';

    public const DEFAULT_WELCOME_DESCRIPTION = 'Daftarkan anak, lengkapi data, bayar, dan pantau sampai pengumuman. Semuanya dari HP.';

    /** @var array<string, mixed>|null */
    private static ?array $values = null;

    /**
     * Forget what was read, so the next call reads the settings again.
     */
    public static function flush(): void
    {
        self::$values = null;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::values()[$key] ?? null;

        return $value === null || $value === '' ? $default : $value;
    }

    public static function name(): string
    {
        return (string) self::get('brand.name', config('app.name'));
    }

    public static function logoUrl(): ?string
    {
        return self::fileUrl('brand.logo_path');
    }

    public static function faviconUrl(): ?string
    {
        return self::fileUrl('brand.favicon_path');
    }

    public static function accent(): string
    {
        $color = (string) self::get('brand.accent_color', self::DEFAULT_ACCENT);

        return preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1 ? strtolower($color) : self::DEFAULT_ACCENT;
    }

    /**
     * CSS custom properties that recolour the whole interface. Empty for the default accent, so the stylesheet's
     * own lime stays untouched.
     *
     * The brand colour becomes step 400 of the accent scale (the colour of primary buttons); lighter steps fade
     * towards white for soft backgrounds and darker steps stay dark enough for text on them.
     *
     * @return array<string, string>
     */
    public static function themeVariables(): array
    {
        $accent = self::accent();

        if ($accent === self::DEFAULT_ACCENT) {
            return [];
        }

        return self::accentScale($accent) + ['--primary-foreground' => self::textOn($accent), '--sidebar-primary-foreground' => self::textOn($accent)];
    }

    /**
     * @return array<string, string>
     */
    public static function accentScale(string $hex): array
    {
        ['l' => $l, 'c' => $c, 'h' => $h] = Oklch::fromHex($hex);

        $lighter = [50 => [0.899, 0.13], 100 => [0.792, 0.28], 200 => [0.613, 0.53], 300 => [0.352, 0.82]];
        $darker = [500 => [0.768, 0.98, 0.03], 600 => [0.648, 0.84, 0.07], 700 => [0.532, 0.66, 0.11], 800 => [0.453, 0.52, 0.15], 900 => [0.405, 0.42, 0.19]];

        $scale = [];

        foreach ($lighter as $step => [$fraction, $chromaRatio]) {
            $scale["--color-lime-{$step}"] = Oklch::css($l + (1 - $l) * $fraction, $c * $chromaRatio, $h);
        }

        $scale['--color-lime-400'] = Oklch::css($l, $c, $h);

        foreach ($darker as $step => [$lightness, $chromaRatio, $drop]) {
            $scale["--color-lime-{$step}"] = Oklch::css(max(0.2, min($lightness, $l - $drop)), $c * $chromaRatio, $h);
        }

        ksort($scale);

        return $scale;
    }

    /**
     * Text colour for a solid brand-coloured surface: whichever of near-black and white is easier to read.
     */
    public static function textOn(string $hex): string
    {
        return Oklch::contrast($hex, '#171717') >= Oklch::contrast($hex, '#ffffff') ? '#171717' : '#ffffff';
    }

    /**
     * What every page receives as the `brand` prop.
     *
     * @return array<string, mixed>
     */
    public static function shared(): array
    {
        return [
            'name' => self::name(),
            'organization' => self::get('foundation.name'),
            'tagline' => self::get('brand.tagline'),
            'logo_url' => self::logoUrl(),
            'show_name' => (bool) self::get('brand.show_name', true),
            'accent' => self::accent(),
            'contact' => [
                'phone' => self::get('contact.phone'),
                'whatsapp' => self::get('contact.whatsapp'),
                'email' => self::get('contact.email'),
                'address' => self::get('contact.address'),
                'website' => self::get('contact.website'),
            ],
            'payment' => [
                'bank_name' => self::get('payment.bank_name'),
                'account_number' => self::get('payment.account_number'),
                'account_holder' => self::get('payment.account_holder'),
                'instructions' => self::get('payment.instructions'),
            ],
            'public' => [
                'welcome_title' => self::get('brand.welcome_title', self::DEFAULT_WELCOME_TITLE),
                'welcome_description' => self::get('brand.welcome_description', self::DEFAULT_WELCOME_DESCRIPTION),
                'footer_text' => self::get('brand.footer_text'),
            ],
        ];
    }

    /**
     * Make framework features that read config (page titles, emails) use the brand name.
     */
    public static function applyToConfig(): void
    {
        $name = rescue(fn (): ?string => self::get('brand.name'), report: false);

        if (is_string($name) && $name !== '') {
            config(['app.name' => $name, 'mail.from.name' => $name]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function values(): array
    {
        return self::$values ??= Setting::query()
            ->where(fn ($query) => $query->where('key', 'like', 'brand.%')
                ->orWhere('key', 'like', 'contact.%')
                ->orWhere('key', 'like', 'payment.%')
                ->orWhere('key', 'foundation.name'))
            ->pluck('value', 'key')
            ->all();
    }

    private static function fileUrl(string $key): ?string
    {
        $path = self::get($key);

        return is_string($path) && Storage::disk('public')->exists($path) ? Storage::disk('public')->url($path) : null;
    }
}
