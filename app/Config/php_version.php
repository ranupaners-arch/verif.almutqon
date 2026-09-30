<?php
declare(strict_types=1);

/**
 * app/Config/php_version.php — penjaga versi PHP.
 *
 * Berkas ini dimuat paling pertama, sebelum konfigurasi lain, karena
 * setelahnya masih ada langkah pemuatan lain. Kegagalan di sini
 * harus tampil sebagai halaman yang bisa dibaca, bukan 500 kosong atau fatal
 * error yang tidak menjelaskan apa pun.
 *
 * Versi minimum 8.1 mengikuti penggunaan `never`, `str_starts_with`, dan
 * `array|false` di seluruh kode.
 */

const APP_MIN_PHP_VERSION = '8.1';

if (PHP_VERSION_ID < 80100) {
    $minimum = APP_MIN_PHP_VERSION;
    $current = PHP_VERSION;

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "AL MUTQON membutuhkan PHP {$minimum} atau lebih baru. Versi sekarang: {$current}\n");
        exit(1);
    }

    if (!headers_sent()) {
        http_response_code(503);
        header('Content-Type: text/html; charset=UTF-8');
        header('Retry-After: 3600');
    }

    echo '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>Versi PHP perlu diperbarui</title></head><body>'
        . '<h1>Versi PHP perlu diperbarui</h1>'
        . '<p>Situs ini membutuhkan PHP ' . htmlspecialchars($minimum, ENT_QUOTES) . ' atau lebih baru. '
        . 'Server saat ini menjalankan PHP ' . htmlspecialchars($current, ENT_QUOTES) . '.</p>'
        . '<p>Silakan pilih versi PHP yang lebih baru di panel hosting, lalu muat ulang halaman ini.</p>'
        . '</body></html>';

    exit;
}
