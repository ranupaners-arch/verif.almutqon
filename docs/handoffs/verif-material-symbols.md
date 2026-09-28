# Migrasi ikon inline SVG → Google Material Symbols
Status: DITUNDA · Service: frontend (`app/Pages/*.php`) · Diperbarui: 2026-09-25 12:50

## Sedang dikerjakan
— (belum dimulai; user menunda, "nanti")

## Status terakhir
- Font Material Symbols SUDAH terpasang lokal: `public/assets/fonts/material-symbols-rounded.woff2`, di-`@font-face` di `public/assets/app.css:8`.
- Sebagian halaman sudah memakai `material-symbols-rounded` (dashboard stat, sidebar, masthead).
- Commit `87c12e0` (login staff ke index.php) + push sudah LIVE di GitHub — perubahan bersih, belum ada miletone migrasi.

## Keputusan penting
- Tidak perlu pasang sumber font eksternal; pakai font lokal yang ada.

## Langkah berikutnya
1. Ganti **16 ikon `<svg>` inline** (feather-style, stroke=currentColor) jadi `<span class="material-symbols-rounded">…</span>`:
   - `_sidebar.php` (1) · `change_password.php` (3) · `dashboard.php` (4) · `data_invoice.php` (2) · `index.php` (6) · `lupa_password.php` (1) · `profil.php` (1) · `reset_password.php` (1).
2. Petakan path feather → nama simbol Material (mis. download, check, info, plus, scan, logout, lock, edit, person).
3. Periksa ukuran/keselarasan: `.material-symbols-rounded` default `font-size` vs SVG lama (16–46px); cek `app.css` def (`line 15`) dan `.sidebar__nav-link` (`line 603`).
4. Verifikasi visual di browser (Playwright) per halaman.

## Jangan lakukan
- Jangan tambah CDN/Google Fonts link — font sudah lokal.
- Jangan ubah `stat__icon`/`.landing__icon` container yang sudah rapi.
