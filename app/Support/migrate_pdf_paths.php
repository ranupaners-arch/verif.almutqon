<?php
declare(strict_types=1);

/**
 * Migrasi path PDF lama ke skema baru (storage/uploads/<nama-file>).
 *
 * Sebelum refactor, pdf_path mungkin menyimpan path lama (mis. "uploads/foo.pdf"
 * atau path absolut). Script ini:
 *   - merapikan nilai yang sudah benar (cukup basename, file sudah di storage/uploads),
 *   - menyalin file yang masih hidup di lokasi legacy ke storage/uploads dengan
 *     nama skema baru (cert_<uniqid>.pdf) serta memperbarui database,
 *   - melaporkan path yang tidak ditemukan (orphan) agar bisa ditindaklanjuti manual.
 *
 * Pemakaian:
 *   php app/Support/migrate_pdf_paths.php        # jalankan migrasi
 *   php app/Support/migrate_pdf_paths.php --dry-run   # preview tanpa mengubah apa pun
 */

require_once dirname(__DIR__) . '/Bootstrap.php';

$dryRun = in_array('--dry-run', $argv, true);

if (!is_dir(STORAGE_PATH . '/uploads') && !mkdir(STORAGE_PATH . '/uploads', 0755, true)) {
    fwrite(STDERR, "Gagal membuat folder storage/uploads.\n");
    exit(1);
}

$rows = $pdo->query("SELECT id, no_surat, pdf_path FROM dokumen WHERE pdf_path IS NOT NULL AND pdf_path != ''")->fetchAll();

$migrated = 0;
$canonicalized = 0;
$missing = 0;
$failed = 0;

foreach ($rows as $row) {
    $raw = trim((string) $row['pdf_path']);
    $basename = basename($raw);

    echo "#{$row['id']} ({$row['no_surat']})  pdf_path={$raw}\n";

    if ($basename === '' || $basename === '.' || $basename === '/') {
        echo "  [skip] nilai pdf_path tidak valid\n";
        continue;
    }

    $located = resolve_pdf_path($raw);

    if ($located === null) {
        $missing++;
        echo "  [missing] file tidak ditemukan di storage/uploads maupun lokasi legacy\n";
        continue;
    }

    $locatedReal = realpath($located);
    $uploadsReal = realpath(STORAGE_PATH . '/uploads');

    if ($locatedReal !== false && $uploadsReal !== false && str_starts_with($locatedReal, $uploadsReal . '/')) {
        $canonicalized++;
        if ($locatedReal === realpath(STORAGE_PATH . '/uploads/' . $basename) && $basename === $raw) {
            echo "  [ok] sudah sesuai skema baru\n";
            continue;
        }
        $newValue = basename($locatedReal);
        if ($dryRun) {
            echo "  [dry] pdf_path akan dikoreksi menjadi: {$newValue}\n";
        } else {
            $pdo->prepare('UPDATE dokumen SET pdf_path = ? WHERE id = ?')->execute([$newValue, $row['id']]);
            echo "  [fix] pdf_path dikoreksi menjadi: {$newValue}\n";
        }
        continue;
    }

    $newName = 'cert_' . uniqid() . '.pdf';
    $dest = STORAGE_PATH . '/uploads/' . $newName;

    if ($dryRun) {
        echo "  [dry] akan disalin dari {$locatedReal} ke {$dest}\n";
        $migrated++;
        continue;
    }

    if (!copy($locatedReal, $dest)) {
        $failed++;
        echo "  [failed] gagal menyalin {$locatedReal} ke {$dest}\n";
        continue;
    }

    try {
        $pdo->prepare('UPDATE dokumen SET pdf_path = ? WHERE id = ?')->execute([$newName, $row['id']]);
        $migrated++;
        echo "  [migrated] {$locatedReal} -> {$dest}\n";
    } catch (PDOException $e) {
        @unlink($dest);
        $failed++;
        echo "  [failed] gagal memperbarui database: " . $e->getMessage() . "\n";
    }
}

echo "\nSelesai. Migrasi: {$migrated}, Dikoreksi: {$canonicalized}, Tidak ditemukan: {$missing}, Gagal: {$failed}";
echo $dryRun ? " (dry-run, tidak ada perubahan)" : "";
echo "\n";
