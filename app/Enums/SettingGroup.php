<?php

namespace App\Enums;

use App\Contracts\HasLabel;
use App\Support\StaffTwoFactor;

/**
 * The tabs of the white-label settings page. Each group owns a set of setting keys, so a tab saves on its own.
 */
enum SettingGroup: string implements HasLabel
{
    case Identity = 'identitas';
    case Appearance = 'tampilan';
    case Contact = 'kontak';
    case Payment = 'pembayaran';
    case Public = 'halaman-depan';
    case Security = 'keamanan';

    public function label(): string
    {
        return match ($this) {
            self::Identity => 'Identitas',
            self::Appearance => 'Tampilan',
            self::Contact => 'Kontak',
            self::Payment => 'Rekening',
            self::Public => 'Halaman depan',
            self::Security => 'Keamanan',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Identity => 'Nama, logo, dan ikon yang muncul di seluruh aplikasi, judul tab, dan email.',
            self::Appearance => 'Warna utama untuk tombol, penanda aktif, dan ikon.',
            self::Contact => 'Cara orang tua menghubungi sekolah. Tampil di halaman bantuan dan halaman depan.',
            self::Payment => 'Rekening tujuan transfer yang ditampilkan ke orang tua saat membayar.',
            self::Public => 'Teks sambutan di halaman depan dan baris kaki yang dilihat orang tua.',
            self::Security => 'Aturan keamanan untuk akun staf.',
        };
    }

    /**
     * Setting key => validation rules for the text fields of this group. Uploaded files are handled apart.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return match ($this) {
            self::Identity => [
                'brand.name' => ['required', 'string', 'max:60'],
                'foundation.name' => ['nullable', 'string', 'max:120'],
                'brand.tagline' => ['nullable', 'string', 'max:160'],
                'brand.show_name' => ['boolean'],
            ],
            self::Appearance => [
                'brand.accent_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            ],
            self::Contact => [
                'contact.phone' => ['nullable', 'string', 'max:30'],
                'contact.whatsapp' => ['nullable', 'string', 'max:30', 'regex:/^\+?\d{8,20}$/'],
                'contact.email' => ['nullable', 'email', 'max:120'],
                'contact.address' => ['nullable', 'string', 'max:300'],
                'contact.website' => ['nullable', 'url:http,https', 'max:200'],
            ],
            self::Payment => [
                'payment.bank_name' => ['nullable', 'string', 'max:60'],
                'payment.account_number' => ['nullable', 'string', 'max:40'],
                'payment.account_holder' => ['nullable', 'string', 'max:100'],
                'payment.instructions' => ['nullable', 'string', 'max:500'],
            ],
            self::Public => [
                'brand.welcome_title' => ['nullable', 'string', 'max:100'],
                'brand.welcome_description' => ['nullable', 'string', 'max:300'],
                'brand.footer_text' => ['nullable', 'string', 'max:200'],
            ],
            self::Security => [
                StaffTwoFactor::SETTING_KEY => ['boolean'],
            ],
        };
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'brand.name' => 'Nama aplikasi',
            'foundation.name' => 'Nama yayasan',
            'brand.tagline' => 'Slogan',
            'brand.show_name' => 'Tampilkan nama di samping logo',
            'brand.accent_color' => 'Warna utama',
            'contact.phone' => 'Telepon',
            'contact.whatsapp' => 'WhatsApp',
            'contact.email' => 'Email',
            'contact.address' => 'Alamat',
            'contact.website' => 'Situs web',
            'payment.bank_name' => 'Nama bank',
            'payment.account_number' => 'Nomor rekening',
            'payment.account_holder' => 'Atas nama',
            'payment.instructions' => 'Petunjuk transfer',
            'brand.welcome_title' => 'Judul sambutan',
            'brand.welcome_description' => 'Teks sambutan',
            'brand.footer_text' => 'Teks kaki halaman',
            StaffTwoFactor::SETTING_KEY => 'Wajib autentikasi dua langkah untuk staf',
            'logo' => 'Logo',
            'favicon' => 'Ikon tab',
        ];
    }
}
