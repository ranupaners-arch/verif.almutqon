<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
define('STORAGE_PATH', BASE_PATH . '/storage');

$envFile = BASE_PATH . '/.env';
if (is_file($envFile)) {
    $environment = parse_ini_file($envFile, false, INI_SCANNER_RAW);
    if (is_array($environment)) {
        foreach ($environment as $key => $value) {
            if (getenv((string) $key) === false) {
                putenv($key . '=' . $value);
            }
        }
    }
}

define('APP_URL', rtrim(getenv('APP_URL') ?: 'http://localhost', '/'));
define('APP_SECRET_KEY', getenv('APP_SECRET_KEY') ?: 'local-development-key-change-before-production');
define('MAX_UPLOAD_BYTES', 10 * 1024 * 1024);
define('UPLOAD_RATE_WINDOW_SECONDS', 600);
define('MAX_UPLOAD_ATTEMPTS', 15);
define('API_RATE_WINDOW_SECONDS', 60);
define('MAX_API_ATTEMPTS', 60);

session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
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
    foreach ([STORAGE_PATH . '/uploads', BASE_PATH . '/public'] as $base) {
        $real = realpath($base);
        if ($real !== false) {
            $allowedBases[] = $real;
        }
    }

    $candidates = [];
    $basename = basename($raw);
    $candidates[] = STORAGE_PATH . '/uploads/' . $basename;
    $candidates[] = BASE_PATH . '/public/uploads/' . $basename;
    $candidates[] = BASE_PATH . '/public/' . $basename;
    if ($basename !== $raw) {
        $candidates[] = STORAGE_PATH . '/' . $raw;
        $candidates[] = BASE_PATH . '/public/' . $raw;
        $candidates[] = BASE_PATH . '/' . $raw;
        $candidates[] = STORAGE_PATH . '/uploads/' . $raw;
        $candidates[] = BASE_PATH . '/public/uploads/' . $raw;
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
    $stmt = $pdo->prepare('DELETE FROM password_resets WHERE email = ?');
    $stmt->execute([$email]);

    $stmt = $pdo->prepare(
        'INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))'
    );
    $stmt->execute([$email, $token]);

    return $token;
}

/**
 * Cari token reset staff yang valid (ada, belum dipakai, belum kedaluwarsa).
 */
function staff_find_valid_reset(PDO $pdo, string $token): array|false
{
    $stmt = $pdo->prepare(
        'SELECT id, email FROM password_resets
         WHERE token = ? AND used = 0 AND expires_at > NOW()
         ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([$token]);
    return $stmt->fetch();
}

/**
 * Tandai token reset staff sudah dipakai (sekali pakai).
 */
function staff_mark_reset_used(PDO $pdo, string $token): void
{
    $stmt = $pdo->prepare('UPDATE password_resets SET used = 1 WHERE token = ?');
    $stmt->execute([$token]);
}

/**
 * Stub pengiriman email (diganti implementasi SMTP nyata bila diperlukan).
 * Mengembalikan ['ok' => bool, 'error' => string].
 */
function send_mail(string $to, string $subject, string $body): array
{
    // Jika ada konfigurasi SMTP di .env (MAIL_HOST, dll), implementasikan di sini.
    // Untuk sekarang hanya log ke error_log.
    error_log("[mail] TO: $to | SUBJECT: $subject");
    if (getenv('APP_DEBUG') === 'true') {
        error_log("[mail] BODY: $body");
    }
    return ['ok' => false, 'error' => 'SMTP not configured'];
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
