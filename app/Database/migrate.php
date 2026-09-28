<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$migrations = [
    dirname(__DIR__) . '/Support/migrate_tautan_staf.php',
    dirname(__DIR__) . '/Support/migrate_avatar_theme.php',
    dirname(__DIR__) . '/Support/migrate_jenis_dokumen.php',
    dirname(__DIR__) . '/Support/migrate_password_resets.php',
    dirname(__DIR__) . '/Support/migrate_system_actor.php',
];

foreach ($migrations as $migration) {
    echo basename($migration) . PHP_EOL;
    require $migration;
}

echo "Semua migrasi struktur selesai.\n";
