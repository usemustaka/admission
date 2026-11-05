<?php

namespace App\Support;

use App\Models\Parentship;
use App\Models\Registrant;
use App\Models\Setting;

class Announcement
{
    public const SETTING_KEY = 'admission.announcement_template';

    public const DEFAULT_TEMPLATE = "Assalamu'alaikum, Bapak/Ibu.\n\nKami informasikan hasil pendaftaran {nama} di {lembaga} ({gelombang}): {hasil}.\n\nInformasi selanjutnya akan kami sampaikan lewat nomor ini. Terima kasih.";

    /**
     * Placeholders an announcement template can use, with what they stand for.
     *
     * @var array<string, string>
     */
    public const PLACEHOLDERS = [
        '{nama}' => 'Nama anak',
        '{lembaga}' => 'Lembaga yang dituju',
        '{gelombang}' => 'Gelombang pendaftaran',
        '{hasil}' => 'Hasil ujian (tuntas atau belum tuntas)',
    ];

    public static function template(): string
    {
        $template = Setting::get(self::SETTING_KEY);

        return is_string($template) && trim($template) !== '' ? $template : self::DEFAULT_TEMPLATE;
    }

    public static function text(Registrant $registrant): string
    {
        $registrant->loadMissing(['person', 'organization', 'wave']);

        $result = ExamResult::for($registrant);

        return strtr(self::template(), [
            '{nama}' => $registrant->person->name,
            '{lembaga}' => $registrant->organization->name,
            '{gelombang}' => $registrant->wave->name,
            '{hasil}' => match (true) {
                $result['aspects'] === [] => 'dinyatakan lolos',
                $result['passed'] === true => 'tuntas',
                $result['passed'] === false => 'belum tuntas',
                default => 'belum ada nilai',
            },
        ]);
    }

    /**
     * The child's primary contact, who receives the announcement.
     *
     * @return array{name: string, phone: string}|null
     */
    public static function recipient(Registrant $registrant): ?array
    {
        $contact = Parentship::query()
            ->where('person_id', $registrant->person_id)
            ->where('is_primary_contact', true)
            ->with('relatedPerson')
            ->first()
            ?->relatedPerson;

        if ($contact === null || blank($contact->phone)) {
            return null;
        }

        return ['name' => $contact->name, 'phone' => $contact->phone];
    }

    /**
     * Phone numbers are typed locally (08…) or with a country code; WhatsApp wants digits with the country code.
     */
    public static function whatsappNumber(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return match (true) {
            str_starts_with($digits, '62') => $digits,
            str_starts_with($digits, '0') => '62'.substr($digits, 1),
            default => '62'.$digits,
        };
    }

    public static function whatsappUrl(Registrant $registrant): ?string
    {
        $recipient = self::recipient($registrant);

        return $recipient === null
            ? null
            : 'https://wa.me/'.self::whatsappNumber($recipient['phone']).'?text='.rawurlencode(self::text($registrant));
    }
}
