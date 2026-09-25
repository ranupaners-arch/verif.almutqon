<?php
// app/Pages/reset_password.php — formulir reset password staff.
require dirname(__DIR__) . '/Bootstrap.php';

$token = trim($_POST['token'] ?? ($_GET['token'] ?? ''));
$error = '';
$sukses = '';

if ($token === '') {
    $error = 'Link reset tidak lengkap. Silakan meminta link baru.';
} else {
    $row = staff_find_valid_reset($pdo, $token);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verify_csrf()) {
            $error = 'Permintaan tidak valid. Silakan muat ulang halaman dan coba lagi.';
        } elseif (!$row) {
            $error = 'Token tidak valid, sudah dipakai, atau kedaluwarsa. Minta link baru.';
        } else {
            $password = $_POST['password'] ?? '';
            $konfirmasi = $_POST['konfirmasi_password'] ?? '';

            if ($password === '') {
                $error = 'Password baru wajib diisi.';
            } elseif (strlen($password) < 8) {
                $error = 'Password minimal 8 karakter.';
            } elseif ($password !== $konfirmasi) {
                $error = 'Konfirmasi password tidak cocok.';
            } else {
                $stmt = $pdo->prepare('UPDATE staff SET password = ? WHERE email = ?');
                $stmt->execute([password_hash($password, PASSWORD_BCRYPT), $row['email']]);

                staff_mark_reset_used($pdo, $token);

                $sukses = 'Password berhasil direset. Silakan masuk dengan password baru.';
            }
        }
    } elseif (!$row) {
        $error = 'Token tidak valid, sudah dipakai, atau kedaluwarsa. Minta link baru.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset Password — AL MUTQON</title>
<link rel="icon" type="image/png" href="assets/favicon.png?v=20260919b">
    <link rel="shortcut icon" href="assets/favicon.ico?v=20260919b">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="assets/app.css?v=20260926f" rel="stylesheet">
</head>
<body>
<main class="page" style="max-width:440px; margin: 80px auto;">
    <div class="panel">
        <div class="panel__hd" style="text-align:center; border-bottom:none; padding-bottom:0">
            <div style="display:flex; align-items:center; justify-content:center; gap:10px; margin-bottom:12px;">
                <span style="color:var(--forest); font-size:28px;">password</span>
                <div>
                    <div style="font-weight:800; letter-spacing:1px; color:var(--forest); font-size:20px;">AL MUTQON</div>
                    <div style="font-size:10px; letter-spacing:2px; color:var(--ink-faint);">VERIFICATION SYSTEM</div>
                </div>
            </div>
            <h1 style="font-size:22px; margin-bottom:8px;">Buat Password Baru</h1>
            <p class="faint" style="font-size:14px;">Masukkan password baru untuk akun Anda (minimal 8 karakter).</p>
        </div>
        <div class="panel__bd">
            <?php if ($error): ?>
            <div class="alert alert-err" style="margin-bottom:16px">
                <span><?= htmlspecialchars($error) ?></span>
            </div>
            <?php elseif ($sukses): ?>
            <div class="alert alert-ok" style="margin-bottom:16px">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0"><path d="M5 13l4 4L19 7"/></svg>
                <span><?= htmlspecialchars($sukses) ?></span>
            </div>
            <a href="staff.php" class="btn btn-primary btn-block" style="text-align:center; text-decoration:none;">Ke Halaman Masuk</a>
            <?php else: ?>
            <form method="POST" class="stack" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <div class="field">
                    <label class="field__label" for="password">Password Baru</label>
                    <input class="input" type="password" id="password" name="password" placeholder="Minimal 8 karakter" required minlength="8">
                </div>
                <div class="field">
                    <label class="field__label" for="konfirmasi_password">Konfirmasi Password Baru</label>
                    <input class="input" type="password" id="konfirmasi_password" name="konfirmasi_password" placeholder="Ulangi password baru" required minlength="8">
                </div>
                <button type="submit" class="btn btn-primary btn-block">Simpan Password Baru</button>
            </form>
            <?php endif; ?>
            <p style="text-align:center; margin-top:16px; font-size:13px; color:var(--ink-soft);">
                <a href="staff.php" style="color:var(--forest); text-decoration:none;">← Kembali ke halaman masuk</a>
            </p>
        </div>
    </div>
</main>
<script src="assets/nav.js?v=20260926a"></script>
</body>
</html>