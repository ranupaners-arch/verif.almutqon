<?php
// app/Pages/lupa_password.php — permintaan reset password staff.
require dirname(__DIR__) . '/Bootstrap.php';

$kirim_ok = false;
$error = '';
$mailEnabled = filter_var(env_get('MAIL_ENABLED', 'false'), FILTER_VALIDATE_BOOL);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clientIp = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    if (!verify_csrf()) {
        $error = 'Permintaan tidak valid. Silakan muat ulang halaman dan coba lagi.';
    } elseif (!$mailEnabled) {
        $error = 'Layanan reset melalui email belum tersedia. Silakan hubungi administrator.';
    } elseif (!rate_limit_ok($pdo, 'password_reset_request', $clientIp, MAX_RESET_ATTEMPTS, RESET_RATE_WINDOW_SECONDS)) {
        $error = 'Terlalu banyak permintaan reset. Silakan coba lagi nanti.';
    } else {
        rate_limit_record($pdo, 'password_reset_request', $clientIp, 'Permintaan reset password');
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
                $resetUrl = APP_URL . '/reset_password.php?token=' . rawurlencode($token);

                $subject = 'Reset Password Staff AL MUTQON';
                $body = "Anda meminta reset password akun staff AL MUTQON.\n\n"
                    . "Klik link berikut untuk membuat password baru:\n{$resetUrl}\n\n"
                    . "Link berlaku 30 menit. Abaikan email ini jika bukan Anda yang meminta.\n";

                $mail = send_mail($email, $subject, $body);
                if (!$mail['ok']) {
                    error_log('[mail] Pengiriman reset password gagal: ' . $mail['error']);
                }
            }
            $kirim_ok = true;
        }
    }
}
?>
<?php $pageTitleFull = 'Lupa Password — AL MUTQON'; include __DIR__ . '/_head.php'; ?>
<main class="page" style="max-width:440px; margin: 80px auto;">
    <div class="panel">
        <div class="panel__hd" style="text-align:center; border-bottom:none; padding-bottom:0">
            <div class="u-reset-brand">
                <span class="u-reset-brand-icon">lock_reset</span>
                <div>
                    <div class="u-reset-brand-name">AL MUTQON</div>
                    <div class="u-reset-brand-sub">VERIFICATION SYSTEM</div>
                </div>
            </div>
            <h1 class="u-reset-title">Reset Password</h1>
            <p class="faint u-font-14">Masukkan email terdaftar. Jika email Anda ada di sistem, kami kirim link untuk membuat password baru (berlaku 30 menit).</p>
        </div>
        <div class="panel__bd">
            <?php if ($kirim_ok): ?>
            <div class="alert alert-ok u-mb-16">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="u-shrink-0"><path d="M5 13l4 4L19 7"/></svg>
                <span>Jika email terdaftar, link reset sudah dikirim. Cek inbox Anda (termasuk folder spam).</span>
            </div>
            <?php elseif ($error): ?>
            <div class="alert alert-err u-mb-16">
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
            <p class="u-reset-footer">
                <a href="staff.php" class="u-reset-link">← Kembali ke halaman masuk</a>
            </p>
        </div>
    </div>
</main>
<?php include __DIR__ . '/_foot.php'; ?>
