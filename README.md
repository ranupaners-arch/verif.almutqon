# AL MUTQON Verification System

Aplikasi PHP native untuk verifikasi dan pengelolaan berbagai jenis dokumen digital AL MUTQON.

## Struktur

- `public/` adalah document root dan berisi entry point serta aset browser.
- `app/` berisi konfigurasi, HTTP handler, halaman, schema, dan migrasi.
- `storage/uploads/` menyimpan PDF privat yang hanya dilayani melalui endpoint download.
- `docs/` berisi dokumentasi pengembangan dan tidak ikut menjadi web root.
- `index.php` dan `.htaccess` di root hanya fallback untuk hosting yang tidak dapat memakai `public/` sebagai document root.

## Menjalankan lokal

1. Arahkan document root web server ke folder `public/`.
2. Salin `.env.example` menjadi `.env`, lalu untuk lokal set `APP_ENV=development`, `APP_DEBUG=true`, dan `APP_URL=http://verif.almutqon.test` (produksi wajib `APP_ENV=production` + `APP_URL` HTTPS).
3. Impor `app/Database/schema.sql` untuk database baru.
4. Untuk database yang sudah ada, jalankan `php app/Database/migrate.php` setelah membuat backup.
5. Pastikan PHP dapat menulis ke `storage/uploads/` dan `public/assets/avatars/`.

Panduan produksi lengkap tersedia di [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md).

## Pemeriksaan

```bash
find app public -type f -name '*.php' -print0 | xargs -0 -n1 php -l
php app/Support/check_deploy.php
```

Jangan commit `.env`, credential, token, file unggahan, dump database, atau log produksi.
