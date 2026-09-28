<?php
require dirname(__DIR__) . '/Bootstrap.php';

function redirect_to_staff_portal(?string $errorMessage = null): never
{
    if ($errorMessage !== null) {
        $_SESSION['staff_login_error'] = $errorMessage;
    }

    header('Location: index.php#staff', true, 303);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'logout') {
    if (!verify_csrf()) {
        redirect_to_staff_portal('Permintaan keluar tidak valid. Silakan coba lagi.');
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    redirect_to_staff_portal();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to_staff_portal();
}

if (!verify_csrf()) {
    redirect_to_staff_portal('Permintaan tidak valid. Silakan muat ulang halaman dan coba lagi.');
}

$nip = trim((string) ($_POST['nip'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
$clientIp = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

if ($nip === '' || $password === '') {
    redirect_to_staff_portal('ID pengguna dan kata sandi wajib diisi.');
}

if (!rate_limit_ok($pdo, 'login_failed', $clientIp, MAX_LOGIN_ATTEMPTS, LOGIN_RATE_WINDOW_SECONDS)) {
    redirect_to_staff_portal('Terlalu banyak percobaan masuk. Silakan coba lagi nanti.');
}

$stmt = $pdo->prepare('SELECT * FROM staff WHERE nip = ? AND is_active = 1');
$stmt->execute([$nip]);
$staff = $stmt->fetch();

if (!$staff || !password_verify($password, $staff['password'])) {
    $pdo->prepare("INSERT INTO activity_log (staff_id, action, description, ip_address) VALUES (NULL, 'login_failed', 'Percobaan login gagal', ?)")
        ->execute([$clientIp]);
    redirect_to_staff_portal('ID pengguna atau kata sandi salah.');
}

session_regenerate_id(true);
$_SESSION['is_staff'] = true;
$_SESSION['staff_id'] = $staff['id'];
$_SESSION['staff_nama'] = $staff['nama_lengkap'];
$_SESSION['staff_jabatan'] = $staff['jabatan'];
$pdo->prepare("INSERT INTO activity_log (staff_id, action, description, ip_address) VALUES (?, 'login', 'Staff login berhasil', ?)")
    ->execute([$staff['id'], $clientIp]);

header('Location: dashboard.php', true, 303);
exit;
