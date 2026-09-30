# CHANGELOG_FIXES — Verif Almutqon
Entri terbaru di paling atas. Ditulis sesuai aturan adaCODE (perbaikan terverifikasi).

---

### Fix #10 — Halaman Rekap Nomor 500: `$pageTitle` hilang setelah konsolidasi `_head.php`
Gejala: `/rekap_nomor.php` balas HTTP 500 dengan body kosong. Halaman lain normal. Baru muncul saat diuji lewat nginx + PHP-FPM; di `php -S` halaman ini selalu lolos.

| Aspek | Rincian |
|---|---|
| Tanggal | 2026-09-30 |
| File | `app/Pages/rekap_nomor.php:43` |
| Masalah | `$pageTitleFull` di-set, `$pageTitle` tidak. `_masthead.php:12` memanggil `htmlspecialchars($pageTitle)` |
| Akar | Saat konsolidasi 10 `<head>` jadi `_head.php` (Fase 4), `rekap_nomor.php` kehilangan baris `$pageTitle`; hanya `$pageTitleFull` yang tersisa. Tidak ketahuan karena `set_error_handler` menjadikan warning sebagai exception hanya saat `APP_DEBUG=true`, dan smoke test harness lama mendefinisikan `$pageTitle` secara global sehingga men masking bug-nya |
| Fix | Tambahkan `$pageTitle = 'Rekap Nomor';` sebelum `$pageTitleFull` |
| Verifikasi | 11 halaman diuji via HTTP nyata (nginx + PHP-FPM, tiap request terisolasi seperti FPM): semua 200, 10 judul cocok byte-per-byte dengan nilai sebelumnya, 0 entri WARNING/ERROR baru di `storage/logs/app-2026-09-30.log` setelah fix. `php -l` 66/66. Audit 7 halaman yang me-`_masthead`: semua punya `$pageTitle`, `$pageTitleFull`, `$navActive` |
| Pelajaran | Harness render yang menyetel variabel halaman secara global menutupi bug yang nyata muncul di FPM. Uji halaman selalu lewat HTTP per-request, bukan `include` di satu proses. Warning yang jadi exception saat `APP_DEBUG` menghasilkan 500, bukan halaman terpotong — untuk bug undefined variable, `php -l` dan smoke test sama-sama buta |
| Log Keyword | rekap_nomor 500, undefined variable pageTitle, _masthead, PHP-FPM, smoke test false negative |
| Deploy | BELUM — belum dipush |

### Fix #9 — Refactor host-ready: config terpisah, error tertangani, aset lokal, cache-buster tunggal
Gejala: aplikasi bergantung pada CDN & Google Fonts (blank bila diblokir jaringan), 39 cache-buster tak sinkron sehingga sebagian halaman memuat CSS lama, stack trace PHP bisa bocor di production, label "WIB" tidak cocok dengan zona waktu server, dan `parse_ini_file` merusak nilai `.env` yang mengandung `#`.

| Aspek | Rincian |
|---|---|
| Tanggal | 2026-09-30 |
| File | `app/Bootstrap.php`, `app/Config/{php_version,env,app,paths,database}.php`, `app/Support/{errors,security,account,rate_limit,documents,auth,mail,format,deploy_checks,jenis_dokumen}.php`, `app/Pages/_head.php`, `public/diagnosa.php`, `public/assets/app.css`, `public/.htaccess`, `.htaccess` |
| Masalah | Monolit 312 baris mencampur 9 tanggung jawab; tidak ada exception handler; tidak ada `date_default_timezone_set`; `getenv()` tanpa cadangan `putenv`; 10 duplikasi `<head>`; path avatar di-hardcode di 4 tempat |
| Akar | Pertumbuhan fitur ditambahkan langsung ke satu berkas, dan konfigurasi di Schmidt dibaca ulang di beberapa lokasi dengan cara berbeda |
| Fix | `Bootstrap.php` → 57 baris orkestrator; parser `.env` sendiri; `set_exception_handler` + `set_error_handler` (warning jadi exception hanya saat `APP_DEBUG`); `SET time_zone` disamakan dengan `APP_TIMEZONE`; batas rate limit dihitung `NOW() - INTERVAL` di SQL; html5-qrcode 2.3.8 + SweetAlert2 11.22.2 + 2 font variable di-self-host; satu `ASSET_VERSION`; `.htaccess` deny di `app/ storage/ docs/ handoffs/` tanpa bergantung `mod_rewrite`; `diagnosa.php` gate `APP_SETUP_TOKEN` |
| Verifikasi | `php -l` 66/66 file bersih; `grep -c "cdn\.\|unpkg\|googleapis\|gstatic"` di `app`+`public` = 0; `grep -rn "v=2026" app` = 0; 10 halaman dirender dan judulnya cocok byte-per-byte dengan sebelumnya; seluruh asset HTTP 200; `check_deploy.php` = 25 lolos / 4 gagal (semua gagal memang konfigurasi dev); API `jenis=invoice` membuat dokumen dengan `nomor_urut` NULL; `diagnosa.php` 404 tanpa token, 401 dengan token salah, 200 dengan token benar |
| Pelajaran | Agent eksekusi (codex) tetap mengubah 4 judul halaman (urutan kata, em-dash, "Rekap Nomor") dan mematikan pemindaian berkas foto yang sebenarnya tidak butuh kamera — seluruh diff wajib dibaca, bukan hanya "lulus verifikasi" |
| Log Keyword | refactor hosting, ASSET_VERSION, diagnosa.php, php_version guard, _head.php, self-host font, parse_ini_file, set_exception_handler |
| Deploy | BELUM — rangkaian commit lokal, belum dipush |

### Fix #8: QR unduhan Almutqon: modul hitam, logo proporsional, dan mudah dipindai
Gejala: QR yang diunduh dari Dashboard memakai modul hijau dan titik sudut amber, logo Almutqon terlalu besar di tengah, dan hasil visual kurang presisi.

| Aspek | Rincian |
|---|---|
| Tanggal | 2026-09-25 |
| File | `app/Pages/dashboard.php:418-442` |
| Masalah | Opsi styling memakai warna `#166534` dan `#F59E0B`, `imageSize: 0.5`, serta tidak mengatur koreksi error atau quiet zone tambahan |
| Akar | Konfigurasi preview dan download terlalu agresif untuk logo square di tengah QR, sehingga area logo menutup terlalu banyak modul dan paket unduhan tidak punya margin putih yang cukup |
| Fix | Semua modul, corner square, dan corner dot menjadi `#000000`; logo `imageSize: 0.24`; tambah margin 8 px untuk preview dan 32 px untuk PNG 1000×1000; koreksi error `H` |
| Verifikasi | `php -l app/Pages/dashboard.php` dan `node --check` blok JS lulus; browser merender preview SVG 220×220 dengan logo 37×37 px; PNG unduhan 1000×1000 (83.533 byte) berhasil dibuat; warna SVG hanya `#ffffff` dan `#000000`, 0 piksel berwarna di luar area logo; `jsQR` berhasil membaca `https://example.test/index.php?token=test`; event download selesai tanpa failure; console error 0 |
| Pelajaran | Logo branded perlu dibatasi sekitar seperempat area QR, dikelilingi quiet zone, dan dikombinasikan dengan koreksi error tinggi agar hasil tetap rapi sekaligus mudah dipindai |
| Log Keyword | QR unduhan, QR hitam, logo Almutqon, imageSize, errorCorrectionLevel, quiet zone, QRCodeStyling |
| Deploy | BELUM - perubahan lokal belum dipush |

### Fix #7 — QR Code dokumen baru tidak muncul & tombol "Download QR (HD PNG)" mati di Dashboard
Gejala: setelah membuat dokumen baru di Dashboard, panel "QR Code Verifikasi" kosong (tidak ada QR tampil) dan klik "Download QR (HD PNG)" tidak melakukan apa pun (console: `Uncaught SyntaxError: Unexpected token ')'` → `downloadQR is not defined`).

| Aspek | Rincian |
|---|---|
| Tanggal | 2026-09-25 |
| File | `app/Pages/dashboard.php:441` |
| Masalah | Blok `<script>` QR (preview `QRCodeStyling` + fungsi `downloadQR`) ditutup dengan `});` setelah `highResQR.download(...)` padahal `function downloadQR() {` sudah ditutup `}` di baris sebelumnya — satu kurung tutup berlebih tanpa pembuka |
| Akar | Kesalahan penutupan blok fungsi JS; `php -l` tidak menangkapnya (hanya sintaks PHP), sehingga lolos sampai runtime browser. SyntaxError membatalkan **seluruh blok script** — bukan hanya `downloadQR` — sehingga preview QR ikut mati |
| Fix | Ganti `});` menjadi `}` (penutup `function downloadQR`) |
| Verifikasi | Reproduksi headless Chrome sebelum fix: `QRCodeStyling` termuat (`styling=function`), `#qr-code-container` tetap `svg_children=0` (bug terbukti). Setelah fix: `svg_children=1` (QR SVG terender), `errors=[]`. E2E HTTP: login staff → buat dokumen → kode 6-digit + verify_url muncul; `index.php?token=…` menampilkan "Dokumen Sah & Terverifikasi". `php -l` dashboard bersih. Data uji (2 dokumen TEST-QRBUG-*) dihapus |
| Pelajaran | Validasi JS inline hasil render PHP harus lewat `node --check` atau browser sungguhan — `php -l` buta terhadap isi blok `<script>`; satu kurung salah di fungsi manapun membunuh semua JS di blok yang sama |
| Log Keyword | QRCodeStyling, downloadQR, syntax error, unexpected token, qr-code-container, preview kosong |
| Deploy | BELUM — ikut deploy bersama Fix #5/#6 |

### Fix #6 — Lupa & reset password tak terjangkau: entry publik `lupa_password.php`/`reset_password.php` hilang
Gejala: tautan "Lupa kata sandi?" di halaman login staff (`staff.php`) membuka halaman Validasi (landing) — `/lupa_password.php` dan `/reset_password.php` jatuh ke fallback nginx → `public/index.php`. Fitur reset password seluruhnya tak dapat diakses dari browser.

| Aspek | Rincian |
|---|---|
| Tanggal | 2026-09-25 |
| File | Tambah: `public/lupa_password.php`, `public/reset_password.php` |
| Masalah | Halaman `app/Pages/lupa_password.php` & `app/Pages/reset_password.php` lengkap (token reset 30 menit, sekali pakai, anti-enumeration email, `staff_mark_reset_used`) tapi tanpa entry di `public/` — tak ada URL yang memuatnya |
| Akar | Konvensi proyek: tiap Page butuh shim `public/<nama>.php` (`require dirname(__DIR__).'/app/Pages/<nama>.php'`); dua file shim ini terlupa saat fitur dibangun |
| Fix | Tambahkan dua shim publik mengikuti pola file lain (mis. `public/change_password.php`) |
| Verifikasi | `php -l` bersih; `curl /lupa_password.php` → `<title>Lupa Password — AL MUTQON`; `curl /reset_password.php?token=x` → halaman reset; E2E HTTP penuh: lupa → token dibuat (64 hex) → reset password → login dgn pass baru 302 → token bekas ditolak — termasuk pengujian anti-enumeration (email tak dikenal → pesan sama), semuanya lulus (suite `verif_manage_flow` 25/25) |
| Pelajaran | Fitur yang halamannya tak pernah di-route (tanpa shim `public/`) tampak berfungsi di kode namun mati total di produksi — uji link/URL nyata, bukan hanya fungsi PHP |
| Log Keyword | lupa_password, reset_password, route, entry publik, siluman 404, fallback index |
| Deploy | BELUM — dua file baru ikut deploy bersama Fix #5 |

### Fix #5 — Keamanan konfigurasi: fallback secret + IDOR download PDF ber-ID berurut
Gejala: `APP_SECRET_KEY` punya fallback default hardcoded (`local-development-key-change-before-production`) sehingga bila `.env` gagal terbaca, token dokumen (HMAC) menjadi dapat diprediksi; `download.php?id=<int>` publik tanpa token sehingga PDF dokumen `active` bisa diunduh siapa pun dengan menebak/scan ID berurutan.

| Aspek | Rincian |
|---|---|
| Tanggal | 2026-09-24 |
| File | `app/Bootstrap.php:20`, `app/Http/download_document.php`, `app/Pages/index.php:153` |
| Masalah | Secret fallback = kunci prediktabel utk `hash_hmac` token dokumen (dashboard & API create); IDOR download PDF dokumen aktif (struk berisi data pribadi penerima) |
| Akar | Fallback default dibiarkan sebagai "titik nyaman" bootstrap; regulasi sebelumnya (`CATATAN.md:126`) sengaja membuka download tanpa token — kini diperkuat karena PDF berisi data pribadi |
| Fix | (1) fail-fast: `APP_SECRET_KEY` kosong → `error_log` + HTTP 500 + pesan umum (tanpa nilai default); (2) download PHP butuh `&t=<HMAC-SHA256("download:{id}", secret)>` dengan `hash_equals` (403 bila tak sah); `index.php` membangun token otomatis. QR/tautan verifikasi (`index.php?token=…`) TIDAK berubah; hanya link unduh kini bertoken |
| Verifikasi | `php -l` bersih (Bootstrap/download/index); runtime: `APP_SECRET_KEY=` → fail-fast pesan umum tampil; token-verify konsisten (index & download pakai formula sama); HTTP end-to-end tak dijalankan (web server lokal nonaktif) |
| Pelajaran | Jangan pernah memberi nilai default untuk secret produksi; proteksi resource berbasis ID berurut minimal = signed URL HMAC + `hash_equals`, tanpa mengubah skema URL verifikasi |
| Log Keyword | APP_SECRET_KEY, fail-fast, download, signed URL, IDOR, HMAC |
| Deploy | BELUM — ubah ini bagian dari diterapkan; tak mengubah URL QR; link `download.php?id=` lama (tanpa `&t=`) kini 403 (disengaja) |

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
