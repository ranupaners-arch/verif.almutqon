<?php
// Session Security
require dirname(__DIR__) . '/Bootstrap.php';
$error_msg = '';

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: staff.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf()) {
    $error_msg = 'Permintaan tidak valid. Silakan muat ulang halaman dan coba lagi.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nip'])) {
    $nip = trim($_POST['nip']);
    $password = $_POST['password'];
    
    // Rate Limiting: Max 5 percobaan per 5 menit
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM activity_log WHERE action = 'login_failed' AND ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
    $stmt->execute([$_SERVER['REMOTE_ADDR']]);
    $failed_attempts = $stmt->fetchColumn();
    
    if ($failed_attempts >= 5) {
        $error_msg = 'Terlalu banyak percobaan login. Coba lagi dalam 5 menit.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM staff WHERE nip = ? AND is_active = 1");
        $stmt->execute([$nip]);
        $staff = $stmt->fetch();
        
        if ($staff && password_verify($password, $staff['password'])) {
            session_regenerate_id(true);
            $_SESSION['is_staff'] = true;
            $_SESSION['staff_id'] = $staff['id'];
            $_SESSION['staff_nama'] = $staff['nama_lengkap'];
            $_SESSION['staff_jabatan'] = $staff['jabatan'];
            $pdo->prepare("INSERT INTO activity_log (staff_id, action, description, ip_address) VALUES (?, 'login', 'Staff login berhasil', ?)")->execute([$staff['id'], $_SERVER['REMOTE_ADDR']]);
            header("Location: dashboard.php");
            exit;
        } else {
            $pdo->prepare("INSERT INTO activity_log (staff_id, action, description, ip_address) VALUES (NULL, 'login_failed', 'Percobaan login gagal', ?)")->execute([$_SERVER['REMOTE_ADDR']]);
            $error_msg = 'NIP atau kata sandi salah!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Portal Staff - AL MUTQON</title>
<link rel="icon" type="image/png" href="assets/favicon.png?v=20260919b">
    <link rel="shortcut icon" href="assets/favicon.ico?v=20260919b">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="assets/app.css?v=20260919l" rel="stylesheet">
</head>
<body>
<div class="login">
    <aside class="login__brand">
        <div class="login__mark">
            <img src="assets/1.png" alt="Logo AL MUTQON" class="login__logo">
            <div>
                <p class="login__tag">Sistem Validasi Dokumen Digital</p>
                <h1 class="login__org">AL MUTQON<br>Arabic Learning</h1>
            </div>
            <p class="login__desc">Pengesahan dan verifikasi keaslian dokumen resmi lembaga secara digital, dilindungi validasi kriptografi.</p>
        </div>
        <div class="login__footer">&copy; 2026 AL MUTQON Technology &bull; Validasi HMAC-SHA256</div>
    </aside>
    <main class="login__form">
        <div class="login__card">
            <div class="tabs" style="margin-bottom:26px">
                <a href="index.php" class="tab-btn">Cek Dokumen</a>
                <a href="index.php#scan" class="tab-btn">Scan QR</a>
                <a href="staff.php" class="tab-btn tab-active">Portal Staff</a>
            </div>
            <div class="panel">
                <div class="panel__hd" style="border-top:3px solid var(--gold)">
                    <h2 class="panel__title">Selamat Datang</h2>
                </div>
                <div class="panel__bd">
                    <p class="faint" style="font-size:13px;margin-bottom:18px">Masuk ke portal manajemen dan pengesahan dokumen.</p>
                    <?php if ($error_msg): ?><div class="alert alert-err"><?= htmlspecialchars($error_msg) ?></div><?php endif; ?>
                    <form method="POST" class="stack">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                        <div class="field">
                            <label class="field__label" for="f-nip">ID Pengajar</label>
                            <input class="input" id="f-nip" type="text" name="nip" placeholder="Masukkan NIP Anda" required>
                        </div>
                        <div class="field">
                            <label class="field__label" for="f-pass">Kata Sandi</label>
                            <input class="input" id="f-pass" type="password" name="password" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;" required>
                        </div>
                        <button class="btn btn-primary btn-block" type="submit">Masuk ke Sistem</button>
                    </form>
                    <p class="faint textc" style="font-size:12px;margin-top:12px">
                        <a href="lupa_password.php" style="color:var(--forest); text-decoration:none;">Lupa kata sandi?</a>
                    </p>
                    <p class="faint textc" style="font-size:12px;margin-top:8px">Butuh bantuan? Hubungi administrator sistem.</p>
                </div>
            </div>
        </div>
    </main>
</div>
</body>
</html>