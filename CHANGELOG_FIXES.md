# CHANGELOG_FIXES — Verif Almutqon
Entri terbaru di paling atas. Ditulis sesuai aturan adaCODE (perbaikan terverifikasi).

---

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