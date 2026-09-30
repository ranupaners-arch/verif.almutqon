<?php
// app/Pages/profil.php — profil staff: avatar, info akun, keamanan.
// Akses: staff login (semua role).
require dirname(__DIR__) . '/Bootstrap.php';

if (!isset($_SESSION['is_staff'])) {
    header('Location: staff.php');
    exit;
}

$staff_id = (int) ($_SESSION['staff_id'] ?? 0);
$akun = akun_ringkas($pdo, $staff_id);
$roleLabel = ['direktur' => 'Direktur', 'admin' => 'Admin', 'staff' => 'Staff'][$akun['role']] ?? $akun['role'];
$roleBadge = ['direktur' => 'badge-gold', 'admin' => 'badge-blue', 'staff' => 'badge-ok'][$akun['role']] ?? 'badge-muted';

// Waktu terakhir ganti password.
$stmt = $pdo->prepare("SELECT created_at FROM activity_log WHERE staff_id = ? AND action = 'change_password' ORDER BY id DESC LIMIT 1");
$stmt->execute([$staff_id]);
$lastPw = $stmt->fetchColumn();
$lastPwText = $lastPw ? date('d M Y H:i', strtotime((string) $lastPw)) : 'Belum pernah';

$msg = ''; $msg_type = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf()) {
    $msg = 'Permintaan tidak valid. Silakan muat ulang halaman dan coba lagi.'; $msg_type = 'error';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = (string) ($_POST['aksi'] ?? '');
    if ($aksi === 'simpan_nama') {
        $nama = trim((string) ($_POST['nama_lengkap'] ?? ''));
        if ($nama === '' || mb_strlen($nama) > 255) {
            $msg = 'Nama lengkap wajib diisi dan maksimal 255 karakter.'; $msg_type = 'error';
        } else {
            $pdo->prepare('UPDATE staff SET nama_lengkap = ? WHERE id = ?')->execute([$nama, $staff_id]);
            $pdo->prepare("INSERT INTO activity_log (staff_id, action, description, ip_address) VALUES (?, 'change_name', 'Mengubah nama sendiri', ?)")
                ->execute([$staff_id, $_SERVER['REMOTE_ADDR']]);
            $akun['nama'] = $nama;
            $_SESSION['staff_nama'] = $nama;
            $msg = 'Nama profil berhasil diperbarui.'; $msg_type = 'success';
        }
    } elseif ($aksi === 'simpan_email') {
        $email_baru = trim((string) ($_POST['email_baru'] ?? ''));
        $pass_lama = (string) ($_POST['password_lama'] ?? '');
        if (!filter_var($email_baru, FILTER_VALIDATE_EMAIL) || mb_strlen($email_baru) > 255) {
            $msg = 'Format email tidak valid.'; $msg_type = 'error';
        } elseif ($pass_lama === '') {
            $msg = 'Password saat ini wajib diisi.'; $msg_type = 'error';
        } else {
            $stmt = $pdo->prepare('SELECT email, password FROM staff WHERE id = ?');
            $stmt->execute([$staff_id]);
            $row = $stmt->fetch();
            if (!$row || !password_verify($pass_lama, $row['password'])) {
                $msg = 'Password saat ini salah. Silakan coba lagi.'; $msg_type = 'error';
            } else {
                $dup = $pdo->prepare('SELECT id FROM staff WHERE email = ? AND id != ?');
                $dup->execute([$email_baru, $staff_id]);
                if ($dup->fetch()) {
                    $msg = 'Email sudah digunakan staff lain.'; $msg_type = 'error';
                } else {
                    $pdo->prepare('UPDATE staff SET email = ? WHERE id = ?')->execute([$email_baru, $staff_id]);
                    $pdo->prepare("INSERT INTO activity_log (staff_id, action, description, ip_address) VALUES (?, 'change_email', 'Mengubah email sendiri', ?)")
                        ->execute([$staff_id, $_SERVER['REMOTE_ADDR']]);
                    $akun['email'] = $email_baru;
                    $msg = 'Email berhasil diperbarui.'; $msg_type = 'success';
                }
            }
        }
    } else {
        $msg = 'Permintaan tidak dikenali.'; $msg_type = 'error';
    }
}

$pageTitle = 'Profil Saya';
$pageTitleFull = null;
$navActive = 'profil';
?>
<?php $bodyClass = 'profile-page'; include __DIR__ . '/_head.php'; ?>

<?php include __DIR__ . '/_sidebar.php'; ?>
<?php include __DIR__ . '/_masthead.php'; ?>

<div style="max-width:680px;margin:0 auto;width:100%;display:flex;flex-direction:column;justify-content:center;min-height:calc(100vh - 180px);padding:28px 0 56px;">
    <?php if ($msg): ?>
    <div class="alert <?= $msg_type === 'success' ? 'alert-ok' : 'alert-err' ?>" style="margin-bottom:24px">
        <span><?= htmlspecialchars($msg) ?></span>
    </div>
    <?php endif; ?>

    <section class="profile-card">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:20px;">
            <div>
                <div class="profile-card__title">Profil</div>
                <p class="profile-card__sub">Foto dan nama untuk akun dashboard Anda.</p>
            </div>
            <span class="badge <?= $roleBadge ?>"><?= htmlspecialchars($roleLabel) ?></span>
        </div>
        <div style="display:flex;align-items:center;gap:16px;margin-bottom:20px;">
            <span style="width:72px;height:72px;border-radius:50%;overflow:hidden;flex:none;display:inline-flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#166534,#14532D);color:#fff;font-size:28px;font-weight:800;">
                <?php if ($akun['avatar'] !== ''): ?>
                    <img src="<?= htmlspecialchars($akun['avatar']) ?>" alt="Avatar" style="width:100%;height:100%;object-fit:cover;">
                <?php else: ?>
                    <?= htmlspecialchars(mb_strtoupper(mb_substr($akun['nama'], 0, 1))) ?>
                <?php endif; ?>
            </span>
            <div>
                <strong style="font-size:17px;"><?= htmlspecialchars($akun['nama']) ?></strong>
                <p class="profile-card__sub" style="margin:3px 0 0;"><?= htmlspecialchars($akun['jabatan']) ?> &bull; <?= htmlspecialchars($roleLabel) ?></p>
            </div>
        </div>

        <form method="POST" action="api/avatar.php" enctype="multipart/form-data" class="row" style="gap:12px;align-items:flex-start;flex-wrap:wrap">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="aksi" value="simpan">
            <input type="hidden" name="kembali" value="profil.php">
            <div class="u-flex-field">
                <label class="field__label" for="avatar-file-profil">Ganti Avatar</label>
                <input class="input" id="avatar-file-profil" type="file" name="avatar" accept=".jpg,.jpeg,.png,.webp" required>
                <p class="faint u-font-12 u-mt-6">JPG, PNG, atau WebP, maksimal 1 MB.</p>
            </div>
            <button type="submit" class="btn btn-primary" style="flex:0 0 auto;flex-shrink:0;width:fit-content;min-width:0;white-space:nowrap;position:relative;top:30px;">Simpan Avatar</button>
        </form>

        <form method="POST" class="row" style="gap:12px;align-items:end;flex-wrap:wrap;margin-top:20px">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="aksi" value="simpan_nama">
            <div class="u-flex-field">
                <label class="field__label" for="nama-lengkap">Nama Lengkap</label>
                <input class="input" id="nama-lengkap" type="text" name="nama_lengkap" value="<?= htmlspecialchars($akun['nama']) ?>" maxlength="255" required>
            </div>
            <button type="submit" class="btn btn-primary u-shrink-0">Simpan Nama</button>
        </form>
    </section>

    <section class="profile-card">
        <div class="profile-card__title u-mb-16">Info Akun</div>
        <div class="profile-card__table">
            <div class="profile-card__row"><span>ID Pengguna / NIP</span><code><?= htmlspecialchars($akun['nip']) ?></code></div>
            <div class="profile-card__row"><span>Email</span><span><?= htmlspecialchars($akun['email']) ?></span></div>
        </div>
        <button type="button" id="btn-ganti-email" class="btn btn-light" style="margin-top:20px;">Ganti Email</button>
        <form method="POST" id="form-ganti-email" style="margin-top:20px;display:none;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="aksi" value="simpan_email">
            <div class="u-mb-16">
                <label class="field__label" for="email-baru">Email Baru</label>
                <input class="input" id="email-baru" type="email" name="email_baru" value="<?= htmlspecialchars($akun['email']) ?>" maxlength="255" required>
                <p class="faint u-font-12 u-mt-6">Email dipakai untuk login dashboard dan harus unik.</p>
            </div>
            <div class="u-mb-16">
                <label class="field__label" for="pass-lama-email">Password Saat Ini</label>
                <input class="input" type="password" name="password_lama" id="pass-lama-email" required placeholder="Masukkan password saat ini">
            </div>
            <div style="display:flex;gap:10px;align-items:center;">
                <button type="submit" class="btn btn-primary">Simpan Email</button>
                <button type="button" id="btn-batal-email" class="btn btn-light">Batal</button>
            </div>
        </form>
    </section>

    <section class="profile-card">
        <div class="profile-card__title u-mb-16">Keamanan</div>
        <p class="profile-card__sub" style="margin:0 0 16px;">Gunakan password yang kuat dan jangan bagikan kepada orang lain.</p>
        <a class="btn btn-primary btn-block" href="change_password.php">Ganti Password</a>
    </section>
</div>

<script>
document.getElementById('avatar-file-profil').addEventListener('change', function () {
    var file = this.files && this.files[0];
    var prev = document.getElementById('avatar-preview');
    if (file && prev) {
        var reader = new FileReader();
        reader.onload = function (e) {
            prev.src = e.target.result;
            prev.classList.remove('hidden');
        };
        reader.readAsDataURL(file);
    }
});
(function () {
    var btn = document.getElementById('btn-ganti-email');
    var form = document.getElementById('form-ganti-email');
    var btl = document.getElementById('btn-batal-email');
    if (!btn || !form) return;
    btn.addEventListener('click', function () {
        btn.style.display = 'none';
        form.style.display = 'block';
        var pass = document.getElementById('pass-lama-email');
        if (pass) pass.focus();
    });
    btl.addEventListener('click', function () {
        form.style.display = 'none';
        btn.style.display = 'inline-flex';
    });
})();
</script>
<script src="assets/nav.js?v=<?= ASSET_VERSION ?>"></script>
<?php include __DIR__ . '/_foot.php'; ?>
