<?php
declare(strict_types=1);

/**
 * app/Bootstrap.php — titik masuk tunggal aplikasi.
 *
 * Urutan pemuatan penting:
 *   0. php_version  penjaga versi PHP, harus paling awal
 *   1. BASE_PATH   lokasi proyek, dibutuhkan semua file lain
 *   2. Config      env -> app (validasi) -> paths
 *   3. Handler     error/exception handler, lihat app/Support/errors.php
 *   4. Session     header keamanan & cookie
 *   5. Database    koneksi PDO
 *   6. Support     fungsi bantu yang dipakai lintas halaman
 *
 * File ini sengaja tipis: menambah tanggung jawab baru sebaiknya dibuat
 * modul di app/Config atau app/Support, bukan ditumpuk di sini.
 */

require_once __DIR__ . '/Config/php_version.php';

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

require_once __DIR__ . '/Config/app.php';
require_once __DIR__ . '/Config/paths.php';
require_once __DIR__ . '/Support/errors.php';

app_apply_timezone();
app_register_error_handlers();

$requestIsHttps = app_request_is_https();

if (PHP_SAPI !== 'cli') {
    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(self), microphone=(), geolocation=()');
    if (APP_ENV === 'production' && $requestIsHttps) {
        header('Strict-Transport-Security: max-age=31536000');
    }
}

session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure' => APP_ENV === 'production' || $requestIsHttps,
    'use_strict_mode' => true,
    'gc_maxlifetime' => 1800,
]);

require_once __DIR__ . '/Config/database.php';

require_once __DIR__ . '/Support/security.php';
require_once __DIR__ . '/Support/account.php';
require_once __DIR__ . '/Support/rate_limit.php';
require_once __DIR__ . '/Support/documents.php';
require_once __DIR__ . '/Support/auth.php';
require_once __DIR__ . '/Support/mail.php';
require_once __DIR__ . '/Support/format.php';
