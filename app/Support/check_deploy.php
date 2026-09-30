<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/Bootstrap.php';

$errors = [];
$passes = [];

$check = static function (bool $condition, string $success, string $failure) use (&$errors, &$passes): void {
    if ($condition) {
        $passes[] = $success;
        return;
    }
    $errors[] = $failure;
};

$check(APP_ENV === 'production', 'APP_ENV production', 'APP_ENV harus production');
$check(APP_DEBUG === false, 'APP_DEBUG nonaktif', 'APP_DEBUG harus false');
$check(str_starts_with(strtolower(APP_URL), 'https://'), 'APP_URL memakai HTTPS', 'APP_URL harus memakai HTTPS');
$check(strlen(APP_SECRET_KEY) >= 32, 'APP_SECRET_KEY memenuhi panjang minimum', 'APP_SECRET_KEY minimal 32 karakter');
$check(strlen((string) env_get('VERIF_API_TOKEN', '')) >= 32, 'VERIF_API_TOKEN tersedia', 'VERIF_API_TOKEN minimal 32 karakter');
$check(trim((string) env_get('VERIF_API_SYSTEM_ACTOR_KEY', '')) !== '', 'System actor key tersedia', 'VERIF_API_SYSTEM_ACTOR_KEY wajib diisi');
$check(trim((string) env_get('DB_PASSWORD', '')) !== '', 'Password database tersedia', 'DB_PASSWORD produksi tidak boleh kosong');
$check(PHP_VERSION_ID >= 80100, 'Versi PHP didukung', 'Gunakan PHP 8.1 atau lebih baru');
$check(extension_loaded('pdo_mysql'), 'Ekstensi pdo_mysql tersedia', 'Ekstensi pdo_mysql tidak tersedia');
$check(extension_loaded('fileinfo'), 'Ekstensi fileinfo tersedia', 'Ekstensi fileinfo tidak tersedia');
$check(is_dir(UPLOAD_DIR) && is_writable(UPLOAD_DIR), 'Storage PDF writable', UPLOAD_DIR . ' tidak writable');
$check(is_dir(AVATAR_DIR) && is_writable(AVATAR_DIR), 'Storage avatar writable', AVATAR_DIR . ' tidak writable');

$mailEnabled = filter_var(env_get('MAIL_ENABLED', 'false'), FILTER_VALIDATE_BOOL);
if ($mailEnabled) {
    $check(filter_var(env_get('MAIL_FROM', ''), FILTER_VALIDATE_EMAIL) !== false, 'Alamat pengirim email valid', 'MAIL_FROM tidak valid');
    $check(function_exists('mail'), 'Mail transport PHP tersedia', 'Fungsi mail PHP tidak tersedia');
} else {
    $passes[] = 'Reset password melalui email dinonaktifkan';
}

$requiredTables = ['staff', 'system_actors', 'dokumen', 'activity_log', 'password_resets'];
$availableTables = [];
foreach ($requiredTables as $table) {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
    );
    $stmt->execute([$table]);
    $availableTables[$table] = (int) $stmt->fetchColumn() === 1;
    $check($availableTables[$table], "Tabel {$table} tersedia", "Tabel {$table} belum tersedia");
}

$actorKey = trim((string) env_get('VERIF_API_SYSTEM_ACTOR_KEY', ''));
if ($actorKey !== '' && $availableTables['system_actors']) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM system_actors WHERE actor_key = ? AND is_active = 1');
    $stmt->execute([$actorKey]);
    $check((int) $stmt->fetchColumn() === 1, 'System actor aktif', 'System actor belum tersedia atau nonaktif');
}

foreach ($passes as $message) {
    echo '[PASS] ' . $message . PHP_EOL;
}
foreach ($errors as $message) {
    fwrite(STDERR, '[FAIL] ' . $message . PHP_EOL);
}

exit($errors === [] ? 0 : 1);
