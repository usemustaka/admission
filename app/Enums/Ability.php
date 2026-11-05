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
    case RoleManage = 'core::role.manage';
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
    case RegistrantExport = 'admission::registrant.export';
    case RegistrantBulk = 'admission::registrant.bulk';
    case RegistrantPlace = 'admission::registrant.place';
    case RegistrantBypass = 'admission::registrant.bypass';
    case AnnouncementManage = 'admission::announcement.manage';
    case PaymentView = 'admission::payment.view';
    case PaymentRecord = 'admission::payment.record';
    case PaymentVerify = 'admission::payment.verify';
    case PaymentExport = 'admission::payment.export';

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
            self::RoleManage => 'Mengelola peran dan izinnya',
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
            self::RegistrantExport => 'Mengekspor data pendaftar (CSV)',
            self::RegistrantBulk => 'Menjalankan aksi massal pada pendaftar',
            self::RegistrantPlace => 'Menempatkan pendaftar menjadi siswa',
            self::RegistrantBypass => 'Melompati alur status pendaftar dengan catatan',
            self::AnnouncementManage => 'Mengubah templat pengumuman',
            self::PaymentView => 'Melihat pembayaran',
            self::PaymentRecord => 'Mencatat pembayaran langsung (tunai atau transfer yang diterima staf)',
            self::PaymentVerify => 'Memverifikasi dan menolak pembayaran yang dikirim orang tua',
            self::PaymentExport => 'Mengekspor data pembayaran (CSV)',
        };
    }
}
