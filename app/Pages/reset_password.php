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
            } elseif (!password_is_valid($password)) {
                $error = 'Password minimal 8 karakter serta harus mengandung huruf dan angka.';
            } elseif ($password !== $konfirmasi) {
                $error = 'Konfirmasi password tidak cocok.';
            } else {
                $stmt = $pdo->prepare('UPDATE staff SET password = ? WHERE email = ?');
                $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $row['email']]);

                staff_mark_reset_used($pdo, $token);

                $sukses = 'Password berhasil direset. Silakan masuk dengan password baru.';
            }
        }
    } elseif (!$row) {
        $error = 'Token tidak valid, sudah dipakai, atau kedaluwarsa. Minta link baru.';
    }
}
?>
<?php $pageTitleFull = 'Reset Password — AL MUTQON'; include __DIR__ . '/_head.php'; ?>
<main class="page" style="max-width:440px; margin: 80px auto;">
    <div class="panel">
        <div class="panel__hd" style="text-align:center; border-bottom:none; padding-bottom:0">
            <div class="u-reset-brand">
                <span class="u-reset-brand-icon">password</span>
                <div>
                    <div class="u-reset-brand-name">AL MUTQON</div>
                    <div class="u-reset-brand-sub">VERIFICATION SYSTEM</div>
                </div>
            </div>
            <h1 class="u-reset-title">Buat Password Baru</h1>
            <p class="faint u-font-14">Masukkan password baru untuk akun Anda (minimal 8 karakter).</p>
        </div>
        <div class="panel__bd">
            <?php if ($error): ?>
            <div class="alert alert-err u-mb-16">
                <span><?= htmlspecialchars($error) ?></span>
            </div>
            <?php elseif ($sukses): ?>
            <div class="alert alert-ok u-mb-16">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="u-shrink-0"><path d="M5 13l4 4L19 7"/></svg>
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
            <p class="u-reset-footer">
                <a href="staff.php" class="u-reset-link">← Kembali ke halaman masuk</a>
            </p>
        </div>
    </div>
</main>
<script src="assets/nav.js?v=<?= ASSET_VERSION ?>"></script>
<?php include __DIR__ . '/_foot.php'; ?>
