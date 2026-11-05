# Product

<!-- impeccable:product-schema 1 -->

<!-- Disusun dari isi repo dan brief pengguna, tanpa sesi wawancara. Setiap fakta di bawah yang bukan dari brief ditandai (asumsi). -->

## Platform

web

## Users

Dua kelompok pengguna dengan perangkat yang berbeda.

- **Panitia penerimaan siswa baru (admin/operator)**: staf yayasan/lembaga yang memproses pendaftar sepanjang hari di laptop atau desktop. Pekerjaannya berulang: memeriksa data, memverifikasi bukti bayar, mengatur jadwal ujian, menilai, mengumumkan, menempatkan siswa. Mereka butuh kecepatan memproses banyak baris, bukan ekspresi.
- **Orang tua/wali**: hampir selalu memakai HP. Mereka mendaftarkan anak, melengkapi data, memilih sesi ujian, membayar, dan menunggu pengumuman. Bukan pengguna rutin; mereka datang untuk satu urusan lalu pergi. (Dari brief: "lebih sering pakai hp".)

## Product Purpose

Sistem Informasi Sekolah milik yayasan, dengan modul penerimaan siswa baru (PSB) yang sudah berjalan: pendaftaran oleh orang tua, penyaringan data oleh panitia, tagihan dan pembayaran, ujian dan nilai, pengumuman, lalu penempatan menjadi siswa. Satu domain untuk seluruh lembaga di yayasan; lembaga hanya kategori/filter, semua admin melihat semua lembaga.

## Positioning

Satu orang (NIK) dikenali lintas lembaga; keluarga, catatan, tagihan, dan status pendaftaran menempel pada orang yang sama sehingga panitia tidak mengetik ulang dan orang tua tidak mengisi dua kali.

## Operating Context

- Alur status pendaftar: Baru daftar → Menunggu pembayaran → Draf → Sedang diperiksa → Perlu diperbaiki / Data valid → Sudah diumumkan → Sudah ditempatkan.
- Pembayaran dikirim orang tua sebagai bukti transfer lalu diverifikasi atau ditolak admin; admin juga bisa mencatat pembayaran langsung.
- Pengumuman dikirim lewat WhatsApp ke kontak utama keluarga.
- Pendaftar dengan NIK yang bentrok ditandai "Cek NIK" dan harus diselesaikan sebelum diproses lanjut.
- Izin berbasis peran (`super-admin`, `admin`, `operator`, `user`); menu hanya menampilkan yang diizinkan.
- Teks UI Bahasa Indonesia semi-casual, tanpa jargon teknis.

## Capabilities and Constraints

- Stack: Laravel + Inertia v3 + React 19 + Tailwind 4 + shadcn/Radix + lucide-react. Pemasangan dependensi baru perlu persetujuan pengguna.
- Halaman admin harus lebar penuh (pengguna pakai desktop/laptop) tetapi tetap responsif.
- Sisi orang tua dipaksa tampilan HP (lebar sempit, bottom nav) di semua ukuran layar.
- Data dari server memakai pagination sisi server; filter lewat query string berbahasa Indonesia (`cari`, `status`, `lembaga`, `gelombang`).
- Belum diputuskan: apakah orang tua perlu mode gelap (mode gelap saat ini sudah ada di kode).

## Brand Commitments

- Dari brief: sistem desain mengikuti prototipe Mustaka (https://mustaka-prototype.sekeco.workers.dev/): hanya tampilan, bukan struktur aplikasinya. Nama produk di prototipe: "mustaka".

## Evidence on Hand

- Prototipe visual yang menjadi acuan (lihat di atas). Tidak ada data pelanggan, testimoni, atau angka kinerja; jangan mengarang.
- Seed demo ada di `database/seeders` (tiket 06) untuk melihat halaman dengan data nyata.

## Product Principles

- Panitia memproses ratusan pendaftar: setiap layar admin menjawab "apa yang perlu saya kerjakan sekarang" sebelum "apa yang tercatat".
- Aksi yang sering diulang harus bisa dilakukan dari daftar, tanpa membuka halaman detail.
- Orang tua tidak boleh melihat data anak yang belum terselesaikan bentrok NIK-nya.
- Orang tua selalu tahu langkah berikutnya dan siapa yang sedang menunggu siapa.
- Bahasa sehari-hari; istilah teknis (NIK, status) dijelaskan lewat konteks, bukan label.

## Accessibility & Inclusion

- Orang tua beragam usia dan literasi digital, memakai HP seadanya: target sentuh besar, teks minimal 15px, kontras jelas, tidak mengandalkan warna saja untuk status.
