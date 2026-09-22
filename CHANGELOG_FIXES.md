# CHANGELOG_FIXES — Verif Almutqon
Entri terbaru di paling atas. Ditulis sesuai aturan adaCODE (perbaikan terverifikasi).

---

### Fix #4 — Pembersihan file sampah & penghapusan backdoor DEBUG auto-login
Gejala: `public/__sesi.php` (auto-login `staff_id=2` tanpa password, DEBUG) masih di web root — lubang autentikasi; `public/api/theme.php` adalah tombstone yang selalu balas 410; `app/Support/migrate_theme_system.php` migrasi fitur tema yang sudah dihapus; folder `storage/uploads_legacy_t/` kosong (hanya `.gitkeep`).

| Aspek | Rincian |
|---|---|
| Tanggal | 2026-09-22 |
| File | Hapus: `public/__sesi.php`, `public/api/theme.php`, `app/Support/migrate_theme_system.php`, `storage/uploads_legacy_t/` (dir + `.gitkeep`) |
| Masalah | Backdoor DEBUG aktif di web root (docs LAPORAN.md:158 berkali-kali meminta "segera hapus sebelum deploy"); endpoint tema 410 mati tanpa pemanggil; migrasi untuk fitur yang telah dihapus; folder arsip kosong |
| Akar | `__sesi.php` sisa sesi debugging yang gagal dihapus pada pembersihan backdoor sebelumnya (CATATAN #94 hanya menghapus `_test_session.php`); fitur ganti tema dihapus tapi tombstone endpoint & migrasi dibiarkan |
| Fix | Hapus keempat junk/backdoor; pertahankan `app/Support/migrate_avatar_theme.php` (bagian avatar masih dipakai — upload avatar aktif, skema `staff.avatar` ada; bagian theme-nya mati namun file satu-satunya rekam skema) |
| Verifikasi | `php -l` 42 file PHP bersih; `grep api/theme|__sesi|uploads_legacy|migrate_theme_system` di `app/`+`public/` → 0 referensi tersisa; `storage/uploads/` tetap utuh |
| Pelajaran | Pembersihan backdoor harus mencakup seluruh pola sesi debug (`_test_session.php` DAN `__sesi.php`); endpoint/migrasi untuk fitur yang dihapus jadi sampah dua-tahap — tombstone + migrator — buang keduanya sekaligus |
| Log Keyword | `__sesi.php`, backdoor, auto-login, theme, 410, cleanup, migrate_theme |
| Deploy | BELUM — perubahan lokal belum di-push; pastikan `__sesi.php` TIDAK ikut deploy ke live |

### Fix #3 — Akses "Aktif" ↔ "Dicabut" di filter Riwayat Dokumen gagal pindah status
Gejala: klik "Dicabut" saat sedang di tab "Aktif" (dan sebaliknya) tidak mengubah daftar — tetap di status semula. "Semua"↔"Aktif" dan "Semua"↔"Dicabut" normal.

| Aspek | Rincian |
|---|---|
| Tanggal | 2026-09-19 |
| File | `app/Pages/dashboard.php` |
| Masalah | Link tab status menyuruh `$buildQs(['status' => target])`, tapi helper menimpa nilai dari `$extra` dengan `$statusF` yang sedang aktif |
| Akar | Baris `if ($statusF !== '') { $arr['status'] = $statusF; }` di `$buildQs` berjalan SETELAH `$arr = $extra` → status target (Aktif/Dicabut) selalu diganti status saat ini. Terlihat "normal" pada transaksi Semua→Aktif/Dicabut karena `$statusF` kosong sehingga tidak ada penimpaan (kait: Fix #2 juga melibatkan helper ini) |
| Fix | Hindari penimpaan: `if (!isset($arr['status']) && $statusF !== '')` — status target dari `$extra` menang, `$statusF` hanya dipakai sebagai fallback (mis. untuk link pagination) |
| Verifikasi | `php -l` OK; simulasi PHP semua transisi benar: Aktif→Dicabut `status=revoked`, Dicabut→Aktif `status=active`, pagination `page=2&status=active`, pencarian dipertahankan (`status=revoked&q=X`) |
| Pelajaran | Helper query yang menulis ulang kondisi harus didahulukan dari `$extra`, bukan sesudahnya — uji semua pasangan transisi tab, bukan hanya dari state kosong |
| Log Keyword | `buildQs`, `filter-tab`, `status`, `aktif`, `dicabut`, pagination |
| Deploy | PENDING konfirmasi visual user |

### Fix #2 — Filter "Semua" di Riwayat Dokumen tidak bisa keluar dari filter status + dokumen dicabut tak tampak redup
Gejala: klik tab "Semua" saat berada di "Aktif"/"Dicabut" tidak mengembalikan daftar lengkap (link tampak mati, tab Semua tak pernah aktif); dokumen berstatus dicabut tampak sama seperti dokumen aktif.

| Aspek | Rincian |
|---|---|
| Tanggal | 2026-09-19 |
| File | `app/Pages/dashboard.php`, `public/assets/app.css` |
| Masalah | (1) Tab Semua memakai `$buildQs()` yang selalu menyertakan ulang `status` saat difilter → link tak menghapus filter; (2) `.doc.is-off` tak terdefinisi (hanya `.table .is-off` dan `.pager .is-off`) sehingga kelas `is-off` pada kartu riwayat tidak berdampak |
| Akar | (1) Desain helper `buildQs` mempertahankan status secara otomatis; (2) pemakaian kelas kartu `.doc` tidak pernah diberi saja regulasi dim |
| Fix | Tab Semua dibangun manual dengan `http_build_query` — hasil `dashboard.php?` (kosong/bersih) atau `dashboard.php?q=…` (pertahankan pencarian), tanpa `status`/`page`; tambah aturan `.doc.is-off { opacity:.55; filter:saturate(.7); }` |
| Verifikasi | `php -l` dashboard OK; simulasi PHP: Semua → `dashboard.php?` (status aktif) / `dashboard.php?q=sertifikat` (dengan pencarian) — status terhapus; braces CSS OK; cache buster → `20260919l` |
| Pelajaran | Link filter yang seharusnya "reset" butuh builder query terpisah, jangan reuse helper yang menyusun ulang kondisi aktif; setiap kelas state (`is-off`) harus diuji untuk semua konteks pemakaiannya |
| Log Keyword | `filter-tab`, `buildQs`, `status`, `is-off`, `dicabut`, `revoked` |
| Deploy | PENDING konfirmasi visual user (hard-refresh `Cmd+Shift+R`, pastikan `?v=20260919l`) — mekanisme URL & CSS terverifikasi statis |

### Fix #1 — Ikon Material Symbols tampil sebagai teks mentah ("article", "person", dll) di kartu KPI dashboard
Gejala: ikon kartu KPI (Total Dokumen, Nomor Urut, Invoice/Struk, Dokumen Anda) tidak tampil, yang muncul hanya tulisan Inggris di dalam chip berwarna.

| Aspek | Rincian |
|---|---|
| Tanggal | 2026-09-19 |
| File | `public/assets/app.css`, `public/assets/fonts/material-symbols-rounded.woff2` (baru), `app/Pages/*.php` (bersihkan link) |
| Masalah | Ligatur Material Symbols membutuhkan font; saat font gagal dimuat browser menampilkan teks mentah |
| Akar | Font hanya dimuat via `<link>` Google Fonts di `<body>` (`_masthead.php`) lalu dipindah ke `<head>`, TAPI sumbernya tetap `fonts.googleapis.com` — gagal di jaringan yang memblokir Google Fonts |
| Fix | Self-host font: unduh WOFF2 (`fonts/material-symbols-rounded.woff2`, 364K) + `@font-face` lokal + aturan `.material-symbols-rounded` (liga, variation settings) di `app.css`; hapus semua `<link>` Google Material; cache buster → `20260919j` |
| Verifikasi | Chrome headless dengan `--host-resolver-rules` memblokir `fonts.googleapis.com` & `.gstatic.com`, tanpa link Google: `document.fonts.check` OK, ukuran ligatur = 24px untuk ke-4 ikon (jadi glif, bukan teks) |
| Pelajaran | UI jangan mengandalkan CDN font untuk elemen kritikal (ikon menu/sidebar pun ikut tertolong oleh fix ini — kini semua ikon dari font lokal) |
| Log Keyword | `material symbols`, `glyph`, `ligature`, `fonts.googleapis` |
| Deploy | PENDING konfirmasi visual user (hard-refresh `Cmd+Shift+R`, pastikan `?v=20260919j`) — mekanisme sudah terbukti di engine render offline |