# Refactor host-ready & maintainable

Status: BERJALAN · Service: verif.almutqon (web app) · Diperbarui: 2026-09-30

## Tujuan
Refactor agar rapi + siap deploy ke shared hosting Indonesia. **Tidak mengubah URL publik, tampilan, maupun perilaku.**

## Keputusan penting (sudah dikunci dengan owner)
| Topik | Keputusan |
|---|---|
| CDN (unpkg/jsdelivr/Google Fonts) | Self-host semua |
| Document root | Pertahankan fallback `index.php` + `.htaccess` root, diperkuat + didokumentasikan |
| Migrasi DB | phpMyAdmin import `schema.sql` + halaman diagnosa web. **Tanpa wizard installer** |
| PHP | Pertahankan 8.1+, tambah guard yang ramah (bukan fatal error) |
| Jenis dokumen | Centralisasi validasi saja. **ENUM tidak diubah** |
| Pembagian kerja | opencode = Fase 0,1,2,3,5,6 (sensitif). `pi` = Fase 4 (mekanis) |

## Temasan awal (read-only, sudah diverifikasi)
1. Tidak ada `set_exception_handler` → stack trace bisa bocor di production.
2. Tidak ada `date_default_timezone_set` → server UTC, UI cetak literal "WIB" (`app/Pages/index.php:199`).
3. Deteksi HTTPS hanya `$_SERVER['HTTPS']` (`app/Bootstrap.php:66`) → gagal di belakang Cloudflare.
4. 39 cache-buster `?v=` tidak sinkron (`app.css`: f/g/h/k, `nav.js`: a/b) → sebagian halaman pakai CSS lama.
5. `<head>` diduplikasi 10×, weight Google Fonts berbeda-beda per halaman.
6. `.env` di-parse `parse_ini_file` → rapuh saat nilai mengandung `#` (pernah kena, CATATAN item 40).
7. `app/Bootstrap.php` 312 baris, 9 tanggung jawab.
8. Avatar ditulis di dalam web root (`public/assets/avatars/`) — satu-satunya direktori writable di `public/`.

## Milestone
- [x] **Fase 0** — baseline `php -l` bersih, `handoffs/` dibuat, brief `docs/REFACTOR_HOSTING_BRIEF.md` ditulis.

## Status terakhir
Commit `d16fee5` = titik balik. Working tree bersih saat Fase 0 mulai.

## Langkah berikutnya
1. **Fase 1** — `app/Config/env.php` (parser `.env` sendiri), `app/Config/app.php` (konstanta + `ASSET_VERSION`), `app/Config/paths.php`. Commit.
2. **Fase 2** — pecah `app/Bootstrap.php` jadi orkestrator tipis + `app/Support/{security,rate_limit,documents,auth,mail,format}.php`. **Nama fungsi publik wajib tidak berubah.** Commit.
3. **Fase 3** — guard PHP 8.1 di entry, `set_exception_handler`/`set_error_handler`, `date_default_timezone_set`, deteksi proxy tepercaya, `error_log` ke `storage/logs/`, `.htaccess` root + per-direktori, `public/diagnosa.php`. Commit.
4. **Fase 4** — jalankan `pi` (model default) dengan `@docs/REFACTOR_HOSTING_BRIEF.md`. Review `git diff` sendiri. Verifikasi: `php -l`, `grep cdn|unpkg|googleapis` kosong, `grep v=2026` kosong, cek font + `Html5QrcodeScanner`, screenshot 390/1440. Commit.
5. **Fase 5** — `app/Support/jenis_dokumen.php`, sentralisasi validasi. Commit.
6. **Fase 6** — tulis ulang `docs/DEPLOYMENT.md` untuk shared hosting ID, update `.env.example`, bersihkan folder mati. Commit.

## Jangan lakukan
- Jangan ubah URL, query string, atau markup yang terlihat. QR lama yang sudah tercetak harus tetap hidup.
- Jangan ubah nilai default konstanta (rate limit, MAX_UPLOAD_BYTES) — hanya jadikan bisa di-override env.
- Jangan ubah `dokumen.jenis` ENUM jadi varchar/tabel.
- Jangan `git push` tanpa diminta.
- Jangan peek/print isi `.env`, `auth.json`, atau kredensial apa pun.
- Jangan jalankan perintah destruktif (`rm -rf`, `git reset --hard`, `git clean`) tanpa izin eksplisit di pesan yang sama.
- Jangan percaya `pi` tanpa review: ia menjalankan model flash, ddatabase verify sebelum commit.
