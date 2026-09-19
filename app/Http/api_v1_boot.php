<?php
declare(strict_types=1);

// app/Http/api_v1_boot.php — fondasi bersama endpoint API internal v1.
// Menyediakan: envelope JSON, auth Bearer (VERIF_API_TOKEN), input gabungan
// JSON+POST, IP klien, dan service-account (VERIF_API_STAFF_NIP).
// Dipakai via require dari tiap endpoint (dokumen.php, staf.php, ...).
// Keluar sendiri dengan 405/401 bila metode/auth tak sah.

require dirname(__DIR__) . '/Bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

function api_respond(int $code, array $payload): void
{
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

function api_bearer(): string
{
    $h = '';
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        foreach ($headers as $k => $v) {
            if (strcasecmp((string) $k, 'Authorization') === 0) {
                $h = (string) $v;
                break;
            }
        }
    }
    if ($h === '') {
        $h = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    }
    if (stripos($h, 'Bearer ') === 0) {
        return trim(substr($h, 7));
    }
    return '';
}

// Gabung body JSON (bila ada) di atas $_POST agar klien boleh pakai keduanya.
function api_input(): array
{
    $input = $_POST;
    $contentType = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        if ($raw !== '' && $raw !== false) {
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                api_respond(400, ['success' => false, 'message' => 'Body JSON tidak valid.']);
            }
            $input = $decoded + $input;
        }
    }
    return $input;
}

function api_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? 'cli');
}

// Service-account khusus API: wajib ada & aktif, selain itu salah konfigurasi server.
function api_service_account(PDO $pdo): array
{
    $staffNip = (string) (getenv('VERIF_API_STAFF_NIP') ?: 'SYSTEM-ALMUTQON');
    $stmt = $pdo->prepare('SELECT id, nama_lengkap, jabatan, is_active FROM staff WHERE nip = ? LIMIT 1');
    $stmt->execute([$staffNip]);
    $svc = $stmt->fetch();
    if (!$svc || (int) ($svc['is_active'] ?? 0) !== 1) {
        error_log('API v1: service-account "' . $staffNip . '" tidak ada / nonaktif.');
        api_respond(500, ['success' => false, 'message' => 'Layanan verifikasi belum siap.']);
    }
    return $svc;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_respond(405, ['success' => false, 'message' => 'Metode tidak diizinkan.']);
}

$expected = (string) (getenv('VERIF_API_TOKEN') ?: '');
if ($expected === '' || !hash_equals($expected, api_bearer())) {
    api_respond(401, ['success' => false, 'message' => 'Tidak terautentikasi.']);
}

// Rate limiting per IP untuk API v1
if (!rate_limit_ok($pdo, 'api_v1_request', api_ip(), MAX_API_ATTEMPTS, API_RATE_WINDOW_SECONDS)) {
    api_respond(429, ['success' => false, 'message' => 'Terlalu banyak permintaan. Silakan coba lagi nanti.']);
}
