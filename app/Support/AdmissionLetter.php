<?php

namespace App\Support;

use App\Enums\RegistrantStatus;
use App\Models\Registrant;

/**
 * The official letter that tells a family their child was accepted. It carries no letter number: the school
 * letterhead (logo, name and contact from the settings) goes on top and the page fills it in.
 */
class AdmissionLetter
{
    /**
     * Whether a letter can be printed: the data is valid, the NIK check is done and the result is a pass. A
     * registration without exam aspects counts as a pass, like the announcement does.
     */
    public static function canPrint(Registrant $registrant): bool
    {
        return ! $registrant->needs_identity_review
            && in_array($registrant->status, [RegistrantStatus::Validated, RegistrantStatus::Announced, RegistrantStatus::Placed], true)
            && Announcement::announcementKind($registrant) === MessageTemplates::ANNOUNCEMENT_PASSED;
    }

    /**
     * @return array{date: string, recipient: string, subject: string, child: array{name: string, nik: string|null, organization: string, wave: string}, paragraphs: list<string>, closing: string, signer: string}
     */
    public static function data(Registrant $registrant): array
    {
        $registrant->loadMissing(['person', 'organization', 'wave']);

        $recipient = Announcement::recipient($registrant);
        $child = $registrant->person->name;
        $organization = $registrant->organization->name;
        $wave = $registrant->wave->name;

        $outstanding = RegistrantBillSummary::totals(
            $registrant->bills()->with(['items' => fn ($items) => $items->withPaymentTotals()])->get(),
        )['outstanding'];

        $paragraphs = [
            "Berdasarkan hasil seleksi penerimaan siswa baru {$organization} ({$wave}), dengan ini kami menyampaikan bahwa ananda {$child} dinyatakan DITERIMA sebagai calon siswa {$organization}.",
            $outstanding > 0
                ? 'Selanjutnya, mohon Bapak/Ibu menyelesaikan administrasi sesuai tagihan yang berlaku, dengan sisa pembayaran sebesar '.Money::format($outstanding).', dan mengikuti arahan panitia untuk proses penempatan.'
                : 'Seluruh administrasi pembayaran telah terselesaikan. Mohon Bapak/Ibu mengikuti arahan panitia untuk proses penempatan.',
            'Demikian pemberitahuan ini kami sampaikan. Atas perhatian dan kerja sama Bapak/Ibu, kami ucapkan terima kasih.',
        ];

        return [
            'date' => today()->locale('id')->translatedFormat('j F Y'),
            'recipient' => $recipient === null ? 'Bapak/Ibu Orang Tua/Wali' : 'Bapak/Ibu '.$recipient['name'],
            'subject' => 'Pemberitahuan Hasil Seleksi Penerimaan Siswa Baru',
            'child' => [
                'name' => $child,
                'nik' => $registrant->person->nik,
                'organization' => $organization,
                'wave' => $wave,
            ],
            'paragraphs' => $paragraphs,
            'closing' => 'Hormat kami,',
            'signer' => 'Panitia Penerimaan Siswa Baru',
        ];
    }
}
