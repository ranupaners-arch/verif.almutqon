# AL MUTQON Verification System

Aplikasi web untuk menerbitkan dan memverifikasi keaslian dokumen digital (sertifikat, surat, hingga invoice/struk) milik AL MUTQON Arabic Learning. Dipakai oleh staf internal untuk mengelola dokumen, dan oleh publik untuk mengecek keaslian dokumen lewat token, kode 6-digit, atau pemindaian QR.

## Fitur utama

- **Verifikasi publik** — cek keaslian dokumen via link token (64 karakter hex), kode 6-digit, atau scan QR langsung dari browser (`index.php`).
- **Dashboard staf** — buat dokumen baru (dengan/tanpa PDF), unggah ulang PDF, cabut (revoke) dan aktifkan kembali dokumen.
- **Data invoice/struk** — daftar khusus dokumen jenis `invoice`, terpisah dari surat/sertifikat bernomor urut.
- **Rekap nomor surat** — rekap nomor urut dokumen jenis `dokumen` untuk staf.
- **Manajemen staf** — tambah/ubah/nonaktifkan akun staf, atur role (`staff`, `admin`, `direktur`), khusus admin & direktur.
- **Jejak audit** — log aktivitas dengan pencarian, khusus admin & direktur.
- **Akun & keamanan staf** — login, ganti password, lupa/reset password (via email bila `MAIL_ENABLED=true`), upload foto profil.
- **API internal v1** — endpoint `POST /api/v1/dokumen.php` dan `/api/v1/staf.php` dengan autentikasi Bearer token, untuk integrasi dua arah dengan sistem ALMUTQON (create/revoke/restore/replace_pdf dokumen, upsert/toggle/rename staf).
- **Halaman diagnosa deploy** — `public/diagnosa.php` untuk memeriksa kesiapan server (PHP, ekstensi, koneksi DB) tanpa akses SSH, dilindungi token terpisah.
- **Proteksi bawaan** — CSRF token, rate limiting per aksi (login, reset, verifikasi, upload, API), validasi upload PDF, dan prepared statements di seluruh query.

## Tech stack

- PHP native (≥ 8.1), tanpa framework dan tanpa build step
- MySQL 5.7+ / MariaDB 10.3+ (`utf8mb4`)
- HTML, CSS, dan JavaScript vanilla (aset di-*self-host*, tanpa CDN)
- `html5-qrcode`, `qr-code-styling`, dan `sweetalert2` (vendor JS lokal di `public/assets/vendor/`)
- Konfigurasi lewat `.env`

## Menjalankan lokal

1. Arahkan document root web server ke folder `public/`.
2. Salin `.env.example` menjadi `.env`, lalu untuk lokal set `APP_ENV=development`, `APP_DEBUG=true`, dan `APP_URL=http://verif.almutqon.test` (produksi wajib `APP_ENV=production` + `APP_URL` HTTPS). Isi juga `APP_SECRET_KEY`, kredensial database, dan `VERIF_API_TOKEN` dengan nilai acak milikmu sendiri.
3. Impor `app/Database/schema.sql` untuk database baru.
4. Untuk database yang sudah ada, jalankan `php app/Database/migrate.php` setelah membuat backup.
5. Pastikan PHP dapat menulis ke `storage/uploads/` dan `public/assets/avatars/`.
6. Jalankan server bawaan PHP dari folder `public/` untuk uji cepat, misalnya `php -S localhost:8000`.

Tidak ada langkah build (tidak ada `composer install` / `npm run build`) — berkas diunggah apa adanya ke hosting.

Panduan produksi lengkap tersedia di [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md).

## Pemeriksaan

```bash
find app public -type f -name '*.php' -print0 | xargs -0 -n1 php -l
php app/Support/check_deploy.php
```

## Struktur folder

- `public/` — document root publik; berisi shim ke `app/`, aset frontend (`assets/`), dan API publik (`api/`).
- `app/Config/` — konfigurasi environment, path, dan versi PHP.
- `app/Database/` — schema SQL dan skrip migrasi.
- `app/Http/` — handler endpoint dan aksi dokumen/API (dipanggil lewat shim di `public/`).
- `app/Pages/` — logic dan markup tiap halaman staf/publik.
- `app/Support/` — helper (autentikasi, rate limiting, keamanan, mail, dll).
- `storage/uploads/` — PDF privat, hanya dilayani lewat endpoint download, tidak dapat diakses langsung.
- `docs/` — dokumentasi pengembangan dan deployment, bukan bagian web root.
- `index.php` dan `.htaccess` di root hanya fallback untuk hosting yang tidak bisa memakai `public/` sebagai document root.

## Deploy

Aplikasi ini PHP native untuk shared hosting, tanpa pipeline CI/CD maupun rilis APK (berbeda dari ukom-mobile/ukom-dashboard yang APK-nya diunduh dari GitHub Releases). Deploy dilakukan dengan mengunggah berkas langsung ke hosting sesuai langkah di [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md), termasuk verifikasi lewat `public/diagnosa.php`.

Jangan commit `.env`, credential, token, file unggahan, dump database, atau log produksi.

## Kredit

Dibuat oleh **Ranupane** (Ranupane Rimba Argana), founder [Kukerjain](https://kukerjain.dev).

Profil: https://ranupane.kukerjain.dev
