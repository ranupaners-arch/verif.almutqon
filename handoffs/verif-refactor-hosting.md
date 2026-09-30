# Refactor host-ready & maintainable

Status: SELESAI · Service: verif.almutqon (web app) · Diperbarui: 2026-09-30

## Tujuan
Refactor agar rapi + siap deploy ke shared hosting Indonesia. **Tidak mengubah URL publik, tampilan, maupun perilaku.**

## Keputusan penting (sudah dikunci dengan owner)
| Topik | Keputusan |
|---|---|
| CDN (unpkg/jsdelivr/Google Fonts) | Self-host semua |
| Document root | Pertahankan fallback `index.php` + `.htaccess` root, diperkuat + didokumentasikan |
| Migrasi DB | phpMyAdmin import `schema.sql` + halaman diagnosa web. **Tanpa wizard installer** |
| PHP | Pertahankan 8.1+, guard versi yang ramah (bukan fatal error) |
| Jenis dokumen | Centralisasi validasi saja. **ENUM tidak diubah** |
| Pembagian kerja | opencode = Fase 0,1,2,3,5,6 (sensitif). `codex` = Fase 4 (mekanis) |

## Milestone
- [x] **Fase 0** — baseline `php -l` bersih, `handoffs/`, brief agent.
- [x] **Fase 1** — `app/Config/{env,app,paths}.php`; `getenv()` → `env_get()`.
- [x] **Fase 2** — `Bootstrap.php` 312 → 57 baris; 12 fungsi ke `app/Support/*`; error handler.
- [x] **Fase 3** — guard PHP 8.1, `.htaccess` per direktori, `public/diagnosa.php`.
- [x] **Fase 4** — `_head.php`, aset lokal, satu cache-buster (dikerjakan `codex`, direview).
- [x] **Fase 5** — `app/Support/jenis_dokumen.php`.
- [x] **Fase 6** — `docs/DEPLOYMENT.md` ditulis ulang, changelog #9.

Semua tercatat di `docs/CHANGELOG_FIXES.md` **Fix #9**.

## Status terakhir
Commit `d16fee5` = titik balik sebelum refactor. Tujuh commit refactor menyusul.
`php -l` 66/66 bersih; 10 halaman render dengan judul asli; check_deploy 25 lolos.

## Yang belum / perlu dilakukan manusia
1. **Belum diuji di Apache sungguhan.** `.htaccess` hanya bisa dibuktikan di server ber-Apache; `php -S` mengabaikannya. Verifikasi lewat `diagnosa.php` setelah upload.
2. **`.env` lokal memakai `DB_PORT=3306`, sedangkan MySQL lokal berjalan di 3307.** Semua pengujian memakai `DB_PORT=3307` sebagai environment override. `parse_ini_file` lama tidak bisa menangani override ini.
3. **Satu record uji ada di database lokal**: `dokumen id=3, no_surat=INV/1` dibuat saat menguji `jenis=invoice`. Belum dihapus — penghapusan data butuh izin eksplisit.
4. **Belum ada `git push`.** Deploy ke hosting tetap manual lewat panel.

## Jangan lakukan
- Jangan ubah URL, query string, atau teks yang terlihat. Judul 4 halaman pernah berubah saat refactor dan harus dikembalikan persis.
- Jangan ubah nilai default rate limit/MAX_UPLOAD_BYTES — hanya jadikan bisa di-override env.
- Jangan ubah `dokumen.jenis` ENUM jadi varchar/tabel.
- Jangan `git push` tanpa diminta.
- Jangan peek/print isi `.env` atau kredensial apa pun.
- Jangan percayai hasil agent tanpa review — `codex` lolos dari 6 verifikasi formal tapi masih mengubah 4 judul dan mematikan fitur pemindaian gambar.
