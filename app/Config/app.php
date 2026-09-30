<?php
declare(strict_types=1);

/**
 * app/Config/app.php — konstanta aplikasi.
 *
 * Semua nilai punya default yang sama dengan Historically, sehingga aplikasi
 * tetap berperilaku identik bila .env tidak menambahkan apa pun. Setiap
 * konstanta hanya dapat dioverride lewat environment (lihat .env.example).
 */

require_once __DIR__ . '/env.php';

/**
 * Hentikan request karena konfigurasi server belum lengkap.
 * Alasan rinci hanya ditampilkan di luar production agar pengembang cepat
 * menemukan variabel yang salah; production tetap memakai pesan umum.
 */
function config_fail(string $reason): never
{
    error_log($reason);
    http_response_code(500);
    $isProduction = strtolower(trim((string) env_get('APP_ENV', 'production'))) === 'production';
    exit('Konfigurasi server tidak lengkap. Silakan hubungi administrator.' . ($isProduction ? '' : ' (' . $reason . ')'));
}

$appEnvironment = strtolower(trim((string) env_get('APP_ENV', 'production')));
if (!in_array($appEnvironment, ['production', 'staging', 'development', 'testing'], true)) {
    config_fail('APP_ENV tidak valid.');
}
define('APP_ENV', $appEnvironment);
define('APP_DEBUG', env_bool('APP_DEBUG', false));

$appUrl = rtrim(trim((string) env_get('APP_URL', '')), '/');
if ($appUrl === '' || filter_var($appUrl, FILTER_VALIDATE_URL) === false) {
    config_fail('APP_URL kosong atau tidak valid.');
}
if (APP_ENV === 'production' && !str_starts_with(strtolower($appUrl), 'https://')) {
    config_fail('APP_URL produksi wajib memakai HTTPS.');
}
define('APP_URL', $appUrl);

$appSecretKey = (string) env_get('APP_SECRET_KEY', '');
if (strlen($appSecretKey) < 32) {
    config_fail('APP_SECRET_KEY kosong atau terlalu pendek.');
}
define('APP_SECRET_KEY', $appSecretKey);

// Token setup untuk halaman diagnosa. Kosong di production = diagnosa nonaktif.
$appSetupToken = trim((string) env_get('APP_SETUP_TOKEN', ''));
if (APP_ENV === 'production' && strlen($appSetupToken) < 32) {
    config_fail('APP_SETUP_TOKEN wajib diisi (minimal 32 karakter) di production.');
}
define('APP_SETUP_TOKEN', $appSetupToken);

define('APP_TIMEZONE', env_str('APP_TIMEZONE', 'Asia/Jakarta'));
define('APP_TRUST_PROXY', env_bool('APP_TRUST_PROXY', false));

// Batas & rate limit. Nilai default = nilai sebelum refactor.
define('MAX_UPLOAD_BYTES', env_int('MAX_UPLOAD_BYTES', 10 * 1024 * 1024));
define('UPLOAD_RATE_WINDOW_SECONDS', env_int('UPLOAD_RATE_WINDOW_SECONDS', 600));
define('MAX_UPLOAD_ATTEMPTS', env_int('MAX_UPLOAD_ATTEMPTS', 15));
define('API_RATE_WINDOW_SECONDS', env_int('API_RATE_WINDOW_SECONDS', 60));
define('MAX_API_ATTEMPTS', env_int('MAX_API_ATTEMPTS', 60));
define('VERIFY_RATE_WINDOW_SECONDS', env_int('VERIFY_RATE_WINDOW_SECONDS', 60));
define('MAX_VERIFY_ATTEMPTS', env_int('MAX_VERIFY_ATTEMPTS', 30));
define('LOGIN_RATE_WINDOW_SECONDS', env_int('LOGIN_RATE_WINDOW_SECONDS', 900));
define('MAX_LOGIN_ATTEMPTS', env_int('MAX_LOGIN_ATTEMPTS', 5));
define('RESET_RATE_WINDOW_SECONDS', env_int('RESET_RATE_WINDOW_SECONDS', 900));
define('MAX_RESET_ATTEMPTS', env_int('MAX_RESET_ATTEMPTS', 5));

// Cache-buster asset. Naikkan satu kali setiap kali app.css/portal.css/nav.js berubah.
define('ASSET_VERSION', env_str('ASSET_VERSION', '20260930a'));
