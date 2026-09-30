<?php
declare(strict_types=1);

/**
 * CLI: php app/Support/check_deploy.php
 *
 * Keluar dengan kode 0 bila tidak ada pemeriksaan yang gagal, 1 bila ada.
 * Pemeriksaan kategori peringatan tidak membuat exit code 1.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/Bootstrap.php';
require_once __DIR__ . '/deploy_checks.php';

$results = deploy_checks($pdo);
$summary = deploy_check_summary($results);

foreach ($results as $result) {
    if ($result['ok']) {
        printf("[PASS] %s\n", $result['label']);
        continue;
    }
    printf("[%s] %s — %s\n", $result['warn'] ? 'WARN' : 'FAIL', $result['label'], $result['detail']);
}

printf(
    "\n%d lolos, %d peringatan, %d gagal\n",
    $summary['passed'],
    $summary['warned'],
    $summary['failed']
);

if ($summary['failed'] > 0) {
    echo "Aplikasi belum siap dipublikasikan. Perbaiki setiap item FAIL terlebih dahulu.\n";
    exit(1);
}

echo "Siap dipublikasikan.\n";
