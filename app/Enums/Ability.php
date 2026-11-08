<?php

namespace App\Enums;

/**
 * Permission names, written as `module::entity.action`.
 */
enum Ability: string
{
    case AdminAccess = 'core::admin.access';
    case SettingManage = 'core::setting.manage';
    case UserManage = 'core::user.manage';
    case ParentPasswordReset = 'core::parent.password-reset';
    case RoleManage = 'core::role.manage';
    case YearManage = 'core::year.manage';
    case OrganizationManage = 'core::organization.manage';
    case PersonView = 'core::person.view';
    case PersonCreate = 'core::person.create';
    case PersonUpdate = 'core::person.update';
    case FamilyManage = 'core::family.manage';
    case NoteView = 'core::note.view';
    case NoteCreate = 'core::note.create';
    case NoteManage = 'core::note.manage';
    case NoteDelete = 'core::note.delete';
    case WaveView = 'admission::wave.view';
    case WaveManage = 'admission::wave.manage';
    case RulesetView = 'admission::ruleset.view';
    case RulesetManage = 'admission::ruleset.manage';
    case RegistrantView = 'admission::registrant.view';
    case RegistrantManage = 'admission::registrant.manage';
    case ExamManage = 'admission::exam.manage';
    case ExamScore = 'admission::exam.score';
    case RegistrantExport = 'admission::registrant.export';
    case RegistrantBulk = 'admission::registrant.bulk';
    case RegistrantAnnounce = 'admission::registrant.announce';
    case RegistrantPlace = 'admission::registrant.place';
    case RegistrantBypass = 'admission::registrant.bypass';
    case AnnouncementManage = 'admission::announcement.manage';
    case ScholarshipGrant = 'admission::scholarship.grant';
    case PaymentView = 'admission::payment.view';
    case PaymentRecord = 'admission::payment.record';
    case PaymentVerify = 'admission::payment.verify';
    case PaymentExport = 'admission::payment.export';
    case PaymentVoid = 'admission::payment.void';
    case StudentView = 'admission::student.view';
    case StudentManage = 'admission::student.manage';

    /**
     * The module prefix, such as `core` or `admission`, used to group abilities on screen.
     */
    public function module(): string
    {
        return explode('::', $this->value)[0];
    }

    public function label(): string
    {
        return match ($this) {
            self::AdminAccess => 'Masuk ke panel admin',
            self::SettingManage => 'Mengatur identitas, tampilan, dan kontak aplikasi',
            self::UserManage => 'Mengelola akun staf: undang, ubah peran, nonaktifkan',
            self::ParentPasswordReset => 'Mengirim tautan atur ulang password ke akun orang tua pendaftar',
            self::RoleManage => 'Mengelola peran dan izinnya',
            self::YearManage => 'Mengelola tahun ajaran dan periode',
            self::OrganizationManage => 'Mengelola lembaga',
            self::PersonView => 'Melihat data orang',
            self::PersonCreate => 'Menambah orang',
            self::PersonUpdate => 'Mengubah data orang',
            self::FamilyManage => 'Mengatur keluarga dan kontak utama',
            self::NoteView => 'Melihat catatan publik',
            self::NoteCreate => 'Menulis catatan',
            self::NoteManage => 'Mengubah dan menghapus catatan orang lain',
            self::NoteDelete => 'Menghapus catatan orang lain',
            self::WaveView => 'Melihat gelombang pendaftaran',
            self::WaveManage => 'Mengatur gelombang pendaftaran',
            self::RulesetView => 'Melihat aturan pendaftaran',
            self::RulesetManage => 'Mengatur aturan pendaftaran',
            self::RegistrantView => 'Melihat pendaftar',
            self::RegistrantManage => 'Memeriksa, mengubah, mengumumkan, dan menempatkan pendaftar',
            self::ExamManage => 'Mengatur jadwal ujian: tanggal, sesi, dan kuota',
            self::ExamScore => 'Memasukkan nilai ujian pendaftar',
            self::RegistrantExport => 'Mengekspor data pendaftar (CSV)',
            self::RegistrantBulk => 'Menjalankan aksi massal pada pendaftar',
            self::RegistrantAnnounce => 'Mengumumkan hasil pendaftar dan mencetak surat penerimaan',
            self::RegistrantPlace => 'Menempatkan pendaftar menjadi siswa',
            self::RegistrantBypass => 'Melompati alur status pendaftar dengan catatan',
            self::AnnouncementManage => 'Mengubah templat pengumuman',
            self::ScholarshipGrant => 'Memberikan beasiswa dan melihat anak yang berpotensi mendapatkannya',
            self::PaymentView => 'Melihat pembayaran',
            self::PaymentRecord => 'Mencatat pembayaran langsung (tunai atau transfer yang diterima staf)',
            self::PaymentVerify => 'Memverifikasi dan menolak pembayaran yang dikirim orang tua',
            self::PaymentExport => 'Mengekspor data pembayaran (CSV)',
            self::PaymentVoid => 'Membatalkan atau membuka ulang pembayaran yang sudah diproses',
            self::StudentView => 'Melihat daftar siswa',
            self::StudentManage => 'Mengubah nomor induk dan tahun siswa, atau membatalkan penempatan',
        };
    }
}
