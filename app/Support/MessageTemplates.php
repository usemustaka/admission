<?php

namespace App\Support;

use App\Models\Setting;

/**
 * The WhatsApp message templates staff can edit: the announcement per outcome, the reminders for revision, payment
 * and exam, and the arrears reminder. Each kind has a settings key, a default text and the placeholders it may use.
 *
 * This class only knows templates and placeholders; `Announcement` fills them in for a registrant.
 */
class MessageTemplates
{
    /** Picks `ANNOUNCEMENT_PASSED`, `ANNOUNCEMENT_FAILED` or `ANNOUNCEMENT_INCOMPLETE` from the exam result. */
    public const ANNOUNCEMENT = 'announcement';

    public const ANNOUNCEMENT_PASSED = 'announcement-passed';

    public const ANNOUNCEMENT_FAILED = 'announcement-failed';

    public const ANNOUNCEMENT_INCOMPLETE = 'announcement-incomplete';

    public const REVISION = 'revision';

    public const PAYMENT = 'payment';

    public const EXAM = 'exam';

    public const ARREARS = 'payment-arrears';

    /** The settings key of the one generic announcement template that existed before there was one per outcome. */
    public const LEGACY_ANNOUNCEMENT_KEY = 'admission.announcement_template';

    public const MAX_LENGTH = 2000;

    /** WhatsApp accepts far more, but a message longer than this is rarely read to the end. */
    public const COMFORTABLE_LENGTH = 700;

    private const GREETING = "Assalamu'alaikum, {wali}.";

    private const CLOSING = 'Terima kasih.';

    /**
     * @var array<string, string>
     */
    private const TOKEN_DESCRIPTIONS = [
        '{nama}' => 'Nama anak',
        '{lembaga}' => 'Lembaga yang dituju',
        '{gelombang}' => 'Gelombang pendaftaran',
        '{wali}' => 'Sapaan wali, mis. "Bapak/Ibu Siti"',
        '{hasil}' => 'Hasil ujian (tuntas, belum tuntas, atau belum ada nilai)',
        '{alasan}' => 'Alasan permintaan revisi dari panitia',
        '{sisa}' => 'Sisa tagihan yang belum dibayar, mis. Rp1.250.000',
        '{rekening}' => 'Rekening tujuan pembayaran (dari pengaturan Rekening)',
        '{tanggal}' => 'Tanggal ujian, mis. Senin, 9 November 2026',
        '{sesi}' => 'Sesi ujian dan jamnya',
    ];

    /**
     * Every kind that has a template of its own (the `ANNOUNCEMENT` alias is not one of them).
     *
     * @return list<string>
     */
    public static function kinds(): array
    {
        return [
            self::ANNOUNCEMENT_PASSED,
            self::ANNOUNCEMENT_FAILED,
            self::ANNOUNCEMENT_INCOMPLETE,
            self::REVISION,
            self::PAYMENT,
            self::EXAM,
            self::ARREARS,
        ];
    }

    public static function isKind(string $kind): bool
    {
        return in_array($kind, self::kinds(), true);
    }

    /**
     * @return array{label: string, description: string, key: string, tokens: list<string>}
     */
    private static function definition(string $kind): array
    {
        $announcementTokens = ['{wali}', '{nama}', '{lembaga}', '{gelombang}', '{hasil}'];

        return match ($kind) {
            self::ANNOUNCEMENT_PASSED => ['label' => 'Pengumuman: tuntas', 'description' => 'Dikirim ke wali anak yang nilainya memenuhi KKM di semua aspek (atau yang tidak punya ujian).', 'key' => 'admission.announcement.passed', 'tokens' => $announcementTokens],
            self::ANNOUNCEMENT_FAILED => ['label' => 'Pengumuman: belum tuntas', 'description' => 'Dikirim ke wali anak yang nilainya belum memenuhi KKM.', 'key' => 'admission.announcement.failed', 'tokens' => $announcementTokens],
            self::ANNOUNCEMENT_INCOMPLETE => ['label' => 'Pengumuman: nilai belum ada', 'description' => 'Dipakai bila nilai ujian belum lengkap. Juga menjadi templat cadangan lama bila templat tuntas atau belum tuntas dikosongkan.', 'key' => 'admission.announcement.incomplete', 'tokens' => $announcementTokens],
            self::REVISION => ['label' => 'Pengingat: perlu diperbaiki', 'description' => 'Untuk wali yang pendaftarannya diminta direvisi.', 'key' => 'admission.reminder.revision', 'tokens' => ['{wali}', '{nama}', '{lembaga}', '{gelombang}', '{alasan}']],
            self::PAYMENT => ['label' => 'Pengingat: pembayaran', 'description' => 'Untuk wali yang tagihan pendaftarannya belum lunas.', 'key' => 'admission.reminder.payment', 'tokens' => ['{wali}', '{nama}', '{lembaga}', '{gelombang}', '{sisa}', '{rekening}']],
            self::EXAM => ['label' => 'Pengingat: ujian', 'description' => 'Untuk wali yang anaknya sudah punya jadwal ujian.', 'key' => 'admission.reminder.exam', 'tokens' => ['{wali}', '{nama}', '{lembaga}', '{gelombang}', '{tanggal}', '{sesi}']],
            self::ARREARS => ['label' => 'Pengingat: tunggakan', 'description' => 'Dipakai di laporan Tunggakan untuk menagih sisa tagihan.', 'key' => 'billing.reminder_template', 'tokens' => ['{wali}', '{nama}', '{lembaga}', '{gelombang}', '{sisa}', '{rekening}']],
            default => throw new \InvalidArgumentException("Jenis pesan tidak dikenal: {$kind}"),
        };
    }

    public static function label(string $kind): string
    {
        return self::definition($kind)['label'];
    }

    public static function description(string $kind): string
    {
        return self::definition($kind)['description'];
    }

    public static function settingKey(string $kind): string
    {
        return self::definition($kind)['key'];
    }

    public static function defaultTemplate(string $kind): string
    {
        $greeting = self::GREETING;
        $closing = self::CLOSING;

        return match ($kind) {
            self::ANNOUNCEMENT_PASSED => "{$greeting}\n\nAlhamdulillah, hasil pendaftaran {nama} di {lembaga} ({gelombang}): {hasil}. Selamat!\n\nLangkah berikutnya akan kami sampaikan lewat nomor ini. {$closing}",
            self::ANNOUNCEMENT_FAILED => "{$greeting}\n\nKami informasikan hasil pendaftaran {nama} di {lembaga} ({gelombang}): {hasil}. Silakan hubungi panitia untuk informasi lebih lanjut.\n\n{$closing}",
            self::ANNOUNCEMENT_INCOMPLETE => "Assalamu'alaikum, Bapak/Ibu.\n\nKami informasikan hasil pendaftaran {nama} di {lembaga} ({gelombang}): {hasil}.\n\nInformasi selanjutnya akan kami sampaikan lewat nomor ini. {$closing}",
            self::REVISION => "{$greeting}\n\nPendaftaran {nama} di {lembaga} ({gelombang}) perlu diperbaiki: {alasan}\n\nMohon lengkapi lewat akun pendaftaran, lalu kirim ulang. {$closing}",
            self::PAYMENT => "{$greeting}\n\nKami mengingatkan pembayaran pendaftaran {nama} di {lembaga} ({gelombang}). Sisa tagihan: {sisa}.\n\nPembayaran lewat {rekening}. Setelah membayar, mohon unggah bukti di akun pendaftaran. {$closing}",
            self::EXAM => "{$greeting}\n\nPengingat jadwal ujian {nama} di {lembaga} ({gelombang}): {tanggal}, {sesi}. Mohon hadir tepat waktu. {$closing}",
            self::ARREARS => "{$greeting}\n\nKami mengingatkan tagihan {nama} di {lembaga} yang masih tersisa {sisa}. Mohon dilunasi lewat {rekening}.\n\nBila sudah membayar, mohon abaikan pesan ini dan unggah bukti pembayaran di akun pendaftaran. {$closing}",
            default => throw new \InvalidArgumentException("Jenis pesan tidak dikenal: {$kind}"),
        };
    }

    /**
     * The text to send: what staff saved, or the default. A blank announcement template for tuntas or belum tuntas
     * falls back to the single template that was used before there was one per outcome.
     */
    public static function template(string $kind): string
    {
        $saved = self::saved();
        $own = $saved[self::settingKey($kind)] ?? null;

        if (self::filled($own)) {
            return $own;
        }

        $legacy = $saved[self::LEGACY_ANNOUNCEMENT_KEY] ?? null;

        if (in_array($kind, [self::ANNOUNCEMENT_PASSED, self::ANNOUNCEMENT_FAILED, self::ANNOUNCEMENT_INCOMPLETE], true) && self::filled($legacy)) {
            return $legacy;
        }

        return self::defaultTemplate($kind);
    }

    /**
     * Placeholders a kind may use, with what they stand for.
     *
     * @return array<string, string>
     */
    public static function tokens(string $kind): array
    {
        return collect(self::definition($kind)['tokens'])
            ->mapWithKeys(fn (string $token): array => [$token => self::TOKEN_DESCRIPTIONS[$token]])
            ->all();
    }

    /**
     * Placeholders written in the text that the kind does not know (typos, or one of another kind), once each.
     *
     * @return list<string>
     */
    public static function unknownTokens(string $kind, string $text): array
    {
        preg_match_all('/\{[^{}\s]*\}/u', $text, $matches);

        return array_values(array_unique(array_diff($matches[0], array_keys(self::tokens($kind)))));
    }

    /**
     * Fill the template of a kind. `$values` is keyed by the plain token name (`nama`, not `{nama}`); a placeholder
     * without a value stays as written, so a gap is visible instead of silently empty.
     *
     * @param  array<string, string>  $values
     */
    public static function render(string $kind, array $values, ?string $template = null): string
    {
        $replacements = [];

        foreach ($values as $name => $value) {
            $replacements['{'.$name.'}'] = $value;
        }

        return strtr($template ?? self::template($kind), $replacements);
    }

    /**
     * Sample values for the settings page preview. Announcement kinds show their own outcome.
     *
     * @return array<string, string>
     */
    public static function sampleValues(string $kind): array
    {
        return [
            'wali' => 'Bapak/Ibu Siti',
            'nama' => 'Ahmad Fauzi',
            'lembaga' => 'SMP Mustaka',
            'gelombang' => 'Gelombang 1',
            'hasil' => match ($kind) {
                self::ANNOUNCEMENT_FAILED => 'belum tuntas',
                self::ANNOUNCEMENT_INCOMPLETE => 'belum ada nilai',
                default => 'tuntas',
            },
            'alasan' => 'foto akta kelahiran belum terbaca jelas',
            'sisa' => Money::format(1250000),
            'rekening' => 'Bank Mustaka 1234567890 a.n. Yayasan Mustaka',
            'tanggal' => 'Senin, 9 November 2026',
            'sesi' => 'Sesi 1 · 08.00 s.d. 10.00',
        ];
    }

    /**
     * The saved texts of every kind (and the legacy key) in one query.
     *
     * @return array<string, mixed>
     */
    private static function saved(): array
    {
        $keys = [self::LEGACY_ANNOUNCEMENT_KEY, ...array_map(self::settingKey(...), self::kinds())];

        return Setting::query()->whereIn('key', $keys)->pluck('value', 'key')->all();
    }

    private static function filled(mixed $text): bool
    {
        return is_string($text) && trim($text) !== '';
    }
}
