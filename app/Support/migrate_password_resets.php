<?php
// app/Support/migrate_password_resets.php — buat tabel password_resets untuk staff.
// Jalankan: php app/Support/migrate_password_resets.php
require dirname(__DIR__) . '/Bootstrap.php';

$pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL,
    token CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_email (email),
    KEY idx_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

echo "OK: tabel `password_resets` siap.\n";