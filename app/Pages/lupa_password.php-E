<?php
// app/Pages/lupa_password.php — permintaan reset password staff.
require dirname(__DIR__) . '/Bootstrap.php';

$kirim_ok = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Permintaan tidak valid. Silakan muat ulang halaman dan coba lagi.';
    } else {
        $email = trim($_POST['email'] ?? '');
        if ($email === '') {
            $error = 'Email wajib diisi.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Format email tidak valid.';
        } else {
            // Alur sama untuk email terdaftar maupun tidak (hindari user enumeration)
            $stmt = $pdo->prepare('SELECT id FROM staff WHERE email = ? AND is_active = 1');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $token = staff_store_reset_token($pdo, $email);
                $host = ($_SERVER['HTTP_HOST'] ?? 'verif.almutqon.test');
                $resetUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
                    . '://' . $host . '/reset_password.php?token=' . $token;

                $subject = 'Reset Password Staff AL MUTQON';
                $body = "Anda meminta reset password akun staff AL MUTQON.\n\n"
                    . "Klik link berikut untuk membuat password baru:\n{$resetUrl}\n\n"
                    . "Link berlaku 30 menit. Abaikan email ini jika bukan Anda yang meminta.\n";

                // Kirim via SMTP (config/mail.php belum ada di verif). JANGAN pernah tampilkan link reset di layar.
                // Jika SMTP gagal, link hanya dicatat ke log server untuk keperluan pengembangan.
                $mail = send_mail($email, $subject, $body);
                if (!$mail['ok']) {
                    error_log('[mail] ' . $mail['error']);
                    if (getenv('APP_DEBUG') === 'true') {
                        error_log('[reset-link-debug] ' . $resetUrl);
                    }
                }
            }
            $kirim_ok = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Lupa Password — AL MUTQON</title>
<link rel="icon" type="image/png" href="assets/favicon.png?v=20260919b">
    <link rel="shortcut icon" href="assets/favicon.ico?v=20260919b">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="assets/app.css?v=20260919l" rel="stylesheet">
</head>
<body>
<main class="page" style="max-width:440px; margin: 80px auto;">
    <div class="panel">
        <div class="panel__hd" style="text-align:center; border-bottom:none; padding-bottom:0">
            <div style="display:flex; align-items:center; justify-content:center; gap:10px; margin-bottom:12px;">
                <span style="color:var(--forest); font-size:28px;">lock_reset</span>
                <div>
                    <div style="font-weight:800; letter-spacing:1px; color:var(--forest); font-size:20px;">AL MUTQON</div>
                    <div style="font-size:10px; letter-spacing:2px; color:var(--ink-faint);">VERIFICATION SYSTEM</div>
                </div>
            </div>
            <h1 style="font-size:22px; margin-bottom:8px;">Reset Password</h1>
            <p class="faint" style="font-size:14px;">Masukkan email terdaftar. Jika email Anda ada di sistem, kami kirim link untuk membuat password baru (berlaku 30 menit).</p>
        </div>
        <div class="panel__bd">
            <?php if ($kirim_ok): ?>
            <div class="alert alert-ok" style="margin-bottom:16px">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0"><path d="M5 13l4 4L19 7"/></svg>
                <span>Jika email terdaftar, link reset sudah dikirim. Cek inbox Anda (termasuk folder spam).</span>
            </div>
            <?php elseif ($error): ?>
            <div class="alert alert-err" style="margin-bottom:16px">
                <span><?= htmlspecialchars($error) ?></span>
            </div>
            <?php endif; ?>

            <form method="POST" class="stack" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <div class="field">
                    <label class="field__label" for="email">Alamat Email</label>
                    <input class="input" type="email" id="email" name="email" placeholder="nama@email.com" required autofocus>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Kirim Link Reset</button>
            </form>
            <p style="text-align:center; margin-top:16px; font-size:13px; color:var(--ink-soft);">
                <a href="staff.php" style="color:var(--forest); text-decoration:none;">← Kembali ke halaman masuk</a>
            </p>
        </div>
    </div>
</main>
</body>
</html>