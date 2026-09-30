<?php
declare(strict_types=1);

/**
 * app/Support/jenis_dokumen.php — satu sumber kebenaran jenis dokumen.
 *
 * `dokumen.jenis` bertipe ENUM('dokumen','invoice'). Pasangan nilai ini hanya
 * boleh disebut dari berkas ini; kolom ENUM-nya tidak diubah, dan query yang
 * membandingkan jenis di SQL tetap memakai literal karena PDO tidak bisa
 * mengikat nilai ke dalam KOLOM ENUM tanpa mengubahnya.
 *
 * Makna:
 *   dokumen  — surat/sertifikat resmi, punya nomor urut
 *   invoice  — struk/invoice, tidak punya nomor urut (nomor_urut = NULL)
 *
 * Menambah jenis baru berarti: tambah di JENIS_DOKUMEN, ubah ENUM di
 * app/Database/schema.sql, lalu jalankan migrasi ALTER TABLE. Mengubah hanya
 * kode saja akan ditolak database.
 */

/** Nilai yang sah, urutan menentukan tampilan pada select box. */
const JENIS_DOKUMEN = ['dokumen', 'invoice'];

/** Nilai bawaan bila pemanggil tidak mengirim jenis sama sekali. */
const JENIS_DOKUMEN_DEFAULT = 'dokumen';

/**
 * Bersihkan input dari request: lowercase + trim.
 * Mengembalikan null bila nilainya di luar daftar yang sah.
 */
function jenis_dokumen_from_input(mixed $value): ?string
{
    $jenis = strtolower(trim((string) $value));
    if ($jenis === '') {
        $jenis = JENIS_DOKUMEN_DEFAULT;
    }

    return in_array($jenis, JENIS_DOKUMEN, true) ? $jenis : null;
}

/**
 * Sama seperti jenis_dokumen_from_input(), tetapi memakai nilai bawaan bila
 * input kosong. Untuk kasus di mana jenis memang opsional.
 */
function jenis_dokumen_or_default(mixed $value): string
{
    return jenis_dokumen_from_input($value) ?? JENIS_DOKUMEN_DEFAULT;
}

function jenis_dokumen_valid(mixed $value): bool
{
    return jenis_dokumen_from_input($value) !== null;
}

/** Apakah jenis ini memakai nomor urut resmi? */
function jenis_dokumen_bernomor(string $jenis): bool
{
    return jenis_dokumen_or_default($jenis) !== 'invoice';
}
