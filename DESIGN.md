# Design System

Mengikuti prototipe Mustaka (https://mustaka-prototype.sekeco.workers.dev/, sumber di `../mustaka/prototype/src/ui.tsx` dan `layouts.tsx`). Hanya bahasa visualnya yang diambil, bukan struktur aplikasinya. Modenya **Operate**: keterbacaan, konsistensi, kecepatan kerja lebih penting daripada ekspresi.

## Ringkasan

- Putih bersih, teks ink `#222`, teks sekunder mute `#6a6a6a`, garis `#ebebeb`. Satu aksen: **lime-400** (`#a3e635`) untuk aksi utama, penanda aktif, ikon chip.
- Font **Figtree** (400–700, judul ekstra tebal untuk logo). Angka tabel `tabular-nums`.
- Sudut besar: kartu `rounded-2xl`, kolom isian dan tombol `rounded-xl`, dialog/sheet `rounded-3xl`, filter dan pencarian `rounded-full`.
- Elevasi dinyatakan sekali: kartu = **garis 1px tanpa bayangan**. Bayangan (`shadow-card`, `shadow-float`, `shadow-pop`) hanya untuk yang melayang: menu, dialog, toast, bulk bar.
- Fokus keyboard: outline 2px warna `--ring` (ink) offset 2px; kolom isian memakai ring 2px ink.
- Tidak ada gradien teks, kicker di atas judul, border-kiri berwarna, atau kartu bersarang.

## Token (resources/css/app.css)

Token shadcn (`background`, `foreground`, `primary`, `muted`, `border`, `input`, `ring`, ...) dipertahankan; nilainya yang diganti. Tambahan: `ink`, `mute`, `line`, `wash` (utility `text-mute`, `border-line`, `bg-wash`). `primary` = lime-400 dengan teks `neutral-900`. Mode gelap tetap ada dan netral.

Warna status (selalu pasangan latar lembut + teks gelap, tidak pernah warna saja):

| Nada       | Kelas                             |
| ---------- | --------------------------------- |
| sukses     | `bg-emerald-50 text-emerald-700`  |
| peringatan | `bg-amber-50 text-amber-700`      |
| info       | `bg-sky-50 text-sky-700`          |
| bahaya     | `bg-red-50 text-red-700`          |
| netral     | `bg-neutral-100 text-neutral-700` |
| merek      | `bg-lime-100 text-lime-800`       |

Pakai `<Badge variant="success|warning|info|danger|muted|default">` dan `<StatusBadge status="...">`, jangan menulis kelas warna sendiri.

## Komponen

- `ui/button`: `default` (lime), `dark` (hitam), `outline`, `secondary`, `ghost`, `destructive`, `link`; ukuran `sm` (h-8), `default` (h-10), `lg` (h-12), `icon`, `icon-sm`. Satu tombol `default`/`dark` utama per area.
- `ui/input`, `textarea`, `select`: tinggi 48px, `rounded-xl`, border netral-300, hover netral-500, fokus ring ink. Label di atas lewat `FormField`.
- `ui/card`: garis tipis, `rounded-2xl`. `SectionCard` untuk blok berjudul. `StatCard` untuk satu angka (bisa `href` agar menuju daftar terfilter).
- `PageHeader`: judul 26/32px semibold, subjudul mute, aksi di kanan.
- `FilterPills` (pil filter bernilai satu, aktif = hitam solid + hitungan), `ValueTabs` / `LinkTabs` (tab bergaris bawah).
- `DataTable`: kartu bertepi, header `uppercase text-xs font-bold`, opsi `selection` (checkbox + bulk), `onRowOpen` (klik baris membuka quick view), `activeRowId`.
- `QuickView` (panel samping, naik dari bawah di HP), `BulkBar` (bar melayang saat ada pilihan), `Pager`, `EmptyState` (lingkaran lime-100), `Alert` (nada callout: warning/destructive/success/info).
- `PersonAvatar`: gradien dari nama (konsisten per orang), teks putih tebal.

## Tata letak

- **Admin**: kontainer `max-w-7xl` di tengah (`AppAdminLayout`). Header sticky: logo, menu utama berikon (md ke atas), pencarian cepat (Ctrl/Cmd+K), menu akun. Bagian dengan beberapa halaman menampilkan **tab bergaris bawah** di bawah header. Di bawah md, navigasi pindah ke **bottom tab bar** (aktif = pil lime-300 di belakang ikon). Halaman detail memakai tautan kembali "‹ Induk" otomatis dari breadcrumbs (jika ≥3 level).
- Konten halaman: `grid gap-6`, tanpa `max-w-*` kecuali halaman formulir (`mx-auto max-w-3xl`). Gutter `px-4 sm:px-6 lg:px-8` dipegang layout, jadi halaman **tidak** menambah `p-4 md:p-6`. Kolom ganda untuk detail: `@5xl:grid-cols-[minmax(0,1fr)_24rem]`.
- **Portal orang tua**: selalu satu kolom selebar HP (`max-w-[480px]`, di tengah, latar luar netral-100), header 56px, **bottom tab bar** selalu tampil (Beranda, Tagihan, Daftar, Akun). Target sentuh ≥44px, teks ≥15px.
- **Responsif berbasis kontainer**: pembungkus konten admin dan portal adalah `@container`. Komponen yang dipakai di dua sisi **wajib** memakai varian kontainer (`@2xl:`, `@3xl:`, `@5xl:`), bukan `sm:`/`md:`/`lg:`; kalau tidak, portal akan menampilkan tata letak desktop di dalam kolom HP pada layar lebar. Padanan: `sm`→`@2xl`, `md`→`@3xl`, `lg`→`@5xl`, `xl`→`@6xl`. File khusus admin boleh memakai breakpoint viewport; file khusus portal tidak memakai breakpoint sama sekali.

## Gerak

Satu bahasa pendek: `anim-pop` (16ms ease-out, muncul dari bawah 8px), `anim-fade`, `anim-up` (toast/bulk bar), `anim-slide` (panel samping). Hormati `prefers-reduced-motion` (sudah global).

## Salinan

Bahasa Indonesia semi-casual. Tombol menyebut aksinya ("Terima data", bukan "Submit"). Galat menyebut masalah dan jalan keluarnya.
