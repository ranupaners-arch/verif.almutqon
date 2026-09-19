# AGENTS.md

## Project Overview

`verif-almutqon` adalah aplikasi web untuk verifikasi dan pengelolaan dokumen digital dalam ekosistem Almutqon.

Sistem dapat digunakan untuk berbagai jenis dokumen, termasuk tetapi tidak terbatas pada:

* Sertifikat
* Invoice
* Surat
* Bukti pembayaran
* Dokumen administrasi
* Dokumen digital lainnya

Jangan mengasumsikan bahwa sistem hanya digunakan untuk sertifikat.

## Tech Stack

* PHP native
* HTML
* CSS
* JavaScript
* SQL database
* `.env` untuk konfigurasi environment dan secret

Jangan memperkenalkan framework atau dependency besar baru tanpa alasan yang jelas.

## Project Structure

Struktur utama project meliputi:

* `app/` — logic aplikasi
* `app/Config/` — konfigurasi
* `app/Database/` — database dan schema
* `app/Http/` — HTTP handling dan endpoint
* `app/Pages/` — halaman aplikasi
* `app/Support/` — helper/support code
* `public/` — document root dan entry point publik
* `public/assets/` — aset frontend
* `storage/` — file dan data internal
* `.env` — konfigurasi environment dan secret
* `.env.example` — template konfigurasi tanpa secret

Selalu periksa struktur aktual project sebelum membuat asumsi tentang lokasi file.

## Core Principles

Project harus tetap:

* Aman
* Sederhana
* Maintainable
* Responsive
* Modular
* Kompatibel dengan sistem Almutqon

Jangan melakukan perubahan arsitektur besar tanpa alasan yang jelas.

## Document System

Sistem harus dirancang secara fleksibel untuk berbagai tipe dokumen.

Jangan membuat logic yang secara khusus mengasumsikan:

```text
certificate
```

sebagai satu-satunya jenis dokumen.

Gunakan konsep generik seperti:

```text
document
document_type
document_id
verification_code
issuer
recipient
metadata
```

jika memang sesuai dengan implementasi yang sudah ada.

Jika menambahkan jenis dokumen baru, usahakan tidak perlu mengubah logic inti sistem secara besar-besaran.

## Document Verification

Data yang berasal dari pengguna harus selalu dianggap sebagai untrusted input.

Saat melakukan verifikasi dokumen:

* Validasi kode/ID dokumen.
* Validasi status dokumen.
* Validasi data terhadap database.
* Jangan mempercayai data hanya berdasarkan parameter URL.
* Jangan menganggap keberadaan file sebagai bukti dokumen valid.
* Jangan membocorkan data internal yang tidak diperlukan.
* Jangan membocorkan informasi sensitif pemilik dokumen.

Response API harus konsisten dan aman.

## Security Rules

Jangan pernah:

* Menampilkan API key.
* Menampilkan token.
* Menampilkan password.
* Menampilkan secret key.
* Menampilkan credential database.
* Menampilkan OAuth secret.
* Menampilkan isi `.env`.
* Commit `.env`.
* Hardcode credential ke source code.
* Membuat authentication bypass.
* Membuat auto-login production.
* Menambahkan backdoor debugging.

Jika menemukan secret hardcoded:

1. Jangan tampilkan nilainya.
2. Laporkan lokasi file dan jenis secret.
3. Jika aman, pindahkan ke environment variable.

File sensitif harus tetap di-ignore oleh Git.

## Authentication & Authorization

Pertahankan sistem authentication dan authorization yang sudah ada.

Jangan:

* Menghapus validasi permission.
* Membuat bypass login.
* Menambahkan hardcoded session.
* Mengurangi security check.
* Membuka endpoint internal ke publik tanpa alasan.

Jika membutuhkan debugging authentication, gunakan metode development yang tidak ikut digunakan di production.

## Database

Gunakan environment variable untuk konfigurasi database.

Jangan hardcode:

* Database host
* Database username
* Database password
* Database credential lainnya

Gunakan prepared statements untuk query yang menerima input pengguna.

Jangan mengubah schema database production tanpa memahami dampaknya terhadap aplikasi yang sudah berjalan.

## API

Setiap endpoint harus:

* Memvalidasi input.
* Menggunakan authentication/authorization jika diperlukan.
* Mengembalikan HTTP status code yang sesuai.
* Tidak membocorkan secret.
* Tidak membocorkan stack trace pada production.
* Tidak mengembalikan data sensitif yang tidak diperlukan.

Jika menggunakan API token untuk komunikasi internal, ambil token dari environment variable.

## File & Storage

File yang diunggah pengguna harus dianggap sebagai untrusted input.

Pastikan:

* Nama file tidak digunakan langsung sebagai path.
* File upload divalidasi.
* File disimpan pada lokasi yang aman.
* File tidak dapat menjalankan script secara sembarangan.
* Path server internal tidak dibocorkan kepada pengguna.

File upload production tidak boleh masuk Git.

## Frontend

Pertahankan UI yang sudah ada kecuali perubahan desain memang diminta.

Pastikan:

* Responsive desktop/mobile.
* Layout konsisten.
* Accessibility tetap diperhatikan.
* Tidak menambahkan dependency besar untuk perubahan kecil.
* Gunakan CSS dan JavaScript existing jika memungkinkan.

## Coding Style

Sebelum mengubah kode:

1. Baca file yang relevan.
2. Pahami alur data.
3. Cari fungsi dan endpoint yang terkait.
4. Periksa dependency antar-file.
5. Buat perubahan sekecil mungkin.

Utamakan kode yang:

* jelas
* sederhana
* mudah dirawat
* memiliki validasi
* memiliki error handling yang aman

Hindari:

* Duplikasi logic
* Global state yang tidak diperlukan
* Magic values
* Debug code yang tertinggal
* Perubahan besar yang tidak berkaitan dengan task

## Git

Sebelum commit:

```bash
git status
git diff
```

Pastikan tidak ada:

* `.env`
* API key
* token
* password
* credential
* private key
* uploaded documents
* database dump sensitif
* debug/backdoor files

Jangan melakukan `git push` kecuali diminta secara eksplisit.

Gunakan commit message yang jelas, misalnya:

```text
Improve document verification
```

atau:

```text
Fix document validation
```

## Testing

Setelah melakukan perubahan:

1. Periksa syntax PHP.
2. Periksa endpoint yang terdampak.
3. Periksa halaman terkait.
4. Periksa authentication/authorization jika relevan.
5. Periksa upload dan document handling jika relevan.
6. Periksa JavaScript console jika perubahan frontend.
7. Pastikan tidak ada error baru.

Untuk syntax PHP:

```bash
php -l path/to/file.php
```

Gunakan test yang sudah tersedia jika project memilikinya.

## Agent Behavior

Sebelum coding:

* Pahami task.
* Periksa implementasi existing.
* Jangan langsung mengganti arsitektur.

Saat coding:

* Ubah hanya bagian yang diperlukan.
* Pertahankan behavior existing yang tidak berkaitan.
* Jangan menghapus fitur tanpa instruksi.
* Jangan membuat dependency baru tanpa alasan.

Setelah coding:

* Review perubahan.
* Jalankan pemeriksaan yang relevan.
* Laporkan file yang berubah.
* Laporkan test/check yang dijalankan.
* Laporkan masalah yang belum dapat diverifikasi.

## Important Principle

`verif-almutqon` adalah sistem dokumen generik.

Sertifikat hanyalah salah satu jenis dokumen.

Semua pengembangan baru harus mempertimbangkan kemungkinan sistem digunakan untuk:

**sertifikat → invoice → surat → bukti pembayaran → dokumen administrasi → jenis dokumen lainnya.**
