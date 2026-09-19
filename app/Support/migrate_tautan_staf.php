<?php
declare(strict_types=1);

/**
 * Migrasi kolom tautan personel ke users ALMUTQON (sinkron ID pengajar/staf).
 *
 * Kolom `staff.almutqon_user_id` = id baris `users` di db_almutqon-website
 * (lintas DB: tanpa FK). Diisi skrip pairing sisi almutqon + endpoint staf v1.
 *
 * Pemakaian:
 *   php app/Support/migrate_tautan_staf.php              # jalankan migrasi
 *   php app/Support/migrate_tautan_staf.php --dry-run    # preview tanpa mengubah apa pun
 */

require dirname(__DIR__) . '/Bootstrap.php';

$dryRun = in_array('--dry-run', $argv, true);

$hasCol = (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE() AND table_name = 'staff' AND column_name = 'almutqon_user_id'"
)->fetchColumn();

if ($hasCol === 0) {
    if ($dryRun) {
        echo "  + kolom `almutqon_user_id` akan ditambahkan\n";
    } else {
        $pdo->exec('ALTER TABLE `staff` ADD COLUMN `almutqon_user_id` INT UNSIGNED NULL DEFAULT NULL AFTER `nip`');
        echo "  + kolom `almutqon_user_id` ditambahkan\n";
    }
} else {
    echo "  = kolom `almutqon_user_id` sudah ada\n";
}

$hasIdx = (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.statistics
     WHERE table_schema = DATABASE() AND table_name = 'staff' AND index_name = 'idx_staff_almutqon_user'"
)->fetchColumn();

if ($hasIdx === 0) {
    if ($dryRun) {
        echo "  + index `idx_staff_almutqon_user` akan ditambahkan\n";
    } else {
        $pdo->exec('ALTER TABLE `staff` ADD INDEX `idx_staff_almutqon_user` (`almutqon_user_id`)');
        echo "  + index `idx_staff_almutqon_user` ditambahkan\n";
    }
} else {
    echo "  = index `idx_staff_almutqon_user` sudah ada\n";
}

echo $dryRun ? "DRY-RUN: tidak ada perubahan.\n" : "OK: tabel `staff` siap untuk tautan ID ALMUTQON.\n";
