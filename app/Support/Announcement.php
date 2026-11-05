<?php

namespace App\Support;

use App\Enums\RegistrantStatus;
use App\Models\Parentship;
use App\Models\Person;
use App\Models\Registrant;
use InvalidArgumentException;

/**
 * The WhatsApp messages sent to a registrant's family: the announcement of the result and the reminders. Templates
 * live in `MessageTemplates` (editable under Pendaftaran > Templat pesan); this class fills them in for one
 * registrant and finds who to send them to.
 *
 * For lists, quick views and detail pages call `whatsappUrlFor($registrant, $kind)` for the link and `textFor(...)` for
 * the text to copy. `$kind` is one of the `MessageTemplates` constants:
 *
 * - `announcement`: the result announcement; picks tuntas, belum tuntas or nilai belum ada from the exam scores
 * - `revision`, `payment`, `exam`: reminders for a registration under revision, an unpaid bill, an upcoming exam
 * - `payment-arrears`: the reminder of the arrears report
 */
class Announcement
{
    /** The single generic template that existed before there was one per outcome. Still read as a fallback. */
    public const SETTING_KEY = MessageTemplates::LEGACY_ANNOUNCEMENT_KEY;

    /**
     * The announcement template for a result kind (`announcement-passed`, `-failed` or `-incomplete`), or for the
     * outcome when called without one: the generic fallback of nilai belum ada.
     */
    public static function template(string $kind = MessageTemplates::ANNOUNCEMENT_INCOMPLETE): string
    {
        return MessageTemplates::template($kind);
    }

    /**
     * Which announcement template fits the registrant's exam result. A registration without exam aspects counts as
     * tuntas.
     */
    public static function announcementKind(Registrant $registrant): string
    {
        $result = ExamResult::for($registrant);

        return match (true) {
            $result['aspects'] === [], $result['passed'] === true => MessageTemplates::ANNOUNCEMENT_PASSED,
            $result['passed'] === false => MessageTemplates::ANNOUNCEMENT_FAILED,
            default => MessageTemplates::ANNOUNCEMENT_INCOMPLETE,
        };
    }

    /**
     * The result announcement. Same as `textFor($registrant, 'announcement')`.
     */
    public static function text(Registrant $registrant): string
    {
        return self::textFor($registrant, MessageTemplates::ANNOUNCEMENT);
    }

    /**
     * The message of one kind for a registrant, ready to copy or send. `$overrides` replaces placeholder values by
     * plain name (`['sisa' => 'Rp1.000.000']`), which skips looking them up; the arrears report uses it to avoid
     * recomputing what its query already knows.
     *
     * @param  array<string, string>  $overrides
     */
    public static function textFor(Registrant $registrant, string $kind, array $overrides = []): string
    {
        return self::build($registrant, $kind, $overrides, self::recipient($registrant));
    }

    /**
     * The `wa.me` link that opens a chat with the family's contact and the message of that kind filled in
     * (URL-encoded), or null when nobody has a phone number.
     *
     * @param  array<string, string>  $overrides
     */
    public static function whatsappUrlFor(Registrant $registrant, string $kind, array $overrides = []): ?string
    {
        $recipient = self::recipient($registrant);

        return $recipient === null
            ? null
            : 'https://wa.me/'.self::whatsappNumber($recipient['phone']).'?text='.rawurlencode(self::build($registrant, $kind, $overrides, $recipient));
    }

    /**
     * The link of the result announcement. Same as `whatsappUrlFor($registrant, 'announcement')`.
     */
    public static function whatsappUrl(Registrant $registrant): ?string
    {
        return self::whatsappUrlFor($registrant, MessageTemplates::ANNOUNCEMENT);
    }

    /**
     * Who receives the message: the child's primary contact if they have a phone, otherwise any other parent
     * with a phone (lowest id first), otherwise the person who registered the child.
     *
     * @return array{name: string, phone: string}|null
     */
    public static function recipient(Registrant $registrant): ?array
    {
        $parentships = Parentship::query()
            ->where('person_id', $registrant->person_id)
            ->with('relatedPerson')
            ->orderByDesc('is_primary_contact')
            ->orderBy('id')
            ->get();

        $candidates = $parentships->pluck('relatedPerson')->push($registrant->registeredBy()->first());

        $contact = $candidates->first(fn (?Person $person): bool => $person !== null && filled($person->phone));

        return $contact === null ? null : ['name' => $contact->name, 'phone' => $contact->phone];
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

    /**
     * @param  array<string, string>  $overrides
     * @param  array{name: string, phone: string}|null  $recipient
     */
    private static function build(Registrant $registrant, string $kind, array $overrides, ?array $recipient): string
    {
        $kind = $kind === MessageTemplates::ANNOUNCEMENT ? self::announcementKind($registrant) : $kind;

        if (! MessageTemplates::isKind($kind)) {
            throw new InvalidArgumentException("Jenis pesan tidak dikenal: {$kind}");
        }

        $registrant->loadMissing(['person', 'organization', 'wave']);

        $values = [
            'nama' => $registrant->person->name,
            'lembaga' => $registrant->organization->name,
            'gelombang' => $registrant->wave->name,
            'wali' => $recipient === null ? 'Bapak/Ibu' : 'Bapak/Ibu '.$recipient['name'],
            ...$overrides,
        ];

        $lookups = match ($kind) {
            MessageTemplates::ANNOUNCEMENT_PASSED, MessageTemplates::ANNOUNCEMENT_FAILED, MessageTemplates::ANNOUNCEMENT_INCOMPLETE => ['hasil' => fn (): string => self::resultWording($registrant)],
            MessageTemplates::REVISION => ['alasan' => fn (): string => self::revisionReason($registrant)],
            MessageTemplates::PAYMENT, MessageTemplates::ARREARS => [
                'sisa' => fn (): string => Money::format(self::outstanding($registrant)),
                'rekening' => fn (): string => self::bankAccount(),
            ],
            MessageTemplates::EXAM => $registrant->examSession()->with('day')->first() === null
                ? ['tanggal' => fn (): string => 'jadwal belum dipilih', 'sesi' => fn (): string => 'silakan pilih sesi di akun pendaftaran']
                : [
                    'tanggal' => fn (): string => (string) $registrant->examSession->day->date->locale('id')->translatedFormat('l, j F Y'),
                    'sesi' => fn (): string => ExamSchedulePresenter::label($registrant->examSession),
                ],
        };

        foreach ($lookups as $name => $lookup) {
            $values[$name] ??= $lookup();
        }

        return MessageTemplates::render($kind, $values);
    }

    private static function resultWording(Registrant $registrant): string
    {
        $result = ExamResult::for($registrant);

        return match (true) {
            $result['aspects'] === [] => 'dinyatakan lolos',
            $result['passed'] === true => 'tuntas',
            $result['passed'] === false => 'belum tuntas',
            default => 'belum ada nilai',
        };
    }

    private static function revisionReason(Registrant $registrant): string
    {
        $note = $registrant->statusChanges()
            ->reorder('id', 'desc')
            ->where('to_status', RegistrantStatus::Revision)
            ->where('is_bypass', false)
            ->value('note');

        return filled($note) ? rtrim(trim($note), '.').'.' : 'mohon periksa kembali data pendaftaran.';
    }

    /**
     * What is still owed on every bill of the registrant, counted per item so one item's surplus never hides another's debt.
     */
    private static function outstanding(Registrant $registrant): int
    {
        $bills = $registrant->bills()->with(['items' => fn ($items) => $items->withPaymentTotals()])->get();

        return RegistrantBillSummary::totals($bills)['outstanding'];
    }

    private static function bankAccount(): string
    {
        $parts = array_filter([
            Brand::get('payment.bank_name'),
            Brand::get('payment.account_number'),
            Brand::get('payment.account_holder') === null ? null : 'a.n. '.Brand::get('payment.account_holder'),
        ]);

        return $parts === [] ? 'rekening yang tertera di akun pendaftaran' : implode(' ', $parts);
    }
}
