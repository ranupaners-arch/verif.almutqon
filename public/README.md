# public/ — document root

Folder ini adalah **document root**. Di sinilah sebagian besar URL publik
berhenti, jadi jangan menaruh apa pun di sini tanpa alasan.

## Dua jenis berkas di sini

**1. Aset** — `assets/` (CSS, JS, font, gambar, vendor). Di-*self-host* dengan
sengaja: tidak ada CDN, tidak ada Google Fonts, supaya halaman tetap utuh
saat jaringan diblokir atau lambat.

**2. Shim** — `*.php` di sini bukan implementation. Semuanya berbentuk:

```php
<?php
// Shim — kode asli ada di app/Pages/dashboard.php. Jangan diedit di sini.
require dirname(__DIR__) . '/app/Pages/dashboard.php';
```

Kenapa perlu? Arahkan document root ke `public/`, lalu `/dashboard.php`
menyebut `public/dashboard.php` — sedangkan kodenya berada di `app/`, di luar
document root agar tidak bisa diunduh. Shim itu jembatan wajibnya.

**Kalau kamu perlu mengubah halaman, buka `app/Pages/` — bukan berkas di sini.**

## Peta lengkap

| URL | Shim | Kode asli |
|---|---|---|
| `/index.php` | `public/index.php` | `app/Pages/index.php` |
| `/dashboard.php` | `public/dashboard.php` | `app/Pages/dashboard.php` |
| `/staff.php` | `public/staff.php` | `app/Pages/staff.php` |
| `/manage_staff.php` | `public/manage_staff.php` | `app/Pages/manage_staff.php` |
| `/change_password.php` | `public/change_password.php` | `app/Pages/change_password.php` |
| `/profil.php` | `public/profil.php` | `app/Pages/profil.php` |
| `/lupa_password.php` | `public/lupa_password.php` | `app/Pages/lupa_password.php` |
| `/reset_password.php` | `public/reset_password.php` | `app/Pages/reset_password.php` |
| `/rekap_nomor.php` | `public/rekap_nomor.php` | `app/Pages/rekap_nomor.php` |
| `/data_invoice.php` | `public/data_invoice.php` | `app/Pages/data_invoice.php` |
| `/audit_log.php` | `public/audit_log.php` | `app/Pages/audit_log.php` |
| `/download.php` | `public/download.php` | `app/Http/download_document.php` |
| `/api/avatar.php` | `public/api/avatar.php` | `app/Http/avatar.php` |
| `/api/search_audit.php` | `public/api/search_audit.php` | `app/Http/search_audit.php` |
| `/api/search_documents.php` | `public/api/search_documents.php` | `app/Http/search_documents.php` |

## Dua berkas yang TIDAK shim

| Berkas | Kenapa beda |
|---|---|
| `index.php` di root project | Fallback untuk hosting yang document root-nya tidak bisa diarahkan ke `public/`. Hanya dipakai kalau `.htaccess` root tidak aktif. Lihat `docs/DEPLOYMENT.md` bagian 1. |
| `diagnosa.php` | Halaman diagnosa deploy, punya kode sendiri. Membaca `APP_SETUP_TOKEN`. Hapus setelah deploy, atau kosongkan tokennya. |

## peraturan folder ini

- `.htaccess` di sini memblokir `.sql`, `.bak`, `.log`, `.ini`, `.example`,
  `.dist`, `.md`, dan berkas berawalan titik — termasuk `README.md` ini.
- Jangan menaruh PHP baru di sini. Taruh di `app/Pages/` atau `app/Http/`,
  lalu tambahkan shim-nya.
- Kalau ada halaman yang boleh diakses publik tanpa login, tetap lewat shim.
  Jangan pernah `require` langsung dari `public/` ke `app/` dengan path
  hardcode yang tidak portable.
