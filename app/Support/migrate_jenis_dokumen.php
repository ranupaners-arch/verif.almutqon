<?php
declare(strict_types=1);

/**
 * Pemisah jenis dokumen: surat/sertifikat (bernomor) vs invoice/struk (tanpa nomor).
 *
 * - Kolom baru `dokumen.jenis` ENUM('dokumen','invoice') default 'dokumen'.
 * - Backfill: no_surat LIKE 'INV-%' → jenis 'invoice'.
 * - `nomor_urut` menjadi NULL-able; baris invoice dikosongkan agar tidak masuk
 *   hitungan nomor terakhir, total dokumen, maupun rekap penomoran.
 *
 * Pemakaian:
 *   php app/Support/migrate_jenis_dokumen.php              # jalankan migrasi
 *   php app/Support/migrate_jenis_dokumen.php --dry-run    # preview tanpa mengubah apa pun
 */

require_once dirname(__DIR__) . '/Bootstrap.php';

$dryRun = in_array('--dry-run', $argv, true);

$colCount = static function (string $col) use ($pdo): int {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
    );
    $stmt->execute(['dokumen', $col]);
    return (int) $stmt->fetchColumn();
};

if ($colCount('jenis') === 0) {
    if ($dryRun) {
        echo "  + kolom `jenis` akan ditambahkan\n";
    } else {
        $pdo->exec("ALTER TABLE `dokumen` ADD COLUMN `jenis` ENUM('dokumen','invoice') NOT NULL DEFAULT 'dokumen' AFTER `nomor_urut`");
        echo "  + kolom `jenis` ditambahkan\n";
    }
} else {
    echo "  = kolom `jenis` sudah ada\n";
}

$idxCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.statistics
     WHERE table_schema = DATABASE() AND table_name = 'dokumen' AND index_name = 'idx_dokumen_jenis'"
)->fetchColumn();
if ($idxCount === 0) {
    if ($dryRun) {
        echo "  + index `idx_dokumen_jenis` akan ditambahkan\n";
    } else {
        $pdo->exec('ALTER TABLE `dokumen` ADD INDEX `idx_dokumen_jenis` (`jenis`)');
        echo "  + index `idx_dokumen_jenis` ditambahkan\n";
    }
} else {
    echo "  = index `idx_dokumen_jenis` sudah ada\n";
}

// Backfill invoice lama (butuh kolom jenis dulu).
$jenisAda = $colCount('jenis') > 0;
if ($jenisAda && !$dryRun) {
    $n = $pdo->exec("UPDATE `dokumen` SET `jenis` = 'invoice' WHERE `no_surat` LIKE 'INV-%' AND `jenis` <> 'invoice'");
    echo "  = {$n} baris INV-% ditandai invoice\n";
} elseif ($jenisAda && $dryRun) {
    $n = (int) $pdo->query("SELECT COUNT(*) FROM `dokumen` WHERE `no_surat` LIKE 'INV-%'")->fetchColumn();
    echo "  = {$n} baris INV-% akan ditandai invoice\n";
} else {
    echo "  = kolom `jenis` belum ada — lewati hitung backfill\n";
}

// Longgarkan nomor_urut (invoice = NULL).
$stmt = $pdo->prepare(
    'SELECT IS_NULLABLE FROM information_schema.columns
     WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
);
$stmt->execute(['dokumen', 'nomor_urut']);
$nullable = strtoupper((string) $stmt->fetchColumn()) === 'YES';
if (!$nullable) {
    if ($dryRun) {
        echo "  + `nomor_urut` akan menjadi NULL-able\n";
    } else {
        $pdo->exec('ALTER TABLE `dokumen` MODIFY COLUMN `nomor_urut` INT UNSIGNED NULL');
        echo "  + `nomor_urut` menjadi NULL-able\n";
    }
} else {
    echo "  = `nomor_urut` sudah NULL-able\n";
}

if (!$dryRun && $jenisAda) {
    $n = $pdo->exec("UPDATE `dokumen` SET `nomor_urut` = NULL WHERE `jenis` = 'invoice' AND `nomor_urut` IS NOT NULL");
    echo "  = {$n} nomor invoice dikosongkan\n";
} elseif ($dryRun && $jenisAda) {
    $n = (int) $pdo->query("SELECT COUNT(*) FROM `dokumen` WHERE `jenis` = 'invoice' AND `nomor_urut` IS NOT NULL")->fetchColumn();
    echo "  = {$n} nomor invoice akan dikosongkan\n";
} else {
    echo "  = kolom `jenis` belum ada — lewati pengosongan nomor\n";
}

echo $dryRun ? "DRY-RUN: tidak ada perubahan.\n" : "OK: jenis dokumen terpisah; invoice tanpa nomor urut.\n";
