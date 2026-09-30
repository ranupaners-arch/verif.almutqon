<?php
declare(strict_types=1);

/**
 * app/Support/format.php — pemformatan nilai untuk tampilan.
 *
 * Fungsi di sini dipanggil dari seluruh aplikasi; nama & tanda tangannya
 * sengaja tidak diubah.
 */

/**
 * Waktu relatif dalam Bahasa Indonesia untuk tampilan "Aktivitas Terbaru".
 * Contoh: "baru saja", "5 menit lalu", "3 jam lalu", "2 hari lalu".
 * Lebih lama dari seminggu → tanggal "d M Y".
 */
function waktu_relatif(string $datetime): string
{
    try {
        $ts = (new DateTime($datetime))->getTimestamp();
    } catch (Exception $e) {
        return '-';
    }
    $diff = time() - $ts;
    if ($diff < 60) {
        return 'baru saja';
    }
    if ($diff < 3600) {
        return ((int) floor($diff / 60)) . ' menit lalu';
    }
    if ($diff < 86400) {
        return ((int) floor($diff / 3600)) . ' jam lalu';
    }
    if ($diff < 86400 * 7) {
        return ((int) floor($diff / 86400)) . ' hari lalu';
    }
    return date('d M Y', $ts);
}
