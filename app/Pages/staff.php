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

if (isset($_GET['logout'])) {
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

if ($nip === '' || $password === '') {
    redirect_to_staff_portal('ID pengguna dan kata sandi wajib diisi.');
}

$stmt = $pdo->prepare('SELECT * FROM staff WHERE nip = ? AND is_active = 1');
$stmt->execute([$nip]);
$staff = $stmt->fetch();

if (!$staff || !password_verify($password, $staff['password'])) {
    $pdo->prepare("INSERT INTO activity_log (staff_id, action, description, ip_address) VALUES (NULL, 'login_failed', 'Percobaan login gagal', ?)")
        ->execute([$_SERVER['REMOTE_ADDR']]);
    redirect_to_staff_portal('ID pengguna atau kata sandi salah.');
}

session_regenerate_id(true);
$_SESSION['is_staff'] = true;
$_SESSION['staff_id'] = $staff['id'];
$_SESSION['staff_nama'] = $staff['nama_lengkap'];
$_SESSION['staff_jabatan'] = $staff['jabatan'];
$pdo->prepare("INSERT INTO activity_log (staff_id, action, description, ip_address) VALUES (?, 'login', 'Staff login berhasil', ?)")
    ->execute([$staff['id'], $_SERVER['REMOTE_ADDR']]);

header('Location: dashboard.php', true, 303);
exit;
