# Vendor assets

Berkas di sini diunduh dari CDN lalu disajikan sendiri, supaya halaman tidak
bergantung pada koneksi ke pihak ketiga dan tetap tampil di jaringan yang
memblokir CDN.

| Berkas | Versi | Sumber |
|---|---|---|
| `html5-qrcode.min.js` | 2.3.8 | https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js |
| `sweetalert2.min.js` | 11.22.2 | https://cdn.jsdelivr.net/npm/sweetalert2@11.22.2/dist/sweetalert2.all.min.js |

Diunduh 2026-09-30. Lisensi: html5-qrcode MIT, SweetAlert2 MIT.

## Aturan pembaruan

1. Pakai **versi persis**, jangan `latest` — kode scanner dan alert sudah
   diuji terhadap versi ini.
2. Ganti nama berkas bila versinya berubah, lalu perbarui tabel di atas.
3. Naikkan `ASSET_VERSION` di `.env` setelah memperbarui berkas di sini.
4. `app/Pages/index.php` memeriksa `typeof Html5QrcodeScanner` dan
   `typeof Swal` sebelum memakainya, jadi aset yang hilang tidak
   menyebabkan halaman putih.
