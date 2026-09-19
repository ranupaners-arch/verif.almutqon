<?php
// public/api/theme.php — simpan tema per akun (light|dark). Dipanggil via fetch
// dari switch tema di menu akun. Selalu JSON; butuh login + CSRF.
require dirname(__DIR__, 2) . '/app/Bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['is_staff'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Tidak terautentikasi.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metode tidak diizinkan.']);
    exit;
}

$body = $_POST;
if (stripos((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $dec = ($raw !== '' && $raw !== false) ? json_decode($raw, true) : null;
    if (is_array($dec)) {
        $body = $dec;
    }
}

if (!verify_csrf()) {
    // verify_csrf membaca $_POST; untuk body JSON, terima token di field juga.
    $tok = (string) ($body['csrf_token'] ?? '');
    if ($tok === '' || !isset($_SESSION['csrf_token']) || !hash_equals((string) $_SESSION['csrf_token'], $tok)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Token tidak valid.']);
        exit;
    }
}

$theme = (string) ($body['theme'] ?? '');
if (!in_array($theme, ['light', 'dark', 'system'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Tema tidak dikenali.']);
    exit;
}

$pdo->prepare('UPDATE staff SET theme = ? WHERE id = ?')->execute([$theme, (int) $_SESSION['staff_id']]);
echo json_encode(['success' => true, 'theme' => $theme]);
