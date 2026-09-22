# LAPORAN — Analisis Pemisahan Planning / Frontend / Backend

Project: verif-almutqon (Al Mutqon Verification System)
Tanggal: 2026-09-20
Status: **Dokumen analisis murni. Tidak ada perubahan kode.**

---

## 1. Kesimpulan Singkat

Project `verif-almutqon` sudah **terstruktur lebih bersih** daripada `almutqon`:
ada pemisahan nyata `public/` (web root), `app/` (logic), dan `storage/` (data
internal). Namun **lapisan tampilan (`app/Pages/`) masih mencampur view dengan
logika server** — tiap halaman melakukan query DB, validasi POST, dan CSRF
secara langsung, serta meng-`require Bootstrap.php` sendiri-sendiri.

Dokumen "planning" sudah terkumpul dengan baik: `README.md`, `AGENTS.md`,
`CATATAN.md`, `CHANGELOG_FIXES.md`.

Label "frontend murni" di project ini hanya `public/assets/`; `public/api/*`
adalah endpoint backend (walau hidup di bawah web root). Gap utamanya bukan
buat folder baru, melainkan **menyingkirkan logika server dari layer view**.

---

## 2. Arsitektur Saat Ini

| Aspek | Nilai |
|---|---|
| Bahasa | PHP native, HTML, CSS, JS vanilla, SQL |
| Framework | Tidak ada (larangan eksplisit di AGENTS.md) |
| Web root | `public/` — satu-satunya root (entry `public/*.php` = thin wrapper) |
| Bootstrap | Tunggal: `app/Bootstrap.php` (env, session, CSRF, helper) |
| Alur halaman | `public/<nama>.php` → `require app/Pages/<nama>.php` (10 page) |
| Alur API | `public/api/**` → `require app/Http/*` (logika) |
| Path include | `dirname(__DIR__)` relatif; konstanta `BASE_PATH`, `APP_URL` |
| Sesi | `$_SESSION['is_staff']`, `staff_id`, `staff_nama`, `staff_jabatan` |
| Upload | `storage/uploads/` (di luar web root — aman) |
| Lingkungan | Ada `.env` + `.env.example` (secret tidak di repo) |

---

## 3. Pemetaan Per Area

### Backend murni (app)

| Folder | Jml | Isi |
|---|---|---|
| `app/Bootstrap.php` | 1 | bootstrap: env, session_start (httponly/samesite), CSRF, helper, limit rate |
| `app/Config/` | 1 | `database.php` (koneksi PDO) |
| `app/Database/` | 1 | `schema.sql` |
| `app/Http/` | 5 | `api_v1_boot.php`, `api_v1_dokumen.php`, `api_v1_staf.php`, `dokumen_actions.php`, `download_document.php` |
| `app/Support/` | 6 | migrasi: avatar_theme, jenis_dokumen, password_resets, pdf_paths, tautan_staf, theme_system |

### Frontend murni

| Folder | Jml | Isi |
|---|---|---|
| `public/assets/` | 8 | `app.css`, `portal.css`, `nav.js`, `1.png`, favicon×2, fonts/material-symbols-rounded.woff2, vendor/qr-code-styling.js |

### Campuran (view + logika server)

| Folder | Jml | Isi |
|---|---|---|
| `public/*.php` root | 9 | thin entry: index, staff, dashboard, manage_staff, change_password, data_invoice, profil, rekap_nomor, download |
| `app/Pages/` (halaman) | 10 | index, staff, dashboard, manage_staff, change_password, data_invoice, profil, rekap_nomor, lupa_password, reset_password |
| `app/Pages/` (partial view) | 5 | `_doc_item`, `_masthead`, `_modal_shared`, `_sidebar`, `_upload_modal` |
| `public/api/` | 4 | `avatar.php`, `theme.php` (410), `v1/dokumen.php`, `v1/staf.php` — backend, tapi hidup di web root |

### Data & Dev

| Folder | Jml | Isi |
|---|---|---|
| `storage/` | 4 data + 2 .gitkeep | `uploads/` (3 PDF), `uploads_legacy_t/` (legacy.pdf) — di luar web root |
| `public/__sesi.php` | 1 | helper DEBUG: auto-login langsung ke sesi staff (tanpa password) |
| Docs | – | `README.md`, `AGENTS.md`, `CATATAN.md`, `CHANGELOG_FIXES.md` |
| Config | – | `.env` (secret), `.env.example` (template), `.gitignore` |

---

## 4. Titik Kopling Frontend ↔ Backend (fakta terukur)

1. **Semua 10 halaman `app/Pages/` mencampur query DB inline** — jumlah
   referensi `$pdo->` per file: `dashboard.php` (19), `manage_staff.php` (14),
   `staff.php` (4), `change_password.php` (3), `data_invoice.php` (3), sisanya
   1–2. Tidak ada lapisan service/query terpusat selain di `app/Http`.
2. **Halaman juga menangani POST + CSRF sendiri**, contoh
   `app/Pages/dashboard.php:19-46` (handling `upload_pdf`, `verify_csrf()`,
   panggil `dokumen_aksi_upload()`). Bahkan sebagian logika posting juga
   langsung di page (kasus "buat dokumen baru").
3. **Bootstrap dipanggil ulang oleh tiap page** — 10 file `require Bootstrap.php`
   (dan Bootstrap dipanggil lagi oleh file `app/Http`, walau `require_once`
   terhadap file lain menjadikannya idempoten di praktik).
4. **Backend hidup di dalam web root** — `public/api/*` bukan folder `app/`
   murni, sehingga kontrak JSON API (=`public/api/v1/*`) tercampur dengan aset
   publik; sementara `public/download.php` menyambut `download_document.php`
   (HTTP handler) lewat `public/`.
5. **`public/__sesi.php` membuka sesi tanpa autentikasi** — auto-login ke
   `staff_id=2` (Direktur). Ditandai sebagai kode `DEBUG`; jika ter-deploy ke
   live tanpa dihapus, ini menjadi lubang autentikasi terbuka.
6. **Partial view (`_*.php`) sudah murni tampilan** — 5 partial tidak memegang
   query DB; pemisahan view-partial sehat, tapi boundary-nya kontras dengan
   halaman utamanya yang masih campur.
7. File terbesar & paling campur: `app/Pages/dashboard.php` (446),
   `app/Pages/manage_staff.php` (444), `app/Http/api_v1_dokumen.php` (254),
   `app/Bootstrap.php` (232).

---

## 5. Opsi Pemisahan & Perbandingan

### Opsi A — Ringan: pisahkan dev tools & dokumentasi
`app/Support/` (migrasi) dan `public/__sesi.php` dipindah ke area dev;
dokumen planning tetap seperti sekarang (sudah rapi).

```
docs/          ← (opsional) navigasi README/AGENTS/CATATAN/CHANGELOG
dev/           ← app/Support migrasi, __sesi.php (hapus dari public!)
```

- Upaya kecil · risiko kecil · tidak mengubah URL.
- Nilai terbesar: **menghapus/inaktivasi `__sesi.php` dari `public/`**.

### Opsi B — Sedang: pindahkan logika server keluar dari view (rekomendasi inti)
View (`app/Pages/`) jadi hanya render; query/POST pindah ke `app/Http/`:

```
app/Pages/   → view + render saja (partial sudah bersih, ini jadi penyeimbang)
app/Http/    → semua handler aksi & query (dokumen_actions sudah contoh awal)
public/      → tetap entry thin + assets + api
```

- Tidak menambah framework. Tidak mengubah URL publik (`public/*.php` tetap).
- Upaya sedang: refactor ~10 halaman memakai helper aksi yang sudah ada
  (`dokumen_actions.php`), mengurangi duplikasi query inline.
- Risiko sedang — perlu uji ulang (login staff, CRUD dokumen, upload, invoice,
  rekap) setelah tiap halaman dimigrasi.

### Opsi C — Berat: frontend & backend project terpisah (API-only)
- Upaya sangat besar, menulis ulang seluruh layer, tidak sesuai struktur PHP
  native yang ada, dan menyalahi AGENTS.md ("jangan arsitektur besar tanpa
  alasan jelas"). Tidak direkomendasikan.

### Ringkasan perbandingan

| Opsi | Upaya | Risiko | URL | Framework baru | Sesuai AGENTS.md |
|---|---|---|---|---|---|
| A (ringan) | Kecil | Rendah | Tidak berubah | Tidak | Ya |
| B (sedang) | Sedang | Sedang | Tidak berubah | Tidak | Ya |
| C (berat) | Sangat besar | Tinggi | Berubah total | Ya | Tidak |

---

## 6. Rekomendasi

Untuk project ini yang sudah berlapis:

1. **Segera** hapus atau pindahkan `public/__sesi.php` (auto-login tanpa
   password) keluar dari web root sebelum deploy berikutnya.
2. **Langkah pertama** (Opsi A): kelompokkan `app/Support/` + migrasi sebagai
   area dev; dokumen planning sudah cukup rapi.
3. **Langkah inti** (Opsi B): jadikan `app/Pages/` view-only dengan memindah
   query & POST ke `app/Http/` — berpatokan pada `dokumen_actions.php` yang
   sudah ada. Lakukan per-halaman dengan uji ulang setelah setiap migrasi.
4. Hindari Opsi C tanpa keputusan eksplisit pemilik project.

---

## 7. Status Git & Catatan

- Repo: 2 commit (`8816218 Initial commit`, `5701c3b chore: update project progress`), branch `main`.
- **Belum di-commit** (batch saat ini):
  - Modified: 10 file `app/Pages/*.php`
    (`change_password.php`, `dashboard.php`, `data_invoice.php`, `index.php`,
    `lupa_password.php`, `manage_staff.php`, `profil.php`, `rekap_nomor.php`,
    `reset_password.php`, `staff.php`)
  - Untracked: `public/assets/favicon.ico`, `public/assets/favicon.png`
- File `LAPORAN.md` ini baru dibuat dan ikut untracked.

---

## Lampiran — Peta File Lengkap

### Backend murni
- `app/Bootstrap.php`
- `app/Config/database.php`
- `app/Database/schema.sql`
- `app/Http/`: `api_v1_boot.php` `api_v1_dokumen.php` `api_v1_staf.php`
  `dokumen_actions.php` `download_document.php`

### Dev / Migrasi
- `app/Support/`: `migrate_avatar_theme.php` `migrate_jenis_dokumen.php`
  `migrate_password_resets.php` `migrate_pdf_paths.php` `migrate_tautan_staf.php`
  `migrate_theme_system.php`
- `public/__sesi.php` (DEBUG — sebaiknya dipindah dari public)

### Frontend murni
- `public/assets/`: `app.css` `portal.css` `nav.js` `1.png` `favicon.ico`
  `favicon.png` `fonts/material-symbols-rounded.woff2`
  `vendor/qr-code-styling.js` `avatars/.gitkeep`

### Halaman — thin entry (public) + view/logika (app/Pages)
- Entry: `public/index.php` `public/staff.php` `public/dashboard.php`
  `public/manage_staff.php` `public/change_password.php` `public/data_invoice.php`
  `public/profil.php` `public/rekap_nomor.php` `public/download.php`
  (download → `app/Http/download_document.php`)
- View: `app/Pages/index.php` `staff.php` `dashboard.php` `manage_staff.php`
  `change_password.php` `data_invoice.php` `profil.php` `rekap_nomor.php`
  `lupa_password.php` `reset_password.php`
- Partial (murni view): `app/Pages/_doc_item.php` `_masthead.php`
  `_modal_shared.php` `_sidebar.php` `_upload_modal.php`

### API (backend, di web root)
- `public/api/avatar.php`
- `public/api/theme.php` (410 — fitur tema dihapus)
- `public/api/v1/dokumen.php` (→ `app/Http/api_v1_dokumen.php`)
- `public/api/v1/staf.php` (→ `app/Http/api_v1_staf.php`)

### Data internal
- `storage/uploads/` (3 PDF + .gitkeep)
- `storage/uploads_legacy_t/` (legacy.pdf + .gitkeep)

### Dokumen / Planning / Config
- `README.md` `AGENTS.md` `CATATAN.md` `CHANGELOG_FIXES.md` `LAPORAN.md`
- `.env` (secret, jangan di-commit) `.env.example` `.gitignore`

---

## Lampiran 2 — Detail Warna & Tema

Semua warna memakai CSS custom property di `public/assets/app.css`
(light default) dan di-override sebagian di `public/assets/portal.css`
(halaman `index.php`, portal publik). Tidak ada tema gelap di project ini
— hanya satu skema warna (light). Mekanisme tema light/dark/system sudah
dipasang di level database (`staff.theme`) oleh `app/Support/migrate_theme_system.php`
dan dihapus kembali setelah fitur diubah (lihat `public/api/theme.php`
mengembalikan HTTP 410, serta `migrate_avatar_theme.php` yang menghapus
kolom lama).

### Palet warna (light default)

| Variabel | Nilai | Kegunaan |
|---|---|---|
| `--forest` / `--primary` | `#166534` | Warna utama (hijau hutan) |
| `--forest-dark` / `--primary-dark` | `#14532D` | Hover/border gelap |
| `--forest-soft` / `--primary-light` | `#DCFCE7` | Latar lembut hijau |
| `--forest-rgb` | `22, 101, 52` | `rgba()` untuk bayangan |
| `--gold` / `--accent` | `#F59E0B` | Aksen kuning/amber |
| `--gold-soft` / `--accent-light` | `#FEF3C7` | Latar lembut aksen |
| `--gold-on-dark` | `#FBBF24` | Aksen pada latar gelap (mis. sidebar) |
| `--paper` / `--surface` | `#F8FAFC` | Latar halaman |
| `--card` | `#FFFFFF` | Latar kartu |
| `--line` / `--border` | `#E2E8F0` | Garis pembatas ringan |
| `--line-strong` | `#CBD5E1` | Garis pembatas kuat |
| `--ink` / `--text-dark` | `#0F172A` | Teks utama |
| `--ink-soft` / `--text-body` | `#475569` | Teks paragraf |
| `--ink-faint` / `--muted` | `#94A3B8` | Teks samar |
| `--footer-bg` | `#0B1120` | Latar footer/sidebar gelap |

### Warna status & utility

| Variabel | Nilai |
|---|---|
| `--ok` | `#166534` · `--ok-soft` `#DCFCE7` |
| `--err` | `#B3261E` · `--err-soft` `#FEE2E2` |
| `--warn` | `#B45309` · `--warn-soft` `#FEF3C7` |
| `--blue-ink` | `#1D4ED8` · `--blue-soft` `#DBEAFE` |
| `--danger` (area form) | hover `rgba(220,38,38,.16)` |

### Tipografi & geometry

| Variabel | Font / nilai |
|---|---|
| `--font-ui` | `Inter`, system-ui |
| `--font-display` | `Plus Jakarta Sans`, `Inter` |
| `--font-mono` | `ui-monospace`, `JetBrains Mono`, `Menlo`, `Consolas` |
| `--radius` | `12px` |
| `--ani` | `.2s cubic-bezier(.4,0,.2,1)` |
| `--shadow-sm` / `--shadow-md` | 2 tingkatan (dipakai portal & kartu) |

### Tampilan portal (`portal.css`)

- Dipakai **hanya di `index.php`** (halaman verifikasi publik).
- Adaptasi visual ala referensi: latar putih, aksen navy (`--pv-navy`
  = `var(--forest)`), garis `#E2E8F0`, bayangan lembut. Variabel
  `--pv-*` merujuk ke `app.css` (tanpa duplikasi nilai).
- Elemen khas: `--pv-scanline` (`rgba(22,101,52,.25)`) untuk efek pindai
  pada panel QR, `--pv-focus` (ring highlight hijau 14%), `--pv-shadow`
  2 tingkatan.

### Mekanisme tema & database

- Kolom `staff.theme` `ENUM('light','dark','system')` ada di schema;
  `migrate_avatar_theme.php` + `migrate_theme_system.php` menambah
  opsinya.
- `public/api/theme.php` (endpoint simpan tema) kini mengembalikan
  **HTTP 410 Gone** — fitur ganti tema dihapus (lihat `CHANGELOG_FIXES.md`).
- Endpoint avatar masih aktif: `public/api/avatar.php` (upload/hapus
  via multipart + CSRF, whitelist `kembali`).
- Halaman masih memakai `app/Pages/_masthead.php` (topbar ala
  dashboard almutqon) dan `app/Pages/_modal_shared.php` sebagai partial
  view murni.

---

## Lampiran 3 — Fitur Utama Project

Fitur yang terimplementasi di `verif.almutqon` namun belum disebut di bagian
analisis arsitektur di atas.

### Verifikasi dokumen publik

- **Halaman `index.php`** (portal publik): validasi `?token=...`
  (64 hex) atau `?code=...` (6-digit) ke `dokumen`; menampilkan detail
  (nomor surat, jenis, perihal, penerima, tanggal, penandatangan,
  jabatan, status). QR + status visual: **valid** (green `--ok`),
  **dicabut** (red `--err`), **ditolak/rejected** (amber `--warn`).
- **QR dinamis**: `vendor/qr-code-styling.js` (lib frontend)
  merender QR dari `verify_url` di panel kanan portal; scanline animasi
  untuk efek "pindai".

### Pengisian & manajemen dokumen (area staff)

- **Buat dokumen baru** (tanpa PDF, `app/Pages/dashboard.php`) —
  form: `no_surat`, `jenis` (`dokumen`/`invoice`), `perihal`,
  `penerima`, `tanggal_dokumen`, `jabatan_ttd`, `penandatangan`.
- **Upload PDF** (aksi `upload_pdf` via `dokumen_aksi_upload()` di
  `app/Http/dokumen_actions.php`) — validasi MIME `application/pdf`,
  ≤ `MAX_UPLOAD_BYTES` (10 MB), nama acak `cert_*.pdf`, simpan ke
  `storage/uploads/`.
- **Unduh** (`public/download.php` → `app/Http/download_document.php`)
  akses file PDF di `storage/uploads/`, dengan header
  `Content-Disposition` `attachment`.
- **Aktivitas/audit log** — `activity_log` tercatat oleh service-account
  saat dokumen dibuat/diubah/diunduh (kolom `staff_id`, `action`,
  `dokumen_id`, `description`, `ip_address`).

### Manajemen personel (`manage_staff.php`)

- CRUD baris `staff`: tambah, ubah, aktif/nonaktif, reset password.
- Proteksi khusus: `NIP=SYSTEM-*`, `role=direktur` (hanya lewat
  dashboard), dan direktur-terakhir aktif tak bisa dinonaktifkan
  (`api_v1_staf.php` dan halaman lokal).
- **Rekap nomor** (`rekap_nomor.php`) — hitung `nomor_urut` terakhir
  per jenis, untuk penomoran otomatis.
- **Data invoice** (`data_invoice.php`) — daftar dokumen `jenis=invoice`
  (backfill `INV-%` dari `migrate_jenis_dokumen.php`).

### Auth & akun

- Login staff: `staff.php` (password), `change_password.php`,
  `lupa_password.php` + `reset_password.php` (token via `password_resets`,
  migrasi `migrate_password_resets.php`).
- Kolom `staff.theme` (light/dark/system) sudah ada meski fitur
  theme-switch UI dihapus.

### Integritas & proteksi

- **CSRF** (`config` dari `Bootstrap.php`, `verify_csrf()`) +
  `require staff session` di tiap halaman staff area.
- **Rate limiting** per IP: `MAX_UPLOAD_ATTEMPTS` (15 dalam 600 detik),
  `MAX_API_ATTEMPTS` (60 dalam 60 detik) — dipakai `app/Http/api_v1_boot.php`.
- **Service account** `staff` (NIP `SYSTEM-ALMUTQON`) wajib ada &
  aktif; jika tidak, `api_v1_*` balas `500` (mencegah write ke DB
  tanpa akun pencatat).
- **Idempoten migrasi**: semua `app/Support/migrate_*.php` cek
  `information_schema` sebelum `ALTER` (DRY-RUN + eksekusi).

### API internal v1 (integrasi dengan almutqon)

- **Dokumen** (`public/api/v1/dokumen.php` → `app/Http/api_v1_dokumen.php`):
  aksi `create` (idempoten by `no_surat`; PDF opsional via multipart),
  `revoke`, `restore`; balasan `{success, data{token, code_6digit,
  verify_url}}`. Envelope + Bearer token + POST only.
- **Staf** (`public/api/v1/staf.php` → `app/Http/api_v1_staf.php`):
  aksi `upsert` (NIP = `id_pengajar` `PGJ-XXXX`), `toggle`
  (aktif/nonaktif + `hapus_tautan`); proteksi direktur & service
  account.
- **Fondasi** (`app/Http/api_v1_boot.php`): envelope JSON, auth
  Bearer (`VERIF_API_TOKEN`), input JSON+POST digabung, IP klien,
  service account, rate limit, 401/405/429.
- **Konfigurasi** di `.env` (dijaga rahasia): `DB_HOST/PORT/DATABASE/
  USERNAME/PASSWORD`, `APP_URL` (basis `verify_url`), `VERIF_API_TOKEN`,
  `VERIF_API_STAFF_NIP`.

### Upload & storage

- `storage/uploads/` — PDF aktif (nama acak `cert_*.pdf`).
- `storage/uploads_legacy_t/` — arsip legacy (1 PDF).
- `.gitkeep` menjaga struktur; file upload **tidak** masuk git
  (AGENTS.md).

### Keamanan input & UI

- `app/Pages/` memakai partial view murni (`_doc_item`, `_masthead`,
  `_modal_shared`, `_sidebar`, `_upload_modal`) — markup saja, tanpa
  query DB.
- `public/assets/nav.js` (40 baris) — interaksi navigasi/menú.
- `app.css` + `portal.css` memuat seluruh style, tanpa dependency
  framework.
- Input pengguna (token/kode/URL) selalu tervalidasi ke DB; halaman
  verifikasi tidak membocorkan data internal.
