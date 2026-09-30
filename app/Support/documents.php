<?php
declare(strict_types=1);

/**
 * app/Support/documents.php — lokasi berkas PDF di disk.
 *
 * Fungsi di sini dipanggil dari seluruh aplikasi; nama & tanda tangannya
 * sengaja tidak diubah.
 */

/**
 * Lokasi fisik file PDF dari nilai `pdf_path` di database.
 *
 * Nilai normal hanya berisi nama file (disimpan di storage/uploads).
 * Untuk kompatibilitas PDF lama, ikut mencari di lokasi legacy
 * (public/uploads, public/, storage/) sambil membatasi traversal ke
 * direktori yang diizinkan.
 */
function resolve_pdf_path(?string $pdf_path): ?string
{
    $raw = trim((string) $pdf_path);
    if ($raw === '') {
        return null;
    }

    $allowedBases = [];
    foreach ([UPLOAD_DIR, PUBLIC_PATH] as $base) {
        $real = realpath($base);
        if ($real !== false) {
            $allowedBases[] = $real;
        }
    }

    $candidates = [];
    $basename = basename($raw);
    $candidates[] = UPLOAD_DIR . '/' . $basename;
    $candidates[] = PUBLIC_PATH . '/uploads/' . $basename;
    $candidates[] = PUBLIC_PATH . '/' . $basename;
    if ($basename !== $raw) {
        $candidates[] = STORAGE_PATH . '/' . $raw;
        $candidates[] = PUBLIC_PATH . '/' . $raw;
        $candidates[] = BASE_PATH . '/' . $raw;
        $candidates[] = UPLOAD_DIR . '/' . $raw;
        $candidates[] = PUBLIC_PATH . '/uploads/' . $raw;
    }

    foreach ($candidates as $candidate) {
        $candidate = str_replace('\\', '/', $candidate);
        if (!is_file($candidate)) {
            continue;
        }
        $real = realpath($candidate);
        if ($real === false) {
            continue;
        }
        foreach ($allowedBases as $allowed) {
            if (str_starts_with($real, $allowed . '/')) {
                return $real;
            }
        }
    }

    return null;
}
