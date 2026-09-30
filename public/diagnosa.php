<?php
declare(strict_types=1);

/**
 * public/diagnosa.php — pemeriksaan kesiapan deploy lewat browser.
 *
 * Untuk pemilik hosting yang tidak punya akses SSH. Halaman ini memuat
 * konfigurasi tanpa Bootstrap penuh, supaya tetap bisa melaporkan "koneksi
 * database gagal" — justru kondisi yang paling sering membuat halaman ini dibutuhkan.
 *
 * Akses:
 *   - APP_SETUP_TOKEN kosong di production  -> 404 (halaman tidak ada)
 *   - selain itu  -> wajib menyertakan token lewat ?token=... atau POST
 *
 * Halaman ini sengaja TIDAK pernah menampilkan nilai kredensial, hanya status
 * ya/tidak. Hapus setelah deploy selesai.
 */

require_once dirname(__DIR__) . '/app/Config/php_version.php';

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

require_once BASE_PATH . '/app/Config/app.php';
require_once BASE_PATH . '/app/Config/paths.php';
require_once BASE_PATH . '/app/Support/errors.php';
require_once BASE_PATH . '/app/Support/deploy_checks.php';

app_register_error_handlers();

$notFound = static function (): never {
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><title>404</title></head>'
        . '<body><h1>404</h1><p>Halaman tidak ditemukan.</p></body></html>';
    exit;
};

// Tanpa token yang_valid, halaman ini tidak ada sama sekali.
if (APP_SETUP_TOKEN === '') {
    $notFound();
}

$supplied = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$authorized = $supplied !== '' && hash_equals(APP_SETUP_TOKEN, $supplied);

if (!$authorized) {
    http_response_code(401);
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store');
    echo '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>Diagnosa — perlu token</title>'
        . '<style>body{font:16px/1.6 system-ui,sans-serif;max-width:34rem;margin:4rem auto;padding:0 1.25rem}'
        . 'input{font:inherit;padding:.6rem;width:100%;box-sizing:border-box}'
        . 'button{font:inherit;padding:.6rem 1.2rem;margin-top:.75rem;cursor:pointer}</style>'
        . '</head><body><h1>Diagnosa memerlukan token</h1>'
        . '<p>Masukkan nilai <code>APP_SETUP_TOKEN</code> dari berkas <code>.env</code>.</p>'
        . '<form method="post"><input type="password" name="token" autofocus autocomplete="off"'
        . ' aria-label="Token diagnosa"><button type="submit">Buka diagnosa</button></form>'
        . '</body></html>';
    exit;
}

// Koneksi database dicoba terpisah: halaman harus tetap hidup walau DB mati.
$pdo = null;
try {
    $pdo = new PDO(
        sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            env_str('DB_HOST', ''),
            env_str('DB_PORT', '3306'),
            env_str('DB_DATABASE', '')
        ),
        env_str('DB_USERNAME', ''),
        env_str('DB_PASSWORD', ''),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    $pdo->exec('SET time_zone = ' . $pdo->quote((new DateTimeImmutable('now', new DateTimeZone(APP_TIMEZONE)))->format('P')));
} catch (Throwable $e) {
    app_log('warning', 'diagnosa.php gagal terhubung: ' . $e->getMessage());
}

$results = deploy_checks($pdo);
$summary = deploy_check_summary($results);
$state = $summary['failed'] > 0 ? 'gagal' : ($summary['warned'] > 0 ? 'peringatan' : 'siap');

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

$e = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Diagnosa kesiapan deploy</title>
<style>
:root { color-scheme: light dark; }
body { font: 16px/1.6 system-ui, -apple-system, sans-serif; max-width: 52rem; margin: 2.5rem auto; padding: 0 1.25rem; }
h1 { font-size: 1.5rem; margin-bottom: .25rem; }
.sub { opacity: .7; margin-top: 0; }
.ringkas { display: flex; gap: .5rem; flex-wrap: wrap; margin: 1.25rem 0; }
.pill { padding: .35rem .8rem; border-radius: 999px; font-size: .875rem; font-weight: 600; }
.pill--ok { background: #dcfce7; color: #14532d; }
.pill--warn { background: #fef3c7; color: #713f12; }
.pill--fail { background: #fee2e2; color: #7f1d1d; }
ul { list-style: none; padding: 0; }
li { padding: .6rem 0; border-top: 1px solid rgba(128,128,128,.25); }
.tag { display: inline-block; min-width: 5.5rem; font-weight: 700; font-size: .8125rem; }
.tag--ok { color: #15803d; }
.tag--warn { color: #b45309; }
.tag--fail { color: #b91c1c; }
.detail { display: block; opacity: .75; font-size: .875rem; }
footer { margin-top: 2rem; font-size: .875rem; opacity: .7; }
@media (prefers-color-scheme: dark) {
  .pill--ok { background: #14532d; color: #dcfce7; }
  .pill--warn { background: #713f12; color: #fef3c7; }
  .pill--fail { background: #7f1d1d; color: #fee2e2; }
  .tag--ok { color: #4ade80; } .tag--warn { color: #fbbf24; } .tag--fail { color: #f87171; }
}
</style>
</head>
<body>
<h1>Diagnosa kesiapan deploy</h1>
<p class="sub">AL MUTQON Verification &middot; <?= $e(date('Y-m-d H:i:s')) ?> (<?= $e(APP_TIMEZONE) ?>) &middot; PHP <?= $e(PHP_VERSION) ?></p>

<div class="ringkas">
  <span class="pill pill--ok"><?= (int) $summary['passed'] ?> lolos</span>
  <span class="pill pill--warn"><?= (int) $summary['warned'] ?> peringatan</span>
  <span class="pill pill--fail"><?= (int) $summary['failed'] ?> gagal</span>
</div>

<?php if ($summary['failed'] > 0): ?>
  <p><strong>Status: belum siap dipublikasikan.</strong> Perbaiki setiap butir GAGAL lalu muat ulang halaman ini.</p>
<?php elseif ($summary['warned'] > 0): ?>
  <p><strong>Status: siap dengan catatan.</strong> Periksa butir PERINGATAN — aplikasi tetap berjalan.</p>
<?php else: ?>
  <p><strong>Status: siap dipublikasikan.</strong></p>
<?php endif; ?>

<ul>
<?php foreach ($results as $item): ?>
  <?php
  $class = $item['ok'] ? 'ok' : ($item['warn'] ? 'warn' : 'fail');
  $label = $item['ok'] ? 'LOLOS' : ($item['warn'] ? 'PERINGATAN' : 'GAGAL');
  ?>
  <li>
    <span class="tag tag--<?= $class ?>"><?= $label ?></span>
    <?= $e($item['label']) ?>
    <?php if (!$item['ok']): ?><span class="detail"><?= $e($item['detail']) ?></span><?php endif; ?>
  </li>
<?php endforeach; ?>
</ul>

<footer>
  Halaman ini hanya menampilkan status, tidak pernah nilai kredensial.
  Setelah deploy selesai, hapus <code>public/diagnosa.php</code> dari server
  (atau kosongkan <code>APP_SETUP_TOKEN</code>).
</footer>
</body>
</html>
