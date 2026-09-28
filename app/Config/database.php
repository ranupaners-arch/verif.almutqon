<?php
declare(strict_types=1);

/** Database configuration. Seluruh nilai wajib berasal dari environment. */
$databaseKeys = ['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME'];
$databaseConfig = [];
foreach ($databaseKeys as $key) {
    $value = getenv($key);
    if ($value === false || trim((string) $value) === '') {
        error_log($key . ' belum dikonfigurasi.');
        http_response_code(500);
        exit('Konfigurasi server tidak lengkap. Silakan hubungi administrator.');
    }
    $databaseConfig[$key] = trim((string) $value);
}

$dbPasswordValue = getenv('DB_PASSWORD');
if ($dbPasswordValue === false || (APP_ENV === 'production' && $dbPasswordValue === '')) {
    error_log('DB_PASSWORD belum dikonfigurasi.');
    http_response_code(500);
    exit('Konfigurasi server tidak lengkap. Silakan hubungi administrator.');
}

if (!ctype_digit($databaseConfig['DB_PORT']) || (int) $databaseConfig['DB_PORT'] < 1 || (int) $databaseConfig['DB_PORT'] > 65535) {
    error_log('DB_PORT tidak valid.');
    http_response_code(500);
    exit('Konfigurasi server tidak lengkap. Silakan hubungi administrator.');
}

$dbHost = $databaseConfig['DB_HOST'];
$dbPort = $databaseConfig['DB_PORT'];
$dbName = $databaseConfig['DB_DATABASE'];
$dbUser = $databaseConfig['DB_USERNAME'];
$dbPassword = (string) $dbPasswordValue;

try {
    $pdo = new PDO(
        "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPassword,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Terjadi gangguan koneksi database. Silakan hubungi administrator.');
}
