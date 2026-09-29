# Catatan Lanjutan — AL MUTQON Verification System
/
Dokumen ini adalah handoff pekerjaan. Untuk melanjutkan, gunakan perintah: **`lanjut catatan.md`**.

## Kondisi saat ini

- URL lokal Herd: `http://verif.almutqon.test`
- Web root yang dipakai: `public/`
- Konfigurasi lokal ada di `.env` (diabaikan Git; jangan pernah membagikan atau mengomit isinya).
  - `APP_ENV=development` + `APP_DEBUG=true` untuk lokal. Jika `APP_ENV` kosong, aplikasi menganggap `production` dan menolak `APP_URL` non-HTTPS dengan pesan "Konfigurasi server tidak lengkap".
  - Pesan error konfigurasi kini menyertakan alasan spesifik saat `APP_ENV` bukan `production` (helper `config_fail()` di `app/Bootstrap.php`); di production tetap generik.
- Database lokal: `db_almutqon-verif`.
- Schema awal telah diimpor dari `app/Database/schema.sql`.
- Folder PDF: `storage/uploads/` (di luar web root).
- Kebijakan upload: PDF maks. 10 MB (`MAX_UPLOAD_BYTES`, `app/Bootstrap.php`).
- Kebijakan password server-side: min. 8 karakter, mengandung huruf dan angka (`password_is_valid()`, `app/Bootstrap.php`).

## Struktur proyek

```text
app/
  Bootstrap.php             # environment, session, CSRF, bootstrap DB
  Config/database.php       # PDO database
  Database/schema.sql       # schema tabel awal
  Http/download_document.php# endpoint PDF aktif
  Pages/                    # halaman aplikasi
public/                     # satu-satunya document root dan aset browser
storage/uploads/            # PDF terunggah
```

## Akun lokal yang sudah ada

- Akun admin lokal dan akun direktur lokal sudah dibuat untuk pengujian.
- Jangan menuliskan password pada catatan, source code, atau commit.
- Ubah nama, email, dan password akun lokal melalui manajemen staff sebelum dipakai operasional.

## Yang sudah dikerjakan

1. Memindahkan entry point web ke `public/` dan halaman ke `app/Pages/`.
2. Menambahkan `.env.example`, `.gitignore`, `README.md`, serta schema database awal.
3. Menyatukan konfigurasi sesi aman dan koneksi database di `app/Bootstrap.php`.
4. Menambahkan CSRF token pada seluruh form mutasi.
5. Mengubah hapus dokumen dari GET ke POST.
6. Menyimpan PDF di luar web root dan melayaninya dari `download.php` hanya bila dokumen berstatus `active`.
7. Menambahkan validasi MIME dasar untuk upload PDF.
8. Menambahkan responsivitas untuk desktop, tablet, dan HP.
9. Pada beranda mobile (`< md`), panel hijau tampil di atas; copyright berada di bawah menu/form.
10. **Otorisasi upload PDF diperketat** — hanya pembuat dokumen atau admin/direktur yang boleh mengganti PDF (`dashboard.php`).
11. **Batas ukuran upload PHP tersedia** — `MAX_UPLOAD_BYTES` (10 MB) di `Bootstrap.php`, penanganan `UPLOAD_ERR_*` dengan pesan jelas.
12. **Validasi input manajemen staff server-side** — whitelist role `staff|admin|direktur`, validasi email, panjang NIP/nama/jabatan, kebijakan password (min. 8 karakter + huruf + angka) berlaku juga di reset password dan ganti password sendiri.
13. **Matriks izin eksplisit** — admin tidak dapat membuat/mengubah role maupun data direktur; proteksi “direktur terakhir yang aktif” (tidak bisa diturunkan/dinonaktifkan).
14. **Status dokumen: revoke/active** — UI tombol Revoke/Aktifkan (admin/direktur) di dashboard + badge “Dicabut”.
15. **Verifikasi publik membedakan yang dicabut** — token/kode yang status-nya `revoked` menampilkan “Dokumen ini telah dicabut dan tidak lagi valid” (bukan “tidak ditemukan”).
16. **Audit log dilengkapi** — `staff_add`, `staff_edit`, `staff_toggle`, `staff_reset_password`, `delete_document`, `revoke_document`, `restore_document`, `upload_attempt`, `upload_failed` (selain `create_doc`, `upload_pdf`, `login`, `login_failed`, `change_password`).
17. **Pesan error database mentah tidak lagi tampil** — `PDOException` dicatat ke `error_log`, pengguna melihat pesan umum.
18. **Alokasi `nomor_urut` diproteksi transaksi** (`SELECT ... FOR UPDATE`) untuk path `MAX+1`.
19. **Output JavaScript inline memakai `json_encode`** untuk data PHP (bukan interpolasi string HTML).
20. **Pembersihan kode mati** — field `nomor_urut` + JS `extractNomorUrut()` dihapus (`dashboard.php`; server tidak pernah membaca `$_POST['nomor_urut']`), query upload `SELECT staff_id, no_surat` → `SELECT staff_id`, tombol tab inert di `staff.php` dijadikan tautan fungsional (`index.php`, `index.php#scan`), folder akar `assets/` kosong dihapus (semua referensi aset memakai `public/assets/`).
21. **Viewport + validasi create-doc** — `<meta name="viewport">` ditambahkan di `dashboard.php` dan `manage_staff.php`; form buat dokumen divalidasi server-side (no_surat/perihal/penerima/jabatan_ttd wajib dan ≤ 255 karakter, tanggal `Y-m-d` sekaligus `checkdate`).
22. **Lookup verifikasi saling eksklusif** — `index.php` kini memakai `elseif` (token ATAU kode, tidak dua-duanya) + deep-link tab via hash URL (`#check`/`#scan`/`#staff`), sehingga tombol “Scan QR” dari halaman staff langsung membuka tab scan.
23. **Perbaikan `count_active_direktur()`** — hitung langsung dengan `id NOT IN (...)` (tidak lagi `total - count` yang salah bagi direktur non-aktif), dan handler edit staff mengecek eksistensi target sebelum validasi.
24. **Nama penandatangan dibaca dari database, bukan session** — `dashboard.php` kini mengambil `nama_lengkap`/`jabatan` staff terkini lewat query per halaman. Ini menghilangkan bug "nama lama menempel" bila nama direktur pernah diubah (session lama menyimpan snapshot saat login). Diberlakukan juga refresh session saat staff mengubah datanya sendiri (`manage_staff.php`).
25. **Data placeholder `penandatangan = "Direktur"` diperbaiki** — dokumen lama (id 1, 4, 5) yang menyimpan kata jabatan sebagai nama kini memakai nama direktur dari `staff.nama_lengkap`.
26. **Redesign UI total (Tailwind CDN dihapus)** — kelima halaman (`index.php`, `staff.php`, `dashboard.php`, `manage_staff.php`, `change_password.php`) ditulis ulang memakai CSS kustom di `public/assets/app.css` dengan design tokens (`:root`: forest/gold/paper/ink/ok/err/warn), font **Lora** (serif, judul) + **Inter** (UI), struktur komponen: `.masthead`, `.tab-btn/.tab-active`, `.panel`, `.stat`, `.badge`, `.alert`, `.table`, `.doc`, `.verify-sheet`, `.modal-overlay`, `.kv`, `.dropzone`, `#strength-bar[data-lv]`, split login di `staff.php`, plus `@media print`. Seluruh ID/hook JavaScript lama dipertahankan (tidak ada JS/PHP logic yang berubah).
27. **Beranda `index.php` — tata letak responsif baru** — State A kini `.beranda` dua kolom: pada layar ≥ 920 px brand + tagline ("Sistem Validasi Dokumen Digital" tetap satu kesatuan di header) dan footer **"Al-Mutqon Validation System - Secure HMAC-SHA256"** tampil di **sidebar kiri** yang sticky (brand di atas, footer di bawah sidebar); pada layar sempit brand di atas dan footer menjadi **fixed bottom bar** (footer selalu menempel di bawah). Tombol "Portal Staff" di header beranda dihapus (akses tetap lewat tab Portal Staff / `#staff`).
28. **Scan QR berfungsi di HTTP (non-secure context)** — `index.php`: deteksi `!window.isSecureContext`, jika HTTP tombol kamera disembunyikan dan fallback **“Scan dari Gambar / Galeri”** (`#scan-file` + `scanFromFile()`) ditampilkan; pesan error menjelaskan perlunya HTTPS (`https://verif.almutqon.test` tersedia). Kamera tetap aktif bila halaman dibuka via HTTPS.
29. **Tata letak dashboard responsif diperbaiki** — inline `grid-template-columns: 1fr 1.7fr` (yang menimpa media query mobile) diganti class `.dash-grid` (`public/assets/app.css`); `< 760px` riwayat dokumen otomatis menumpuk di bawah form “Buat Dokumen Baru”, dan `panel--sticky` dinonaktifkan di layar kecil.
30. **Migrasi & kompatibilitas PDF lama** — helper `resolve_pdf_path()` di `app/Bootstrap.php` mencari PDF di `storage/uploads/` (skema baru) lalu lokasi legacy (`public/uploads/`, `public/`, `storage/`) dengan guard anti traversal; `app/Http/download_document.php` memakainya sebagai fallback. Script CLI `php app/Support/migrate_pdf_paths.php [--dry-run]` menyalin file legacy ke `storage/uploads/cert_*.pdf` dan mengoreksi `pdf_path`, plus melaporkan file yang tidak ditemukan.
31. **Rate limit upload** — konstanta `UPLOAD_RATE_WINDOW_SECONDS` (600 dtk) & `MAX_UPLOAD_ATTEMPTS` (15) + helper `rate_limit_ok()` di `app/Bootstrap.php`; setiap POST `upload_pdf` dicatat `upload_attempt` dan bila melebihi 15×/10 mnt per IP ditolak; kegagalan upload dicatat `upload_failed` untuk audit.
32. **PHP `upload_max_filesize`/`post_max_size` dinaikkan** — Herd PHP 8.4 (`~/Library/Application Support/Herd/config/php/84/php.ini`) dari 2M/2M menjadi **12M/14M** (batas aplikasi `MAX_UPLOAD_BYTES` tetap 10 MB; memberi jeda di atasnya untuk overhead multipart). Ditambahkan juga `public/.user.ini` (12M/14M) sebagai cadangan untuk hosting yang menghormati `.user.ini` (Herd mengabaikannya).
33. **Beranda ditata ulang meniru pola `verif.mc.ac.id`** — State A kini split-screen (kiri: panel brand `--forest-dark` + aksen gold berisi brand dan footer "Al-Mutqon Validation System - Secure HMAC-SHA256"; kanan: area form terpusat) dengan area portal **`max-w:460px` konsisten di semua lebar** agar ukuran tak melompat saat resize. Segmented tab ala IMC via class baru `.landing__nav/.landing__tab/.landing__panel/.landing__icon`; `<760px` satu kolom, `≥920px` dua kolom (sidebar sticky `h:100vh` — brand teratas, footer menempel bawah via `margin-top:auto`; kanan scroll mandiri). State B (sertifikat) & C (error) tidak diubah. Penyesuaian lanjutan: deskripsi hero dihapus (footer **dikembalikan** atas permintaan user); `.landing__nav` **sticky** (`top:12px`) dan `.landing__panel` **min-height seragam** (620px desktop / 560px ≤760 / 500px ≤480) supaya ketiga tab tampak sama tinggi. **Tab Scan QR = persis ala IMC**: kamera **auto-start** saat tab dibuka (`Html5QrcodeScanner`, tanpa tombol Start/Stop), bingkai `#reader` dalam `.scan-frame` (border 2px + `--forest-soft` + inset shadow), QR tak dikenal → SweetAlert "QR Tidak Dikenali", teks 6-digit diteruskan ke `?code=`, token 64-char ke `?token=`. Fallback "Scan dari Gambar / Galeri" tetap dipakai bila `!isSecureContext` (HTTP). SweetAlert2 CDN ditambahkan di head. ID `btn-start-scan`/`btn-stop-scan` & class `.scan-window` dihapus.
34. **Adaptasi visual portal via `public/assets/portal.css` (struktur tidak diubah)** — setelah percobaan redesign raksasa dibatalkan, visual beranda diadaptasi ke model referensi (white bg, navy `#12294A`, muted blue-gray `#5F6F82`, border soft `#DFE7F0`, rounded besar, subtle shadow) **tanpa mengubah struktur/fungsi/routing/logic**: body beranda jadi putih; sidebar kiri tetap di grid (palet jadi netral: brand navy + footer muted); `.landing__nav` dijadikan segmented grid 3 kolom sama besar (bg `--pv-soft`, tab aktif kartu putih + navy); `index.php` hanya mendapat *lightweight* perubahan markup — penekanan heading "Cek Dokumen"/"Scan QR Code"/"Selamat Datang.", label & placeholder form ("ID Pengguna", "Masukkan ID Anda", "Verifikasi Dokumen", "Masuk ke Sistem"), dan **empty state scanner** (.scan-frame > .scan-inner + `#scan-empty` phone/QR + "Request Camera Permissions" + "Scan an Image File" underline + ikon info "i" kanan atas + garis pindai `.scan-line` saat kamera aktif). JS scanner lama dipertahankan (auto-start `Html5QrcodeScanner`, fallback HTTP `!isSecureContext`, `scanFromFile`, `onScanSuccess` → `?code=`/`?token=`, SweetAlert). CSS ini hanya dimuat di `index.php`; `app.css` tidak disentuh. Kelemahan keamanan/kualitas lain sewaktu-waktu tetap perlu direview manual.
35. **Penyelarasan tampilan ke model almutqon (17 Sep, `assets/*?v=20260917a`)** — hanya CSS + font, tanpa ubah struktur/logic: `:root` `app.css` dipetakan ke token `almutqon/css/base.css` (forest `#166534`/`#14532D`/`#DCFCE7`, accent amber `#F59E0B`/`#FEF3C7`, surface `#F8FAFC`, text `#0F172A`/`#475569`, border `#E2E8F0`, muted `#94A3B8`, radius 12, `--font-display` = Plus Jakarta Sans + alias `--primary/--accent/--footer-bg`); permukaan gelap (masthead/sidebar/login brand/verify-head) → `#0B1120`; `.btn-primary` gaya almutqon (shadow forest + hover lift); tabel/dokumen/legal/verify-back krem → putih/surface; `portal.css` (`--pv-input` putih, shadow/focus/scanline forest-rgb); font `Lora` → `Plus Jakarta Sans` di 5 halaman. Terverifikasi: `php -l` 6 file lolos, HTTP 200 (`/`, `staff.php`, 2 CSS), computed `--forest=#166534` + tombol `rgb(22,101,52)` + heading Plus Jakarta Sans, 0 console error.
36. **Tautan "Kembali ke Dashboard/Beranda" bukan tombol lagi (17 Sep)** — di `app/Pages/index.php:205` (footer lembar verifikasi State B) class `btn btn-light` diganti `verify-back__link` (teks muted + panah, tanpa bg/border, hover hijau + geser); rule `.verify-back .btn` di `app.css` diganti style tautan; override mati `.verify-back .btn-light` di `portal.css` dihapus. Terverifikasi State B: bg transparan + border none, menyatu dengan section `#F8FAFC`.
37. **Rapikan blok pengesahan (17 Sep, `?v=20260917c`)** — `.sig-block` jadi kartu berisi (bg surface, border, radius 14, avatar 52px + ring `forest-soft`, nama 700, jabatan sebagai pill `forest-soft`); bonus: judul `.verify-head__title` dikembalikan putih (override portal hijau-di-atas-gelap). Lebar `.verify-sheet` **dikembalikan ke semula 760px** (percobaan kunci 680px dibatalkan atas permintaan user).
38. **Frame fluid + terpusat di semua layar (17 Sep, `?v=20260917d`)** — tanpa kunci mati: `.verify-sheet` fluid 100% hingga cap 760 (semula) + `margin-block:auto` (terpusat horizontal & vertikal, aman scroll saat konten tinggi); `.verify-wrap` padding `clamp(20px,4vw,48px)`; `.verify-body` padding `clamp(20px,4vw,30px)`. Terverifikasi: @1440 sheet 760 terpusat, @390 sheet 358 terpusat, tanpa overflow; `php -l` + HTTP 200.
39. **Hilangkan efek bersinar ikon/button (17 Sep, `?v=20260917f`)** — `.btn-primary` flat tanpa glow (hover: gelap + terangkat `-2px`, tanpa shadow; transisi box-shadow dibuang); `.tab-active`/`.landing__tab.tab-active` tanpa shadow; ring avatar `.sig-block__av` jadi border polos; garis pindai scanner tanpa glow (animasi gerak tetap). Disengaja dipertahankan: ring fokus input (aksesibilitas) + shadow netral kartu/panel/modal. Terverifikasi hover: `translateY(-2px)` aktif + `box-shadow:none`; HTTP 200.
40. **API internal v1 untuk integrasi ALMUTQON (18 Sep, Fase 0+1, lokal)** — endpoint `POST /api/v1/dokumen.php` (tipis → `app/Http/api_v1_dokumen.php`); auth `Bearer VERIF_API_TOKEN` (401 bila salah; GET → 405); aksi `create|revoke|restore` + envelope `{success,action,data}`; `create` idempoten by `no_surat`, validasi = dashboard, kode 6-digit retry anti-bentrok (perbaiki bug dashboard), PDF opsional (aturan upload dashboard, nama `cert_*`); `revoke/restore` by `token`/`no_surat` + `activity_log`; pencatat = service-account `SYSTEM-ALMUTQON` (staff id 8, password acak tak bisa login); `.env` + `VERIF_API_TOKEN`/`VERIF_API_STAFF_NIP` (komentar gaya `;` — `#` merusak `parse_ini_file`, sempat 500-style fallback lalu diperbaiki), `.env.example` didokumentasi. Terverifikasi 17 cek curl: 401/405/400/404 tepat; create → publik "Sah" → revoke → "dicabut" + download 404 → restore; PDF tersimpan & terunduh sah; kiriman ulang idempoten; txt ditolak. Data uji bersih (dokumen=1, file asli utuh).
41. **Impor ID invoice ALMUTQON (18 Sep, data asli)** — via `tools/impor-verif.php` sisi almutqon: 2 invoice Lunas terdaftar sebagai dokumen (`INV-20260918-0958` via Lunas UI + `INV-20260917-3848` via impor; keduanya `active` + PDF + penandatangan). DB kini: staff 3 (2 asli + service-account), dokumen 3 (1 asli + 2 invoice). Ini DATA ASLI, jangan dihapus sebagai "data uji". Aturan/format ID terpadu dirancang nanti.
42. **Sinkron ID pengajar/staf (18 Sep)** — migrasi `staff.almutqon_user_id` + index (`app/Support/migrate_tautan_staf.php`, idempoten; `schema.sql` ikut untuk instalasi baru); fondasi bersama `app/Http/api_v1_boot.php` (refaktor `api_v1_dokumen.php` tanpa ubah perilaku — 17 cek curl Fase 1 tetap hijau); endpoint baru `POST /api/v1/staf.php` (`upsert` by NIP + `toggle`): tolak `SYSTEM-*`, tolak role `direktur` (403, hanya dashboard), tolak ubah baris direktur, lindungi direktur-terakhir, `hapus_tautan` eksplisit, audit `staff_add/edit/toggle`; password baris baru acak. Hasil pairing: staff.id=2 NIP → `PGJ-1520` (taut users.id=1), staff.id=9 baru NIP `PGJ-2450` (taut users.id=46, role staff). DB kini staff 4. Data uji (UJI-STAF-1 + auditnya) bersih.
43. **Pemisah dokumen vs invoice + rekap penomoran (18 Sep)** — migrasi `app/Support/migrate_jenis_dokumen.php` (idempoten; `schema.sql` ikut): kolom `jenis` ENUM + index, backfill `INV-%` → invoice, `nomor_urut` NULL-able + dikosongkan untuk invoice. Aturan: penomoran (alokasi, nomor terakhir, total, rekap) HANYA `jenis='dokumen'`; invoice tanpa nomor. Dashboard: create selalu `dokumen` + alokasi scoped, riwayat tetap semua (invoice di bawah, badge "Invoice" ganti `#000`), statistik 4 kartu (Total/Nomor Terakhir/Invoice/Dibuat Anda, `.grid4` baru) + menu "Rekap Nomor". API create terima `jenis` (default dokumen, 400 bila ngawur) + kembalikan di respons. Halaman baru `rekap_nomor.php` (guard login): kartu Nomor Terakhir/Terpakai/Lompat + tabel bernomor 1..max dengan baris "Belum terpakai" untuk lompatan. Temuan saat kerja: 1 rujukan yatim (`INV-20260917-3848`, dokumen hilang) → tambah mode `--perbaiki` di `tools/impor-verif.php` + pulihkan. Terverifikasi: guest rekap/dashboard 302; invoice → NULL + tak ubah statistik; render sintetis dashboard (2/#008/2 invoice) & rekap (7 lompatan) tepat; cache-buster `?v=20260917g`; data uji bersih (dokumen=3: 1 surat + 2 invoice asli).
44. **Halaman terpisah Dokumen vs Invoice (18 Sep)** — dashboard "Riwayat Dokumen" kini HANYA surat/sertifikat (`WHERE jenis='dokumen'`; stat "Dibuat Anda" ikut scoped); halaman baru `data_invoice.php` (+ `public/` wrapper, guard login semua role): 3 stat (Total/Aktif/Dicabut) + riwayat invoice saja + menu silang Dashboard/Rekap. Aksi mutasi bersama di `app/Http/dokumen_actions.php` (upload/hapus/revoke + PRG error via sesi; handler dashboard yang teruji TIDAK diubah); partial bersama `_doc_item.php` (kartu) + `_upload_modal.php` (modal+JS). Staff biasa: lihat + upload miliknya, tanpa tombol Revoke/Hapus. Terverifikasi e2e 16/16 (guest 302, pisah daftar tepat, revoke/restore/upload/hapus via halaman invoice, menu silang); regresi almutqon 94/94; file & DB bersih.
45. **Hamburger menu layar sempit (18 Sep, `?v=20260917h`)** — breakpoint 900px: `.masthead__menu` (nav tombol) + `.nav-toggle` (3 garis, `aria-expanded`/`aria-controls`) di 5 halaman bermasthead; chip identitas tetap terlihat; menu jadi dropdown kartu (ghost→kotak terang agar terbaca, danger tetap merah); tutup via klik-luar/Esc; JS bersama `assets/nav.js` (tanpa framework). Desktop tak berubah. Terverifikasi @390 (toggle tampil, menu buka/tutup, 4 item peran staff, tanpa overflow) + @1280 (toggle sembunyi, menu inline) + 3 halaman lain render toggle; 0 console error; akun uji bersih.
46. **Menu akun + avatar + tema gelap (18 Sep, `?v=20260917k`)** — migrasi `staff.avatar`/`staff.theme` (`migrate_avatar_theme.php`, idempoten; `schema.sql` ikut); avatar 42px + lencana gerigi (SVG, tanpa tulisan) di semua masthead; klik → dropdown nama, jabatan, Ganti Avatar (modal + preview + hapus, JPG/PNG/WebP ≤1MB via `api/avatar.php` + toast + redirect balik), switch Tema Gelap (JSON `api/theme.php`, terapan instan), Ganti Password, Keluar. Tema per akun (`data-theme`, publik tetap terang) + palet dark penuh di `app.css`. Temuan: nama putih-di-putih (warisi header → fix `color`), file >batas server 413 di level server (di luar kode). Terverifikasi: upload/hapus/invalid/oversize/tanpa-CSRF/guest-401, tema tersimpan + persisten, 5 halaman terang+gelap via screenshot; akun & file uji bersih.
47. **Avatar polos tanpa gerigi (18 Sep, `?v=20260917l`)** — lencana roda gerigi + border + bayangan dibuang; tombol avatar = lingkaran 42px polos (foto/inisial penuh), tetap membuka menu akun. Terverifikasi via screenshot + computed (`box-shadow:none`, border 0); akun uji bersih.
48. **Header menempel saat scroll (18 Sep, `?v=20260917m`)** — `.masthead` `position:sticky;top:0` (+ bayangan halus, z-index di bawah modal/toast; dropdown mobile tetap berjangkar benar). Terverifikasi @390 & @1280 menempel di `top:0`, dropdown terbuka di bawah header, tanpa overflow; akun uji bersih.
49. **Menu profil baru + halaman Profil Saya + tema 3-pilihan (18 Sep, `?v=20260917n`)** — dropdown: header (avatar/nama/jabatan tetap) → Profil Saya (ikon user) → Manajemen Staff khusus admin/direktur (ikon users) → garis → Tema: Terang/Bulan/Sistem (ikon outline sun/moon/monitor) → garis → Keluar merah; ikon outline 1.8px rounded seragam, tanpa solid. Ganti avatar pindah ke halaman baru `profil.php` (info NIP/email/role + upload/hapus + seg tema + link password). Tema `system` ikuti OS (ENUM + resolver head anti-kedip + listener, tersimpan di DB). Migrasi `migrate_theme_system.php` (idempoten; `schema.sql` ikut). Temuan: init seg sebelum DOM profil → tunda `DOMContentLoaded`. Terverifikasi: guest profil 302, seg sinkron 2 lokasi, system/dark tersimpan, upload profil + toast, guest theme 401, admin-lihat-manajemen vs staff-tidak; akun & file uji bersih.
50. **Rapikan menu profil (18 Sep, `?v=20260917o`)** — baris Tema: label + 3 tombol ikon saja sejajar kanan (tanpa teks, tooltip + aria tetap); semua item seragam 44px; garis pemisah inset (`6px 16px`); CSS ganda `.account-menu__theme/label` dibersihkan. Terverifikasi screenshot + toggle ikon tetap simpan; akun uji bersih.
51. **Bingkai ikon tema dikembalikan (18 Sep, `?v=20260917p`)** — segmen berbingkai (border + latar + padding seperti semula), ikon tetap tanpa teks; opsi aktif kartu + bayangan. Terverifikasi screenshot; akun uji bersih.
52. **Ikon gaya referensi indowebsite (18 Sep)** — seluruh ikon menu profil + seg tema diganti path Lucide persis referensi (`fill=none`, `stroke-width=2`, round caps/joins, 16px): user, users, sun (r=5), moon, monitor, logout. Tanpa perubahan struktur/fungsi. Terverifikasi screenshot; akun uji bersih.

53. **Pemadanan kontrol tema lintas proyek (18 Sep, cache-buster `v=20260918a`)** — satu partial tema 3-opsi (Terang/Gelap/Sistem) dipakai di:
    - verif.almutqon: `_theme_switch.php` → `_account_menu.php` (variasi `--icons`) + `profil.php` (variasi `--big`);
    - almutqon: `layouts/theme-switch.php` → `pages/akun/pengaturan.php` (variasi `--big`).
    Markup, kelas CSS, perilaku JS (POST `api/theme.php`, sinkron 2 lokasi, listener `prefers-color-scheme`, `data-csrf`/`data-endpoint`) **100% identik** keduanya. Almutqon: ENUM `users.theme` ditambah `'system'` (`schema-theme.php`), `api/theme.php` terima `'system'`, `layouts/header.php` resolver sistem via script awal, `js/app.js` `initThemeSettings()` dihapus. Cache-buster dinaikkan ke `20260918a` (verif `assets/*`, almutqon `css/*`).
54. **Pembersihan backdoor & duplikasi masthead (18 Sep)** — dihapus `app/Pages/_test_session.php` (backdoor set sesi direktur). Diekstrak `_masthead.php` (parameter `$pageTitle`, `$navLinks`, `$isAdminOrDirektur`) dan dipakai di 6 halaman (`dashboard.php`, `data_invoice.php`, `rekap_nomor.php`, `manage_staff.php`, `change_password.php`, `profil.php`), hapus logout duplikat di nav, `closeModal`/`window.onclick` dipindah ke `_modal_shared.php` (dedupe `_upload_modal.php` & `manage_staff.php`).
55. **Model tombol diselaraskan ke `.admin__btn` almutqon (18 Sep)** — base `.btn` padding 9×16px / 13px / radius 10px (sebelum 11×20/14px/12px), hover `opacity:.88; transform:translateY(-1px)` di base (sebelum hanya `.btn-primary`), disabled `#9CA3AF` (sebelum `#C9C2B4`), `.btn-sm` 6×11px/12px/8px. Dihapus `.btn-blue`; `_upload_modal.php` & `_doc_item.php` pakai `.btn-primary`. Terverifikasi `php -l` semua file.
56. **Ganti warna lama `#143528`/`#B28A3C` dengan token** — QR code di `dashboard.php` & SweetAlert `index.php` pakai `--forest` `#166534` & `--gold` `#F59E0B`.
57. **Perbaikan `manage_staff.php` die(alert) → redirect+flash (18 Sep)** — akses non-admin/divdirektur now `header('Location: dashboard.php')` + `$_SESSION['manage_msg']`, konsisten dengan pola flash lain.
58. **Refactor `dashboard.php` → `dokumen_actions.php` (18 Sep)** — handler `upload_pdf` dipindah ke shared `app/Http/dokumen_actions.php::dokumen_aksi_upload()`; dashboard memanggil fungsi tersebut.
59. **Rate limit API v1 (18 Sep)** — ditambah `MAX_API_ATTEMPTS=60` / `API_RATE_WINDOW_SECONDS=60` + pengecekan `rate_limit_ok($pdo, 'api_v1_request', ...)` di `app/Http/api_v1_boot.php` (setelah auth Bearer, sebelum service-account). 429 bila melebihi.
60. **Fitur Lupa Password staff (18 Sep)** — migrasi `app/Support/migrate_password_resets.php`; helper `staff_store_reset_token`/`find_valid_reset`/`mark_reset_used` di `Bootstrap.php`; halaman `lupa_password.php` (request) & `reset_password.php` (form + token validasi 30 menit); link "Lupa kata sandi?" di `staff.php`; stub `send_mail()` di `Bootstrap.php` (log-only). Terverifikasi migrasi & `php -l` semua file.
48. **Menu profil baru + halaman Profil Saya + tema 3-pilihan (18 Sep, `?v=20260917n`)** — dropdown: header (avatar/nama/jabatan tetap) → Profil Saya (ikon user) → Manajemen Staff khusus admin/direktur (ikon users) → garis → Tema: Terang/Bulan/Sistem (ikon outline sun/moon/monitor) → garis → Keluar merah; ikon outline 1.8px rounded seragam, tanpa solid. Ganti avatar pindah ke halaman baru `profil.php` (info NIP/email/role + upload/hapus + seg tema + link password). Tema `system` ikuti OS (ENUM + resolver head anti-kedip + listener, tersimpan di DB). Migrasi `migrate_theme_system.php` (idempoten; `schema.sql` ikut). Temuan: init seg sebelum DOM profil → tunda `DOMContentLoaded`. Terverifikasi: guest profil 302, seg sinkron 2 lokasi, system/dark tersimpan, upload profil + toast, guest theme 401, admin-lihat-manajemen vs staff-tidak; akun & file uji bersih.

## Pengujian yang sudah dilakukan

- Semua file PHP lolos `php -l`.
- `http://verif.almutqon.test/` memberi HTTP 200.
- Aset `assets/1.png` memberi HTTP 200.
- Dashboard tanpa sesi mengarahkan ke login (HTTP 302).
- Uji end-to-end (akun uji sementara, dibersihkan setelahnya):
  - Login staff/admin/direktur HTTP 302 → dashboard 200.
  - Buat dokumen → token aktif terverifikasi publik.
  - Staff mengunggah PDF dokumen miliknya sendiri → sukses.
  - Staff mencoba mengunggah PDF dokumen milik staf lain → ditolak.
  - Staff mencoba revoke → tidak berdampak (status tetap `active`).
  - Admin mengedit direktur → ditolak; admin membuat role direktur → ditolak; password lemah saat tambah staff → ditolak.
  - Direktur revoke → publik menampilkan “dicabut”, `download.php` HTTP 404; restore → terverifikasi lagi.
- Database setelah uji dikembalikan ke kondisi semula (2 staff, 1 dokumen), data uji dihapus.
- Pengujian lanjutan (sesi analisis/besihkan): `php -l` semua file lolos; create-doc dengan tanggal `2026-02-31` → “Tanggal dokumen tidak valid.”; `no_surat` 300 karakter → pesan batas 255; create-doc valid → berhasil; `index.php#scan` → HTTP 200. DB kembali dikembalikan ke 2 staff, 1 dokumen.
- Pengujian setelah redesign UI (hari ini): `php -l` semua file lolos; tidak ada sisa class Tailwind (grep `tailwind|bg-brand|text-gray` di `app/Pages` → kosong); seluruh ID/hook JS terverifikasi masih ada; beranda (State A) HTTP 200 dengan `.beranda__side/.beranda__main` aktif; verifikasi token valid (State B) menampilkan sertifikat + nama `Ranupane Rimba Argana`; token/kode invalid (State C) menampilkan “Dokumen Tidak Tervalidasi”; `index.php?code=15E736` → State B; `dashboard.php`/`manage_staff.php`/`change_password.php` tanpa sesi → HTTP 302 ke `staff.php`; render halaman autentikasi diuji via sesi sintetik (file tes sementara dihapus setelahnya); `download.php?id=1` → 200, `id=999` → 404 (tetap terbuka sesuai keputusan desain). DB tidak diubah.
- Pengujian visual manual (browser) belum dilakukan untuk tata letak baru `.beranda`; perlu dicek langsung di 320/375/768/1024/desktop. Layout lived on `index.php` setelah perubahan terakhir (sidebar kiri + footer) — akan direview besok sebelum lanjut.
- Pengujian setelah pengerjaan item prioritas tinggi (hari ini): `php -l` semua file lolos; nilai efektif web `upload_max_filesize=12M`/`post_max_size=14M` (Herd PHP 8.4 setelah `herd restart`); `resolve_pdf_path()` diuji 6 kasus (skema baru, legacy di `public/`, `uploads/...`, traversal aman dalam `public/` → resolved, traversal keluar `../../etc/passwd` → null, file tak ada → null); migrasi diuji dengan dokumen temp ber-pdf_path `uploads/legacy_relative.pdf` → tersalin ke `storage/uploads/cert_*.pdf` + DB ter-update, dokumen temp & file uji dihapus (DB kembali 1 dokumen, tanpa baris tes tersisa); `download.php?id=4` → 200, `id=999` → 404; render `dashboard.php` via sesi sintetik → 200 memuat form + riwayat; POST upload tanpa file → pesan “Silakan pilih file PDF terlebih dahulu.” dan tercatat `upload_attempt` + `upload_failed`; `rate_limit_ok()` lulus di 14 percobaan (boleh) & 15 (ditolak). Baris uji `upload_attempt`/`upload_failed` dibersihkan.
- Adaptasi visual portal (hari ini): `php -l` semua file lolos; `index.php` HTTP 200 memuat `portal.css` (terkirim sebagai `text/css`), `.landing__nav` grid 3 kolom, `.landing__panel--staff`, empty state scanner (`scan-empty`/`scan-info`/`scan-line`), heading & placeholder baru; `?code=91957B` → State B "Sertifikat Validasi Digital"; kode invalid → State C; `#scan` → 200; form staff (CSRF, `action="staff.php"`) utuh. DB tidak diubah; cek visual browser (320/375/768/1024/desktop) menunggu.

## Keputusan desain

- **Download PDF sengaja terbuka** — `download.php?id=<int>` tanpa autentikasi/token agar siapa pun bisa melihat dan mengunduh PDF dokumen aktfif. Ini disengaja, bukan bug; jangan diproteksi tanpa persetujuan pemilik proyek.

## Bug dan potensi masalah yang masih ada

### Prioritas tinggi

- [x] **PDF lama tidak dimigrasikan.** Selesai: `app/Support/migrate_pdf_paths.php [--dry-run]` menyalin file legacy ke `storage/uploads/`, helper `resolve_pdf_path()` memberi fallback kompatibilitas di `download_document.php` (guard anti-traversal), nilai `pdf_path` yang sudah benar dikoreksi otomatis.
- [x] **Belum ada rate limit upload** terpisah. Selesai: `upload_attempt` 15×/10 mnt per IP (`MAX_UPLOAD_ATTEMPTS` + `UPLOAD_RATE_WINDOW_SECONDS` di `Bootstrap.php`), kegagalan dicatat `upload_failed`.
- [x] **PHP `upload_max_filesize`/`post_max_size`** di sisi server belum diverifikasi. Selesai: nilai efektif web kini **12M/14M** (Herd PHP 8.4 `php.ini`; `public/.user.ini` cadangan untuk hosting yang mendukungnya). Batas aplikasi `MAX_UPLOAD_BYTES` tetap 10 MB.

### Prioritas menengah

- [ ] **Logout via GET** (`staff.php?logout=1`) berpotensi dipicu dari sumber lain (CSRF logout). Idealnya diubah ke POST + CSRF.
- [ ] **`code_6digit` acak 6 digit berpotensi bentrok** — kolomnya UNIQUE; jika INSERT gagal, dokumen tidak jadi dibuat. Bisa di-loop generate ulang saat `SQLSTATE[23000]` pada kolom itu. (File `download.php` ikut memakai kode ini.)
- [ ] **Statistik dashboard `$stats['total']` ikut menghitung dokumen `revoked`**; tampilkan angka berdasar status bila diinginkan.
- [ ] **Belum ada rate limit terpisah** untuk perubahan profil, reset password, status staff, dan penghapusan dokumen (audit lengkap sudah ada).
- [ ] **`nomor_urut` berpotensi bentrok** bila dua dokumen dimulai dengan angka prefix yang sama (mis. `8/...` dan `8A/...`). Alokasi `MAX+1` sudah transaksi-atomic; untuk dokumen berformat non-angleangan pertimbangkan constraint unik penuh atau penghitung tersentral.
- [ ] **Verifikasi publik belum memberi peringatan visual** bahwa dokumen memiliki `status` selain `active` (sudah dikenali, belum ada desain halaman khusus untuk status mis. `expired/draft`).
- [ ] **Perangkat fisik belum diuji** untuk Scan QR (izin kamera, stop kamera, fallback).

### Responsivitas dan UX

- [ ] Uji visual nyata pada lebar 320 px, 375 px, 768 px, 1024 px, dan desktop besar — khususnya tata letak `.beranda` baru (split-screen, sidebar kiri ≥920 px, footer fixed bottom <920 px, area portal max-w 460 px). Struktur sudah diuji HTTP 200 (`index.php`, `index.php#scan`, state B token, state C error) dan seluruh ID/hook JS terverifikasi ada; cek visual browser masih menunggu.
- [ ] Pastikan tab “Scan QR” diuji pada perangkat fisik: izin kamera, stop kamera, dan fallback saat kamera tidak tersedia.
- [ ] Tabel staff sengaja memakai scroll horizontal di layar kecil; pertimbangkan kartu per staff jika ingin UX mobile yang lebih nyaman.
- [ ] Optimalkan logo `public/assets/1.png` karena ukurannya sekitar 3 MB.

### Operasional

- [ ] Ganti secret development/default dan pastikan `.env` memakai `APP_URL` produksi saat deploy.
- [ ] Gunakan akun DB khusus aplikasi, bukan `root`, di produksi.
- [ ] Pastikan HTTPS aktif di produksi agar cookie `Secure` aktif.
- [ ] Tambahkan backup database dan backup folder `storage/uploads/`.
- [ ] Tambahkan repository Git bila proyek akan dikelola jangka panjang; saat audit awal folder ini belum terdeteksi sebagai repository Git.

61. **Admin shell kanan (sidebar kanan) untuk halaman staff (19 Sep, `v=20260919a`)** — desain mirip `almutqon/layouts/admin-header.php` (sidebar kanan, brand + nav Material Symbols + footer user, topbar dengan hamburger mobile), diterapkan ke 6 halaman staff:
    - `dashboard.php`, `data_invoice.php`, `rekap_nomor.php`, `manage_staff.php`, `change_password.php`, `profil.php`.
    - Shared partials: `_admin_shell.php` (buka shell + sidebar + topbar) + `_admin_shell_end.php` (tutup shell + JS sidebar toggle + `nav.js`).
    - Navigasi sidebar: Dashboard, Dokumen, Data Invoice, Rekap Nomor, Manajemen Staff (admin/direktur), Ganti Password, Keluar.
    - CSS ditambah di `assets/app.css`: `.admin`, `.admin--right`, `.admin__sidebar`, `.admin__nav`, `.admin__topbar`, `.admin__hamburger`, `.admin__overlay`, responsive < 900px (hamburger + slide-in), responsive < 760px.
    - Hapus `_masthead.php` duplikat (masih dipakai `index.php` halaman publik). Cache-buster `20260919a` di `assets/app.css` + semua halaman staff.

62. **Rollback ke tampilan masthead (19 Sep, `v=20260919b`)** — per permintaan user, dikembalikan ke layout masthead original (`_masthead.php`) untuk 6 halaman staff:
    - `dashboard.php`, `data_invoice.php`, `rekap_nomor.php`, `manage_staff.php`, `change_password.php`, `profil.php`.
    - Menggunakan `_masthead.php` + `_account_menu.php` + `nav.js` (hamburger ≤900px).
    - QR Code Styling CDN ditambahkan ke dashboard via `$extraHead`.
    - Material Symbols Rounded font ditambahkan ke `_masthead.php` untuk ikon menu akun.
    - Partial admin shell (`_admin_shell.php`, `_admin_shell_end.php`) dipertahankan untuk referensi masa depan.
    - Cache-buster `20260919b` di `assets/app.css` + semua halaman staff.

63. **Pemulihan struktur HTML halaman staff yang hilang saat refactor masthead (18 Sep)** — item 54 (ekstraksi `_masthead.php`) tidak disengaja menghilangkan kerangka dokumen pada 3 halaman:
    - `dashboard.php`: `<!DOCTYPE html>`/`<html data-theme>`/`<head>` (theme resolver, charset, viewport, fonts, `app.css?v=`, `$extraHead` QR styling) + `<body>` + pembungkus `<main class="page page--wide">` hilang → halaman render tanpa CSS (quirks mode).
    - `rekap_nomor.php`: kehilangan seluruh shell: `<html>`/`<head>`/`<body>`, include `_masthead.php`, dan `<main>` pembuka (masih ada `</main>` yatim). Dibangun ulang mengikuti pola `data_invoice.php`.
    - `manage_staff.php` & `change_password.php`: hanya kurang `<!DOCTYPE html>` (memasuki quirks mode).
    Terverifikasi: `php -l` semua file lolos; render sesi sintetik (direktur) kelima halaman utuh (masthead + `<main>` + isi); guest 302 (dashboard/rekap/manage_staff/profil); index/staff 200; @390 & @1440 tanpa overflow horizontal; 0 console error. File uji sesi dihapus setelah verifikasi.

64. **Data Invoice direduksi: upload PDF & revoke dihapus (18 Sep)** — atas permintaan user, halaman `data_invoice.php` kini hanya **Lihat** (link publik) dan **Hapus** (admin/direktur):
    - Handler POST `upload_pdf` & `revoke_document`/`restore_document` dihapus; include `_upload_modal.php` dibuang; branch `?msg=revoked/restored` dihapus (tidak mungkin terpicu lagi).
    - `_doc_item.php` (partial bersama dashboard) diberi pengaman flag: tombol "Upload PDF" disembunyikan bila `$noUploadPdf`; tombol Revoke/Aktifkan disembunyikan bila `$noStatusAction`. `data_invoice.php` menyetel kedua flag; dashboard tidak → fitur upload/revoke tetap penuh di Dashboard.
    - Statistik "Dicabut" pada invoice tetap dipertahankan (informatif; invoice lama yang pernah dicabut tetap tampil status badge-nya).
    Terverifikasi: `php -l` lolos; render sesi sintetik: kartu invoice hanya Lihat+Hapus, kartu dashboard tetap Lihat+Upload PDF+Revoke+Hapus. File uji sesi dihapus.

65. **Data Invoice: statistik "Aktif" & "Dicabut" dihapus (18 Sep)** — grid 3 statistik pada `data_invoice.php` disederhanakan menjadi satu kartu **Total Invoice**. Query statistik juga dipangkas dari `SUM(status='active')`/`SUM(status<>'active')` menjadi `COUNT(*)` saja.
    Terverifikasi: `php -l` lolos; render sesi sintetik menampilkan satu kartu Total Invoice; @390px tidak ada overflow horizontal. File uji sesi dihapus.

## Urutan kerja yang disarankan besok

1. Review visual tata letak `.beranda` baru (sidebar kiri + footer bottom) pada ukuran 320/375/768/1024/desktop besar; sesuaikan spacer/padding bila perlu.
2. Uji alur end-to-end lanjutan: revoke → download PDF → pastikan tidak lagi tersedia, serta uji last-direktur lockout.
3. Uji Scan QR pada perangkat fisik (izin kamera, stop, fallback gambar saat HTTP/HTTPS).
4. Siapkan konfigurasi operasional produksi: akun DB khusus, secret baru, HTTPS, backup.

## Catatan aman

- Jangan menghapus `storage/uploads/` atau tabel database tanpa backup.
- Jangan mengirim file `.env` atau password melalui chat/commit.
- Gunakan `public/` sebagai document root; jangan jadikan root proyek sebagai web root.
