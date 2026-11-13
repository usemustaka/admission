<?php

namespace App\Enums;

use App\Contracts\HasLabel;

/**
 * A file a parent uploads for a registration. The `core` types are asked for by the ruleset (each one required or
 * optional); the `supporting` types are evidence for a scholarship and are always optional and always offered.
 */
enum DocumentType: string implements HasLabel
{
    case FamilyCard = 'family-card';
    case BirthCertificate = 'birth-certificate';
    case ReportCard = 'report-card';
    case Diploma = 'diploma';
    case AchievementCertificate = 'achievement-certificate';
    case TahfidzCertificate = 'tahfidz-certificate';
    case Kip = 'kip';
    case HardshipLetter = 'hardship-letter';
    case OtherSupport = 'other-support';

    public function label(): string
    {
        return match ($this) {
            self::FamilyCard => 'Kartu Keluarga (KK)',
            self::BirthCertificate => 'Akta kelahiran',
            self::ReportCard => 'Rapor semester 1-5',
            self::Diploma => 'Ijazah / SKL',
            self::AchievementCertificate => 'Sertifikat prestasi',
            self::TahfidzCertificate => 'Sertifikat tahfidz',
            self::Kip => 'Kartu Indonesia Pintar (KIP)',
            self::HardshipLetter => 'Surat keterangan tidak mampu',
            self::OtherSupport => 'Berkas pendukung lainnya',
        };
    }

    /**
     * How many files the type holds. A report card is one file per semester.
     */
    public function maxFiles(): int
    {
        return match ($this) {
            self::ReportCard => 5,
            self::AchievementCertificate, self::OtherSupport => 5,
            self::TahfidzCertificate => 2,
            default => 1,
        };
    }

    /**
     * What one file of the type is called, for example the semester of a report card.
     */
    public function slotLabel(int $slot): string
    {
        return match (true) {
            $this === self::ReportCard => "Semester {$slot}",
            $this->maxFiles() === 1 => $this->label(),
            default => "Berkas {$slot}",
        };
    }

    public function isSupporting(): bool
    {
        return in_array($this, self::supporting(), true);
    }

    /**
     * The scholarship this file points at, if it points at one.
     */
    public function scholarshipKind(): ?ScholarshipKind
    {
        return match ($this) {
            self::AchievementCertificate => ScholarshipKind::Achievement,
            self::TahfidzCertificate => ScholarshipKind::Tahfidz,
            self::Kip, self::HardshipLetter => ScholarshipKind::Hardship,
            default => null,
        };
    }

    /**
     * Types a ruleset can ask for.
     *
     * @return list<self>
     */
    public static function core(): array
    {
        return [self::FamilyCard, self::BirthCertificate, self::ReportCard, self::Diploma];
    }

    /**
     * Every built-in type a ruleset can switch on or off, each as none, optional or required.
     *
     * @return list<self>
     */
    public static function configurable(): array
    {
        return [...self::core(), ...self::supporting()];
    }

    /**
     * Evidence for a scholarship: always offered, never required.
     *
     * @return list<self>
     */
    public static function supporting(): array
    {
        return [self::AchievementCertificate, self::TahfidzCertificate, self::Kip, self::HardshipLetter, self::OtherSupport];
    }
}
