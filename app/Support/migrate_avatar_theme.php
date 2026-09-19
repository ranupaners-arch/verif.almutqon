<?php
declare(strict_types=1);

/**
 * Avatar + tema per staff (menu akun di masthead).
 *
 * - `staff.avatar` VARCHAR(255) NULL: path relatif di public/assets/avatars/.
 * - `staff.theme` ENUM('light','dark') DEFAULT 'light': tema per akun.
 *
 * Pemakaian:
 *   php app/Support/migrate_avatar_theme.php              # jalankan migrasi
 *   php app/Support/migrate_avatar_theme.php --dry-run    # preview tanpa mengubah apa pun
 */

require dirname(__DIR__) . '/Bootstrap.php';

$dryRun = in_array('--dry-run', $argv, true);

$colCount = static function (string $col) use ($pdo): int {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
    );
    $stmt->execute(['staff', $col]);
    return (int) $stmt->fetchColumn();
};

if ($colCount('avatar') === 0) {
    if ($dryRun) {
        echo "  + kolom `avatar` akan ditambahkan\n";
    } else {
        $pdo->exec('ALTER TABLE `staff` ADD COLUMN `avatar` VARCHAR(255) NULL DEFAULT NULL AFTER `jabatan`');
        echo "  + kolom `avatar` ditambahkan\n";
    }
} else {
    echo "  = kolom `avatar` sudah ada\n";
}

if ($colCount('theme') === 0) {
    if ($dryRun) {
        echo "  + kolom `theme` akan ditambahkan\n";
    } else {
        $pdo->exec("ALTER TABLE `staff` ADD COLUMN `theme` ENUM('light','dark') NOT NULL DEFAULT 'light' AFTER `avatar`");
        echo "  + kolom `theme` ditambahkan\n";
    }
} else {
    echo "  = kolom `theme` sudah ada\n";
}

echo $dryRun ? "DRY-RUN: tidak ada perubahan.\n" : "OK: tabel `staff` siap untuk avatar & tema.\n";
