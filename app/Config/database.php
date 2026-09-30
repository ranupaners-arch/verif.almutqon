<?php
declare(strict_types=1);

/** Database configuration. Seluruh nilai wajib berasal dari environment. */
$databaseKeys = ['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME'];
$databaseConfig = [];
foreach ($databaseKeys as $key) {
    $value = env_get($key, '');
    if ($value === false || trim((string) $value) === '') {
        config_fail($key . ' belum dikonfigurasi.');
    }
    $databaseConfig[$key] = trim((string) $value);
}

$dbPasswordValue = env_get('DB_PASSWORD', '');
if (APP_ENV === 'production' && $dbPasswordValue === '') {
    config_fail('DB_PASSWORD belum dikonfigurasi.');
}

if (!ctype_digit($databaseConfig['DB_PORT']) || (int) $databaseConfig['DB_PORT'] < 1 || (int) $databaseConfig['DB_PORT'] > 65535) {
    config_fail('DB_PORT tidak valid.');
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
    app_log('error', 'Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Terjadi gangguan koneksi database. Silakan hubungi administrator.');
}

/**
 * Samakan zona waktu MySQL dengan APP_TIMEZONE.
 *
 * Tanpa ini server ber-UTC akan menulis NOW() dalam UTC sementara PHP memakai
 * Asia/Jakarta, sehingga cap waktu pada dokumen dan label "WIB" tidak sinkron.
 * Offset dikirim sebagai "+07:00" (bukan "Asia/Jakarta") karena MySQL hanya
 * mengenali nama zona bila tabel timezone sudah dimuat di server.
 */
try {
    $appTimezoneOffset = (new DateTimeImmutable('now', new DateTimeZone(APP_TIMEZONE)))->format('P');
    $pdo->exec('SET time_zone = ' . $pdo->quote($appTimezoneOffset));
} catch (Throwable $e) {
    app_log('warning', 'Gagal menyamakan zona waktu MySQL: ' . $e->getMessage());
}
