# BRIEF — Fase 4 (Frontend consolidation) untuk agent eksekusi

Proyek: `verif-almutqon` (root kerja = repo ini). Baca `AGENTS.md` di root — itu aturan wajib.

## Aturan paling penting
- Kamu **hanya** mengerjakan 4A, 4B, 4C, 4D di bawah. Tidak ada task lain.
- **Jangan** menyentuh: `app/Bootstrap.php`, `app/Config/`, `app/Http/`, `app/Support/`, `app/Database/`, `.htaccess` mana pun, `.env`, `storage/`, `public/assets/avatars/`.
- **Jangan** `git add`, `git commit`, atau `git push`.
- **Jangan** menjalankan `rm -rf`, `git reset`, `git clean`, atau perintah destruktif lain.
- **Jangan** mengubah isi `public/assets/app.css` dan `public/assets/portal.css` (kecuali penambahan blok `@font-face` di 4A).
- **Jangan** mengubah URL, query string Aside, atau teks yang terlihat pengguna.
- Kalau ada yang tidak dijelaskan di brief ini dan tidak bisa kamu pastikan → **berhenti dan lapor**, jangan menebak.

## Konteks
- `app/Pages/*.php` adalah halaman. Masing-masing punya blok `<head>` sendiri yang nyaris identik (duplikat).
- Semua aset diambil dengan path relatif `assets/...` (JANGAN diubah jadi absolut).
- `ASSET_VERSION` **sudah ada** sebagai konstanta, sudah didefinisikan di `app/Config/app.php`. Pakai `<?= ASSET_VERSION ?>`.
- Vendor JS yang akan di-self-host: `public/assets/vendor/`. Font: `public/assets/fonts/`.
- Pola self-host font sudah berhasil dipakai di repo ini untuk Material Symbols — ikuti pola yang sama (lihat `@font-face` untuk `material-symbols-rounded` di `public/assets/app.css`).

---

## 4A — Self-host dependency (CDN → lokal)

### A1. Unduh file vendor (SIMPAN VERSI PERSIS, jangan pakaiversi terbaru)
| URL sumber | Simpan ke |
|---|---|
| `https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js` | `public/assets/vendor/html5-qrcode.min.js` |
| `https://cdn.jsdelivr.net/npm/sweetalert2@11.22.3/sweetalert2.all.min.js` | `public/assets/vendor/sweetalert2.min.js` |

Kalau URL sweetalert2 itu 404, cari **satu versi 11.x** yang stabil di jsdelivr/unpkg dan pakai versi itu. Catat nomor versinya.

### A2. Unduh font (variable, latin subset)
Unduh **variable woff2** untuk dua keluarga ini, subset latin, weight range 200–800:
- `Plus Jakarta Sans` (display)
- `Inter` (UI)

- Plus Jakarta Sans: `https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@200..800&display=swap`
- Inter: `https://fonts.googleapis.com/css2?family=Inter:wght@200..800&display=swap`

Cara dapat file: `curl` URL CSS di atas dengan header `User-Agent` browser modern (mis. `Mozilla/5.0 ... Chrome/120`), lalu ambil URL `.woff2` yang aparecem di blok `/* latin */`. Karena weight-nya `wght@200..800` (range), hasilnya **satu file variable per keluarga**.
- Simpan: `public/assets/fonts/plus-jakarta-sans-var.woff2` dan `public/assets/fonts/inter-var.woff2`
- **Bukan** 7 file per weight. Cukup 1 file per keluarga.

### A3. Deklarasikan `@font-face` di `public/assets/app.css`
Tambahkan **di bagian atas file, setelah baris `@font-face` material-symbols yang sudah ada**:
- `@font-face` untuk `Plus Jakarta Sans`, `font-family: 'Plus Jakarta Sans'`, `src: url('fonts/plus-jakarta-sans-var.woff2') format('woff2')`, `font-weight: 200 800`, `font-style: normal`, `font-display: swap`.
- `@font-face` untuk `Inter`, sama polanya dengan `inter-var.woff2`, `font-weight: 200 800`.
- **Jangan** ubah `--font-display` / `--font-ui` yang sudah ada di `:root` — hanya pastikan nilainya tetap menunjuk ke nama keluarga yang sama.

### A4. Ganti `<link>` Google Fonts
Hapus **semua** tag `<link href="https://fonts.googleapis.com/...">` dari seluruh `app/Pages/*.php`. Font sekarang dimuat oleh `app.css` lewat `@font-face` lokal.

### A5. Ganti `<script src="https://...">` di `app/Pages/index.php`
- `https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js` → `assets/vendor/html5-qrcode.min.js`
- `https://cdn.jsdelivr.net/npm/sweetalert2@11` → `assets/vendor/sweetalert2.min.js`
- Hapus juga `<link rel="preconnect" href="https://fonts.googleapis.com">` dan `https://fonts.gstatic.com` di `app/Pages/dashboard.php` (sudah tidak relevan).

### A6. Catat asal file
Buat `public/assets/vendor/README.md`: nama file, nomor versi, URL sumber, tanggal unduh. Singkat saja.

---

## 4B — Satu cache-buster

Ganti **semua** string `?v=2026xxxx` di `app/Pages/*.php` menjadi `?v=<?= ASSET_VERSION ?>`.

Penting: nilai `?v=` yang sekarang **berbeda-beda per file** (`app.css` ada `20260926f`, `...g`, `...h`, `...k`; `nav.js` ada `20260926a` dan `...b`). Itu memang bug — samakan semua ke `ASSET_VERSION`.

---

## 4C — Partial `_head.php`

Buat `app/Pages/_head.php` dengan kontrak:

```php
<?php
// app/Pages/_head.php — blok <head> bersama untuk semua halaman.
// Variabel opsional sebelum include:
//   $pageTitle   string  isi <title> (tanpa suffix "- AL MUTQON")
//   $extraCss    array   path aset tambahan, mis. ['assets/portal.css']
//   $extraJs     array   <script src> tambahan (tanpa cache-buster), mis. ['assets/vendor/html5-qrcode.min.js']
//   $bodyClass   string  kelas tambahan pada <body>
```

Isinya harus memuat: `<!DOCTYPE html>`, `<html lang="id">`, `<head>`, `<meta charset="utf-8">`, `<meta name="viewport" content="width=device-width, initial-scale=1">`, `<title><?= htmlspecialchars($pageTitle ?? 'AL MUTQON') ?> - AL MUTQON</title>`, dua `<link rel="icon">` (`assets/favicon.png` dan `assets/favicon.ico`, keduanya dengan `?v=<?= ASSET_VERSION ?>`), `<link href="assets/app.css?v=<?= ASSET_VERSION ?>" rel="stylesheet">`, lalu loop `$extraCss` dan `$extraJs`, lalu `</head>` dan `<body<?= $bodyClass ? ' class="'.$bodyClass.'"' : '' ?>>`.

Lalu refactor **9 halaman** ini memakai `include __DIR__ . '/_head.php';` dan hapus blok `<head>` masing-masing:
`index.php`, `dashboard.php`, `data_invoice.php`, `rekap_nomor.php`, `manage_staff.php`, `change_password.php`, `profil.php`, `lupa_password.php`, `reset_password.php`, `audit_log.php`.

Preservasi per halaman:
- **Hanya `index.php`** memakai `portal.css` → lewat `$extraCss`.
- **Hanya `dashboard.php`** memakai `assets/vendor/qr-code-styling.js` → lewat `$extraJs`. Tapi `dashboard.php` sudah punya variabel `$extraHead` yang dipakai untuk hal ini, dan `_masthead.php`/partial lain mungkin memakainya. **Baca `dashboard.php` dan `_masthead.php` dulu**; kalau `$extraHead` juga dipakai untuk hal lain, tetap pakai `$extraHead` dan cukup pastikan isinya ikut ter-render. Pilih yang paling tidak berisiko.
- **Hanya `index.php`** punya dua `<script>` CDN — setelah 4A keduanya sudah lokal, jadi masukkan keduanya ke `$extraJs`.
- **Hanya `index.php`** yang punya `<script src="assets/nav.js">` di akhir `<body>`; halaman staff juga memakainya. **Biarkan tag `nav.js` di akhir body apa adanya** — jangan dipindah ke `_head.php`.
- `reset_password.php` sebelumnya TIDAK memuat Inter, `lupa_password.php`/`index.php` tidak memuat weight 400. **Setelah semua font lokal, ini otomatis seragam** — tidak perlu dipSpecial. Jangan Fameholders|m budgeting attempts untuk meniru perbedaan itu.

Kalau saat mengerjakan kamu ragu soal pemindahan `<head>` di suatu halaman → **biarkan `<head>` halaman itu apa adanya** dan lapor. Gagal separuh lebih baik daripada merusak semua.

---

## 4D — Degradasi aman (wajib)

Di `app/Pages/index.php`, setelah script termuat:
- Bungkus: kalau `typeof Html5QrcodeScanner === 'undefined'`, sembunyikan elemen pemindai QR (elemen yang berisi kamera/`#reader`) dan tampilkan pesan singkat bahwa pemindai kamera tidak tersedia di server ini, plus tautan/penjelas bahwa verifikasi manual dengan kode/token tetap bisa dipakai. Form input kode/token **harus tetap berfungsi normal**.
- Kalau SweetAlert2 tidak termuat (`typeof Swal === 'undefined'`), pakai `alert()` biasa sebagai pengganti di tempat yang memanggil `Swal.fire(...)`. Jangan membuat error JS.

---

## Verifikasi (WAJIB dijalankan semua, lapor hasilnya)
1. `find app public -type f -name '*.php' -print0 | xargs -0 -n1 php -l` → harus semua "No syntax errors".
2. `grep -rn "cdn\.\|unpkg\|googleapis\|gstatic" app public` → harus **kosong**.
3. `grep -rn "v=2026" app` → harus **kosong**.
4. `ls -la public/assets/vendor public/assets/fonts` → file vendor + 2 woff2 harus ada dan ukuran wajar (bukan 0 byte).
5. `grep -c "<head>" app/Pages/*.php` → hanya `_head.php` yang punya `<head>`, halaman tidak lagi.
6. `grep -rn "Html5QrcodeScanner\|Swal\." app/Pages/index.php` → masih ada (logika scanner/alert tidak boleh hilang).

## Format laporan akhir
Tulis ringkas:
- daftar file yang dibuat / diubah
- output ringkas dari 6 pemeriksaan di atas
- nomor versi vendor yang benar-benar terpakai
- apa pun yang tidak kamu kerjakan atau meragukan
