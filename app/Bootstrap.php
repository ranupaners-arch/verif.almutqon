<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

// Konfigurasi dimuat berurutan: env (pembacaan) -> app (validasi) -> paths (lokasi).
require_once __DIR__ . '/Config/app.php';
require_once __DIR__ . '/Config/paths.php';

$requestIsHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

if (PHP_SAPI !== 'cli') {
    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(self), microphone=(), geolocation=()');
    if (APP_ENV === 'production' && $requestIsHttps) {
        header('Strict-Transport-Security: max-age=31536000');
    }
}

session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure' => APP_ENV === 'production' || $requestIsHttps,
    'use_strict_mode' => true,
    'gc_maxlifetime' => 1800,
]);

require BASE_PATH . '/app/Config/database.php';

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(): bool
{
    return isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], (string) $_POST['csrf_token']);
}

function password_is_valid(string $password): bool
{
    return strlen($password) >= 8
        && preg_match('/[A-Za-z]/', $password)
        && preg_match('/[0-9]/', $password);
}

/**
 * Ringkasan akun staff untuk menu akun masthead + tema halaman.
 * Selalu baca terbaru dari database (bukan snapshot session).
 */
function akun_ringkas(PDO $pdo, int $staffId): array
{
    $stmt = $pdo->prepare('SELECT nama_lengkap, jabatan, avatar, theme, role, nip, email FROM staff WHERE id = ? LIMIT 1');
    $stmt->execute([$staffId]);
    $r = $stmt->fetch() ?: [];
    $theme = (string) ($r['theme'] ?? 'light');
    if (!in_array($theme, ['light', 'dark', 'system'], true)) {
        $theme = 'light';
    }
    return [
        'nama' => (string) ($r['nama_lengkap'] ?? ''),
        'jabatan' => (string) ($r['jabatan'] ?? ''),
        'avatar' => (string) ($r['avatar'] ?? ''),
        'theme' => $theme,
        'role' => (string) ($r['role'] ?? 'staff'),
        'nip' => (string) ($r['nip'] ?? ''),
        'email' => (string) ($r['email'] ?? ''),
    ];
}

/**
 * Rate limit generik berbasis activity_log: hitung entri `action` untuk
 * `identifier` (IP) dalam jendela `windowSeconds` detik.
 */
function rate_limit_ok(PDO $pdo, string $action, string $identifier, int $maxAttempts, int $windowSeconds): bool
{
    $cutoff = date('Y-m-d H:i:s', time() - $windowSeconds);
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM activity_log WHERE action = ? AND ip_address = ? AND created_at > ?');
    $stmt->execute([$action, $identifier, $cutoff]);

    return (int) $stmt->fetchColumn() < $maxAttempts;
}

function rate_limit_record(PDO $pdo, string $action, string $identifier, string $description): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO activity_log (staff_id, system_actor_id, action, dokumen_id, description, ip_address)
         VALUES (NULL, NULL, ?, NULL, ?, ?)'
    );
    $stmt->execute([$action, $description, $identifier]);
}

/**
 * Lokasi fisik file PDF dari nilai `pdf_path` di database.
 *
 * Nilai normal hanya berisi nama file (disimpan di storage/uploads).
 * Untuk kompatibilitas PDF lama, ikut mencari di lokasi legacy
 * (public/uploads, public/, storage/) sambil membatasi traversal ke
 * direktori yang diizinkan.
 */
function resolve_pdf_path(?string $pdf_path): ?string
{
    $raw = trim((string) $pdf_path);
    if ($raw === '') {
        return null;
    }

    $allowedBases = [];
    foreach ([UPLOAD_DIR, PUBLIC_PATH] as $base) {
        $real = realpath($base);
        if ($real !== false) {
            $allowedBases[] = $real;
        }
    }

    $candidates = [];
    $basename = basename($raw);
    $candidates[] = UPLOAD_DIR . '/' . $basename;
    $candidates[] = PUBLIC_PATH . '/uploads/' . $basename;
    $candidates[] = PUBLIC_PATH . '/' . $basename;
    if ($basename !== $raw) {
        $candidates[] = STORAGE_PATH . '/' . $raw;
        $candidates[] = PUBLIC_PATH . '/' . $raw;
        $candidates[] = BASE_PATH . '/' . $raw;
        $candidates[] = UPLOAD_DIR . '/' . $raw;
        $candidates[] = PUBLIC_PATH . '/uploads/' . $raw;
    }

    foreach ($candidates as $candidate) {
        $candidate = str_replace('\\', '/', $candidate);
        if (!is_file($candidate)) {
            continue;
        }
        $real = realpath($candidate);
        if ($real === false) {
            continue;
        }
        foreach ($allowedBases as $allowed) {
            if (str_starts_with($real, $allowed . '/')) {
                return $real;
            }
        }
    }

    return null;
}

/**
 * Simpan token reset password staff (hapus token lama email tsb), kembalikan token hex.
 */
function staff_store_reset_token(PDO $pdo, string $email): string
{
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $stmt = $pdo->prepare('DELETE FROM password_resets WHERE email = ?');
    $stmt->execute([$email]);

    $stmt = $pdo->prepare(
        'INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))'
    );
    $stmt->execute([$email, $tokenHash]);

    return $token;
}

/**
 * Cari token reset staff yang valid (ada, belum dipakai, belum kedaluwarsa).
 */
function staff_find_valid_reset(PDO $pdo, string $token): array|false
{
    if (!ctype_xdigit($token) || strlen($token) !== 64) {
        return false;
    }

    $stmt = $pdo->prepare(
        'SELECT id, email FROM password_resets
         WHERE token = ? AND used = 0 AND expires_at > NOW()
         ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([hash('sha256', $token)]);
    return $stmt->fetch();
}

/**
 * Tandai token reset staff sudah dipakai (sekali pakai).
 */
function staff_mark_reset_used(PDO $pdo, string $token): void
{
    $stmt = $pdo->prepare('UPDATE password_resets SET used = 1 WHERE token = ?');
    $stmt->execute([hash('sha256', $token)]);
}

/**
 * Kirim email melalui mail transport server. MAIL_ENABLED harus aktif.
 * Mengembalikan ['ok' => bool, 'error' => string].
 */
function send_mail(string $to, string $subject, string $body): array
{
    if (!filter_var(env_get('MAIL_ENABLED', 'false'), FILTER_VALIDATE_BOOL)) {
        return ['ok' => false, 'error' => 'Mail transport dinonaktifkan'];
    }

    $from = trim((string) (env_get('MAIL_FROM', '')));
    $fromName = trim((string) (env_get('MAIL_FROM_NAME', 'AL MUTQON Verification')));
    if (!filter_var($to, FILTER_VALIDATE_EMAIL) || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Alamat email tidak valid'];
    }

    $safeFromName = str_replace(["\r", "\n"], '', $fromName);
    $headers = [
        'From: ' . $safeFromName . ' <' . $from . '>',
        'Content-Type: text/plain; charset=UTF-8',
        'X-Mailer: PHP/' . PHP_VERSION,
    ];

    $sent = mail($to, $subject, $body, implode("\r\n", $headers));
    return $sent
        ? ['ok' => true, 'error' => '']
        : ['ok' => false, 'error' => 'Mail transport gagal mengirim pesan'];
}

/**
 * Waktu relatif dalam Bahasa Indonesia untuk tampilan "Aktivitas Terbaru".
 * Contoh: "baru saja", "5 menit lalu", "3 jam lalu", "2 hari lalu".
 * Lebih lama dari seminggu → tanggal "d M Y".
 */
function waktu_relatif(string $datetime): string
{
    try {
        $ts = (new DateTime($datetime))->getTimestamp();
    } catch (Exception $e) {
        return '-';
    }
    $diff = time() - $ts;
    if ($diff < 60) {
        return 'baru saja';
    }
    if ($diff < 3600) {
        return ((int) floor($diff / 60)) . ' menit lalu';
    }
    if ($diff < 86400) {
        return ((int) floor($diff / 3600)) . ' jam lalu';
    }
    if ($diff < 86400 * 7) {
        return ((int) floor($diff / 86400)) . ' hari lalu';
    }
    return date('d M Y', $ts);
}
