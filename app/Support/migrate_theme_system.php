<?php
declare(strict_types=1);

/**
 * Opsi tema 'system' (ikuti OS) untuk staff.theme.
 *
 * Pemakaian:
 *   php app/Support/migrate_theme_system.php              # jalankan migrasi
 *   php app/Support/migrate_theme_system.php --dry-run    # preview tanpa mengubah apa pun
 */

require dirname(__DIR__) . '/Bootstrap.php';

$dryRun = in_array('--dry-run', $argv, true);

$stmt = $pdo->prepare(
    "SELECT COLUMN_TYPE FROM information_schema.columns
     WHERE table_schema = DATABASE() AND table_name = 'staff' AND column_name = 'theme'"
);
$stmt->execute();
$type = (string) ($stmt->fetchColumn() ?: '');
$hasSystem = stripos($type, "'system'") !== false;

if (!$hasSystem) {
    if ($dryRun) {
        echo "  + ENUM theme akan ditambah opsi 'system'\n";
    } else {
        $pdo->exec("ALTER TABLE `staff` MODIFY COLUMN `theme` ENUM('light','dark','system') NOT NULL DEFAULT 'light'");
        echo "  + ENUM theme ditambah opsi 'system'\n";
    }
} else {
    echo "  = opsi 'system' sudah ada\n";
}

echo $dryRun ? "DRY-RUN: tidak ada perubahan.\n" : "OK: tema 'system' tersedia.\n";
