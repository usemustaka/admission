# Mustaka Admission — Sistem Informasi Sekolah

Sistem informasi sekolah open-source untuk yayasan, dengan modul **Penerimaan Siswa Baru (PSB)** yang sudah berjalan: pendaftaran oleh orang tua, penyaringan data oleh panitia, tagihan dan pembayaran, ujian dan nilai, pengumuman, hingga penempatan menjadi siswa.

Satu instalasi melayani seluruh lembaga di yayasan. Lembaga hanya berfungsi sebagai kategori/filter, dan satu orang (NIK) dikenali lintas lembaga, jadi keluarga, catatan, tagihan, dan status pendaftaran menempel pada orang yang sama. Panitia tidak perlu mengetik ulang, orang tua tidak perlu mengisi dua kali.

## Fitur

- **Portal orang tua** (tampilan HP): daftarkan anak, lengkapi data, pilih sesi ujian, unggah bukti bayar, lihat pengumuman.
- **Panel admin** (tampilan desktop): periksa data, verifikasi atau tolak pembayaran, atur jadwal dan sesi ujian, input nilai, umumkan, tempatkan siswa.
- **Alur status pendaftar**: Baru daftar → Menunggu pembayaran → Draf → Sedang diperiksa → Perlu diperbaiki / Data valid → Sudah diumumkan → Sudah ditempatkan.
- **Deteksi NIK bentrok**: pendaftar ditandai "Cek NIK" dan harus diselesaikan sebelum diproses lanjut.
- **Keuangan**: tagihan, pembayaran, alokasi, rekonsiliasi, laporan tunggakan, dan koreksi.
- **Pengumuman via WhatsApp** ke kontak utama keluarga.
- **Tahun ajaran, lembaga, gelombang, dan periode** yang dapat diatur.
- **Akun staf dengan peran dan izin** (`super-admin`, `admin`, `operator`, `user`), menu menyesuaikan izin.
- **Keamanan**: autentikasi dua langkah (2FA) wajib untuk staf (dapat diatur), manajemen sesi.
- **White label**: nama dan tampilan dapat disesuaikan lewat pengaturan.

## Teknologi

- PHP 8.3+ dan [Laravel](https://laravel.com) 13
- [Inertia.js](https://inertiajs.com) v3 + React 19 + TypeScript
- Tailwind CSS 4, shadcn/Radix UI, lucide-react
- Laravel Fortify (autentikasi), spatie/laravel-permission (peran dan izin), Laravel Wayfinder (rute bertipe)
- Pest (pengujian), Larastan/PHPStan, Laravel Pint
- SQLite secara bawaan; DB lain yang didukung Laravel bisa dipakai

## Kebutuhan

- PHP 8.3 atau lebih baru dengan ekstensi umum Laravel (mbstring, pdo_sqlite, dll.)
- [Composer](https://getcomposer.org)
- [Bun](https://bun.sh) (atau Node.js) untuk build frontend

## Instalasi

```bash
git clone https://github.com/usemustaka/admission mustaka-admission
cd mustaka-admission
composer setup
```

`composer setup` akan memasang dependensi, menyalin `.env`, membuat kunci aplikasi, menjalankan migrasi, dan membangun aset frontend.

Pastikan file SQLite ada bila memakai konfigurasi bawaan:

```bash
touch database/database.sqlite
php artisan migrate
```

### Buat akun admin pertama

```bash
php artisan app:create-admin admin@contoh.id "Nama Admin"
```

Opsi: `--role=super-admin|admin|operator` dan `--password=...` (untuk deploy tanpa prompt).

### Data demo (opsional)

```bash
php artisan db:seed --class=Database\\Seeders\\SystemSeeder
php artisan db:seed --class=Database\\Seeders\\DemoSeeder
```

Akun demo: `admin@demo.test` dengan password `password`. **Jangan gunakan data demo di produksi.**

## Menjalankan

```bash
composer dev
```

Aplikasi tersedia di <http://localhost:8000>.

## Konfigurasi

Salin `.env.example` menjadi `.env`, lalu sesuaikan. Beberapa variabel penting:

| Variabel                      | Keterangan                                                     |
| ----------------------------- | -------------------------------------------------------------- |
| `APP_NAME`                    | Nama aplikasi/sekolah                                          |
| `APP_URL`                     | URL aplikasi                                                   |
| `APP_LOCALE` / `APP_TIMEZONE` | Bawaan `id` dan `Asia/Jakarta`                                 |
| `DB_CONNECTION`               | Bawaan `sqlite`                                                |
| `REQUIRE_TWO_FACTOR_STAFF`    | Wajibkan 2FA untuk staf (bisa diubah di Pengaturan > Keamanan) |

## Pengembangan

```bash
composer lint        # format PHP dengan Pint
composer types:check # analisis statis PHPStan
bun run check        # lint/format frontend
bun run types:check  # TypeScript
composer test        # lint + analisis statis + test Pest
composer ci:check    # semua pemeriksaan sekaligus
```

## Struktur Singkat

- `app/Actions` — logika bisnis per aksi (Admission, Portal, Users, dll.)
- `app/Http/Controllers/Admin` — panel admin; `Portal` — sisi orang tua
- `app/Support` — presenter, filter, dan helper domain
- `resources/js/pages` — halaman Inertia (React)
- `database/seeders` — seeder sistem, peran/izin, dan demo
- `PRODUCT.md`, `DESIGN.md` — konteks produk dan sistem desain

## Berkontribusi

Kontribusi sangat disambut. Silakan buka issue untuk diskusi, lalu kirim pull request. Sebelum mengirim, jalankan `composer ci:check` dan sertakan test untuk perubahan perilaku. Teks antarmuka memakai Bahasa Indonesia semi-casual tanpa jargon teknis.

## Lisensi

Dirilis di bawah [lisensi MIT](https://opensource.org/licenses/MIT).
