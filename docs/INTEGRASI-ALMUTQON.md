# Integrasi Lintas Project: almutqon → verif.almutqon

Dokumen ini mencatat **kontrak** antara dua project terpisah. Yang paling
berbahaya: perubahan di sisi kita **tidak** langsung terlihat sebagai error
di project ini, dan repo ini **tidak punya integration test** yang menangkapnya.
Kalau kontrak ini pecah, alur pembayaran di `almutqon` yang gagal — bukan
halaman kita.

## Pembagian tanggung jawab

| Project | Tanggung jawab |
|---|---|
| `almutqon` (`/Users/mac/Herd/almutqon`) | Pendaftaran, pembayaran, pembuatan struk |
| `verif.almutqon` (repo ini) | Menyimpan dokumen, menerbitkan token/kode verifikasi, menyajikan halaman verifikasi publik |

Struk pelunasan **dimiliki `almutqon`** (`pages/bayar/struk.php`,
`pages/bayar/struk-pdf.php`). Repo ini tidak punya fitur itu secara sengaja.
`struk.php` menampilkan QR + kode yang **dihasilkan** oleh API di sini.

## Alur

```
pembayaran LUNAS  (almutqon)
  └─ verif_register_doc()
       1. create  -> dokumen dibuat tanpa PDF, demi mendapat verify_url
       2. replace_pdf -> PDF struk dilekatkan (butuh token dari langkah 1)
  └─ simpan verify_url + kode ke tabel users/pendaftar
  └─ struk.php menampilkan QR -> menuju index.php?token=...
```

Kode klien ada di `almutqon/config/verif-client.php` (fungsi `verif_api()`).

## Pemetaan endpoint → aksi

Sudah diverifikasi silang terhadap whitelist kedua sisi (lihat "Verifikasi" bawah).

| Endpoint | Aksi diterima |
|---|---|
| `app/Http/api_v1_dokumen.php` | `create`, `replace_pdf`, `revoke`, `restore` |
| `app/Http/api_v1_staf.php` | `upsert`, `toggle`, `rename_id`, `update_email`, `update_password`, `update_profile` |

`almutqon` memanggil 9 dari 10 itu: `create`, `replace_pdf`, `revoke`
(lewat `dokumen`) dan 6 aksi `staf`. `restore` tidak dipanggil dari sana —
dipakai UI admin repo ini sendiri.

Kedua sisi punya whitelist ketat (`in_array(..., true)`). **Menambah aksi di
sisi `almutqon` tanpa menambahkannya di sini akan ditolak**, dan vice versa.

## Autentikasi

- Header: `Authorization: Bearer <VERIF_API_TOKEN>`
- Dilayani oleh `app/Http/api_v1_boot.php`, dibandingkan dengan `hash_equals()`
  terhadap `VERIF_API_TOKEN` milik repo ini. Panjang minimal 32 karakter.
- **Token harus identik di kedua `.env`.** Nilai tidak boleh masuk Git, log, atau
  screenshot. Verifikasi kesamaan pakai sidik jari SHA-256, jangan cetak nilainya.
- `restore` sengaja tidak ada di daftar aksi `almutqon` — aksi itu mengubah
  status ke `active`, jadi hanya untuk operator repo ini.

## Kebijakan URL

`verif_config()` di `almutqon` menolak `http://` bila host-nya **non-lokal**
(`localhost`, `127.0.0.1`, `::1`, `*.test`, `*.local` tetap boleh). Ini
perlindungan terhadap token yang terkirim tanpa TLS. Jangan longgarkan.

## Yang bisa pecah diam-diam

- Mengganti nama aksi atau menambah parameter baru di `api_v1_*.php`
- Mengubah field yang dikirim `almutqon` (`no_surat`, `jenis`, `perihal`,
  `penerima`, `tanggal_dokumen`, `jabatan_ttd`, `penandatangan`)
- Mengubah `jenis` default atau format `verify_url` / `code_6digit`
- Memindahkan shim `public/api/v1/` (konsumen memanggil lewat path itu)

## Verifikasi ulang

Kontrak ini diuji manual pada 2026-09-30 dan lolos penuh. Untuk mengulang:

1. Pastikan `VERIF_API_TOKEN` sama di kedua `.env` (bandingkan sidik jari).
2. Panggil `verif_api('create', ...)` dari skrip yang me-`require`
   `almutqon/config/verif-client.php` — pakai HTTP client asli `almutqon`,
   jangan curl buatan, supaya jalur autentikasinya ikut teruji.
3. Lanjut `verif_api('replace_pdf', ['token' => ...], $pdf)`.
4. Buka `verify_url` secara publik (tanpa auth) → harus 200 dan menampilkan
   kode. Buka juga dengan `?code=<6 digit>` saja.
5. Cek negatif: token palsu tidak boleh membocorkan data dokumen; unduh PDF
   tanpa HMAC harus **403**.

## Record uji yang disengaja

`E2E-STRUK-20260930-01` (dokumen id 4) **memang ada untuk diamkan sebagai contoh**
alur yang sudah terverifikasi ujung-ke-ujung. Isinya bertuliskan
"uji integrasi (bukan dokumen nyata)".

**Jangan dihapus** saat bersih-bersih — kecuali owner sudah memutuskan.
Kalau dibiarkan, hapus file `storage/uploads/cert_*.pdf`-nya sekalian.
