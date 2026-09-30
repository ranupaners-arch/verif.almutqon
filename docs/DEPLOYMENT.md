# Panduan deployment — shared hosting Indonesia

Aplikasi ini PHP native tanpa framework dan tanpa build step. Tidak ada
`composer install`, tidak ada `npm run build`. Yang diunggah ke server hanya
berkas apa adanya.

---

## 1. Dua topologi, pilih satu

### Opsi A — document root diarahkan ke `public/` (disarankan)

Di panel hosting (cPanel → Domains → Domains, atau Pengaturan Domain),
arahkan document root domain ke folder `public/`.

```
public/          <- document root, satu-satunya yang bisa diakses publik
app/  storage/  docs/  handoffs/  .env
```

Selesai. Berkas di luar `public/` tidak bisa dijangkau lewat URL sama sekali,
tidak bergantung pada `.htaccess`.

### Opsi B — fallback lewat root `.htaccess`

Dipakai kalau panel hosting tidak bisa mengubah document root. Berkas tetap
diunggah ke root folder (`public_html`), dan `.htaccess` di root yang
meneruskan permintaan ke `public/`.

Opsi B **bergantung pada `mod_rewrite`**. Kalau panel hosting menonaktifkannya
atau `AllowOverride` bernilai `None`, aturan itu diabaikan dan `.env`
justru bisa diunduh. Karena itu:

1. Selalu uji lewat `public/diagnosa.php` (bagian 6) —Pemeriksaan
   "Document root = public/" akan menampilkan peringatan.
2. Bila hosting tidak mendukung rewrite sama sekali, pindah ke Opsi A.

---

## 2. Kebutuhan server

| Kebutuhan | Minimum | Catatan |
|---|---|---|
| PHP | 8.1 | 8.2/8.3/8.4 aman. Di bawah 8.1 muncul halaman VERSION notice, bukan fatal error |
| Ekstensi | `pdo_mysql`, `fileinfo`, `mbstring` | wajib; seluruhnya diperiksa `diagnosa.php` |
| `upload_max_filesize` | 12M | PDF maksimum 10 MB, diberi jeda agar tidak tertolak |
| `post_max_size` | 14M | selalu lebih besar dari `upload_max_filesize` |
| Database | MySQL 5.7+ / MariaDB 10.3+ | dipakai `utf8mb4` |
| `.htaccess` | `AllowOverride` minimal `Options`, `FileInfo`, `Limit` | hanya relevan di Opsi B |

Nilai `upload_max_filesize` pada shared hosting biasanya diatur lewat
`.htaccess` (`php_value`) atau `php.ini` dari panel. `public/.user.ini`
hanya bekerja pada LiteSpeed/cPanel tertentu — karena itu batas
aplikasi sendiri (`MAX_UPLOAD_BYTES`) tetap terpisah.

---

## 3. Yang diunggah, yang tidak

**Unggah:**

```
app/  public/  storage/  index.php  .htaccess
```

**Jangan unggah:**

| Jangan | Alasan |
|---|---|
| `.env` | berisi seluruh kredensial produksi |
| `.git/`, `.DS_Store` | riwayat & sampah |
| `docs/`, `handoffs/`, `README.md`, `AGENTS.md` | dokumentasi internal |
| `storage/uploads/*` dari komputer dev | dokumen pengguna, bukan aset aplikasi |
| `public/assets/avatars/*` dari komputer dev | avatar staff, bukan aset aplikasi |
| `storage/logs/*` | log server lama |

Buat `.env` **di server** dari `.env.example`, jangan menyalinnya dari
komputer.Folder `storage/uploads/`, `storage/logs/`, dan
`public/assets/avatars/` harus ada dan writable (izin 755 atau 775).

---

## 4. Konfigurasi produksi

Salin `.env.example` menjadi `.env` di server, lalu isi minimal:

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://domain-anda.id
APP_SECRET_KEY=<64+ karakter acak>
APP_SETUP_TOKEN=<64+ karakter acak>

DB_HOST=<host mysql>
DB_PORT=3306
DB_DATABASE=<nama database>
DB_USERNAME=<user khusus>
DB_PASSWORD=<password kuat>

VERIF_API_TOKEN=<64+ karakter acak>
VERIF_API_SYSTEM_ACTOR_KEY=almutqon_bridge
```

| Kunci | Wajib | Keterangan |
|---|---|---|
| `APP_SECRET_KEY` | ya | minimal 32 karakter, aplikasi menolak start bila kurang |
| `APP_SETUP_TOKEN` | ya di production | mengaktifkan `diagnosa.php`. Kosongkan setelah selesai |
| `APP_TIMEZONE` | tidak | default `Asia/Jakarta` |
| `APP_TRUST_PROXY` | tidak | `true` hanya bila di belakang Cloudflare/load balancer |
| `ASSET_VERSION` | tidak | naikkan satu kali tiap `app.css`/`portal.css`/`nav.js` berubah |

> **Nilai environment server menang atas `.env`.** Kalau hosting menyediakan
> menu "Environment Variables", mengisinya di sana membuat kredensial tidak
> pernah tersimpan di disk sama sekali.

**Jangan** mencetak nilai-nilai ini ke terminal, dokumentasi, atau commit.

### Behind reverse proxy

Kalau memakai Cloudflare, aktifkan `APP_TRUST_PROXY=true`. Tanpa itu
`X-Forwarded-Proto` diabaikan, cookie session tidak ditandai `secure`, dan
`Strict-Transport-Security` tidak terkirim. Header dari klien tidak pernah
dipercaya secara default karena pemanggil bisa mengirimnya sendiri.

---

## 5. Instalasi database (phpMyAdmin)

Tidak ada wizard installer. Dua langkah manual:

1. **Buat database + user** di panel hosting, beri izin pada database itu saja.
2. **Import `app/Database/schema.sql`** lewat phpMyAdmin → menu Import.

`schema.sql` sudah versi final dan mencakup tabel, indeks, serta
`dokumen.jenis` (ENUM `dokumen`/`invoice`). Tidak perlu ALTER manual.

Lalu, dari **Terminal/SSH** (jika ada):

```bash
php app/Database/migrate.php     # idempoten, aman diulang
```

Bila tidak ada SSH, lewati — `migrate.php` hanya menyelaraskan
`system_actors` dengan `.env`, dan baris system actor bisa dibuat manual
lewat phpMyAdmin.

**Perbarui aplikasi di server yang sudah berisi data:** jangan import ulang
`schema.sql` (itu akan gagal atau merusak data). Backup dulu, lalu upload
hanya source.

**Migrasi PDF lama tidak berjalan otomatis.** Tinjau dulu:

```bash
php app/Support/migrate_pdf_paths.php --dry-run
php app/Support/migrate_jenis_dokumen.php --dry-run
```

Jalankan tanpa `--dry-run` hanya setelah hasilnya dibaca dan backup tersedia.

---

## 6. Verifikasi setelah upload

### 6a. Lewat browser — `/diagnosa.php`

Buka `https://domain-anda.id/diagnosa.php`, lalu masukkan nilai
`APP_SETUP_TOKEN`.

- Tanpa token, halaman membalas `401`.
- Bila `APP_SETUP_TOKEN` kosong, halaman membalas `404` (seolah tidak ada).
- Halaman **tidak pernah** menampilkan nilai kredensial, hanya status ya/tidak.

Semua butir harus LOLOS, atau PERINGATAN yang kamu sadari. Setelah selesai:

1. Hapus `public/diagnosa.php` dari server, **atau**
2. Kosongkan `APP_SETUP_TOKEN` di `.env`.

### 6b. Lewat terminal (bila ada SSH)

```bash
find app public -type f -name '*.php' -print0 | xargs -0 -n1 php -l
php app/Support/check_deploy.php     # exit 1 bila ada butir GAGAL
```

`check_deploy.php` memakai pemeriksaan yang sama dengan `diagnosa.php`
(`app/Support/deploy_checks.php`), jadi hasilnya konsisten.

### 6c. Uji manual di browser

1. Beranda: verifikasi kode/token — valid, tidak valid, dan sudah dicabut.
2. Login, logout, pembatasan role, ganti password, sesi bertahan.
3. Unggah PDF, ganti, unduh, hapus.
4. Pindai QR lewat HTTPS (butuh izin kamera).
5. Tampilan mobile 360–430px.
6. **Kontrol View Source** — tidak boleh ada satu pun URL `fonts.googleapis`,
   `unpkg`, `cdn.jsdelivr`, atau `cdnjs`. Kalau masih ada, `app.css` versi
   lama masih tersimpan di cache browser.
7. Buka `storage/logs/app-<tanggal>.log` bila ada masalah.

---

## 7. Kalau ada masalah

| Gejala | Penyebab & solusi |
|---|---|
| Halaman "Versi PHP perlu diperbarui" | pilih PHP 8.1+ di panel hosting |
| "Konfigurasi server tidak lengkap" | `.env` tidak terbaca atau ada kunci wajib kosong. Lihat `storage/logs/` |
| "Terjadi gangguan koneksi database" | `DB_HOST`/`DB_PORT`/user/salah. Cek juga apakah MySQL sudah aktif |
| 404 untuk semua URL | Opsi B aktif tapi `mod_rewrite` mati. Pindah ke Opsi A |
| Aset 404, HTML ada | `AllowOverride` tidak mengizinkan `RewriteRule` di `.htaccess` |
| "Pemindai kamera tidak tersedia" | hanya informasi: kamera tidak bisa dipakai di perangkat itu. Verifikasi manual tetap jalan |
| Font tampil beda dari biasanya | cache CSS lama. Naikkan `ASSET_VERSION` di `.env` lalu muat ulang |
| 500 tanpa pesan | `APP_DEBUG=false` menyembunyikan detail. Buka `storage/logs/app-<tanggal>.log` |
| Unggah selalu ditolak | `upload_max_filesize` < 12M. Naikkan lewat panel atau `php_value` di `.htaccess` |

---

## 8. Setelah rilis

- Hapus `public/diagnosa.php` atau kosongkan `APP_SETUP_TOKEN`.
- Pastikan `APP_DEBUG=false` dan `APP_ENV=production`.
- Cadangkan `storage/uploads/`, `public/assets/avatars/`, dan database
  secara berkala — ketiganya berada di luar Git.
- Jangan `git push` ke server. Deploy selalu manual lewat panel/File Manager.
