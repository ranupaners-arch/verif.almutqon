# Panduan deployment

## Topologi yang disarankan

Arahkan document root domain ke folder `public/`. Dengan konfigurasi ini, source aplikasi, storage, schema database, dokumentasi, dan `.env` berada di luar area publik.

`index.php` dan `.htaccess` di root hanya fallback untuk shared hosting yang tidak dapat mengubah document root. Jangan memakai fallback tersebut jika server dapat diarahkan langsung ke `public/`.

## Persiapan release

1. Buat backup database, `storage/uploads/`, dan `public/assets/avatars/`.
2. Deploy source tanpa `.git`, `.env`, `docs/`, file log, dump database, atau file unggahan dari komputer pengembang.
3. Buat `.env` produksi dari `.env.example` langsung di server.
4. Gunakan HTTPS dan pastikan PHP menerima indikator HTTPS dengan benar dari web server atau reverse proxy.
5. Berikan akses tulis hanya pada `storage/uploads/` dan `public/assets/avatars/`.

Konfigurasi produksi wajib memakai:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL` HTTPS sesuai domain produksi
- `APP_SECRET_KEY` acak minimal 32 karakter
- user database khusus dengan password kuat dan izin minimum
- `VERIF_API_TOKEN` acak minimal 32 karakter
- `VERIF_API_SYSTEM_ACTOR_KEY` yang cocok dengan system actor database

Jangan mencetak atau menaruh nilai konfigurasi tersebut di command history, dokumentasi, maupun repository.

## Database

Untuk instalasi baru, impor `app/Database/schema.sql`, lalu jalankan migrasi struktur untuk membuat atau menyelaraskan system actor:

```bash
php app/Database/migrate.php
```

Untuk instalasi lama, buat backup terlebih dahulu lalu jalankan perintah yang sama. Migrasi dibuat idempoten. Migrasi file PDF lama tidak dijalankan otomatis; tinjau dahulu dengan:

```bash
php app/Support/migrate_pdf_paths.php --dry-run
```

Jalankan tanpa `--dry-run` hanya setelah hasilnya diperiksa dan backup tersedia.

Token reset password baru disimpan sebagai hash. Link reset yang dibuat sebelum release ini tidak berlaku setelah deployment; pengguna dapat meminta link baru yang berlaku 30 menit.

## Email reset password

Reset password dinonaktifkan secara default. Aktifkan `MAIL_ENABLED=true` hanya setelah transport `mail()` PHP di server berhasil diuji dan `MAIL_FROM` berisi alamat pengirim yang valid. Jika server tidak menyediakan mail transport, biarkan fitur nonaktif atau pasang integrasi SMTP terpisah sebelum produksi.

## Pemeriksaan sebelum rilis

```bash
find app public -type f -name '*.php' -print0 | xargs -0 -n1 php -l
php app/Support/check_deploy.php
git diff --check
git status --short
```

Setelah server aktif, uji secara manual:

1. Beranda dan verifikasi kode/token valid, tidak valid, serta dicabut.
2. Login, logout, pembatasan role, perubahan password, dan sesi HTTPS.
3. Upload, penggantian, download bertanda tangan, serta penghapusan PDF.
4. API v1 dengan metode, token, input, dan rate limit yang benar maupun salah.
5. Reset password jika email diaktifkan.
6. QR scanner melalui HTTPS, tampilan mobile, dan log PHP tanpa stack trace ke browser.

Push dan pengalihan DNS dilakukan setelah seluruh pemeriksaan lulus.
