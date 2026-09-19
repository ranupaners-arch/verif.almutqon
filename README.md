# AL MUTQON Verification System

## Struktur

- `public/` adalah satu-satunya web root: entry point dan aset browser.
- `app/` menyimpan bootstrap, konfigurasi, handler HTTP, dan halaman.
- `storage/uploads/` menyimpan PDF dan tidak boleh dapat diakses langsung dari web.

## Menjalankan lokal

1. Atur document root Herd/web server ke folder `public/`.
2. Salin `.env.example` menjadi `.env`, lalu isi `APP_URL` dengan URL lokal Herd dan buat `APP_SECRET_KEY` acak yang panjang.
3. Impor `app/Database/schema.sql` ke database yang dikonfigurasi.
4. Pastikan PHP dapat menulis ke `storage/uploads/`.

Konfigurasi produksi harus memakai kredensial database unik, HTTPS, dan nilai `.env` yang tidak pernah dikomit.
