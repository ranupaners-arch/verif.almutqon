<?php
require dirname(__DIR__) . '/Bootstrap.php';
if (!isset($_SESSION['is_staff'])) { header("Location: staff.php"); exit; }
$staff_id = (int) ($_SESSION['staff_id'] ?? 0);
$akun = akun_ringkas($pdo, $staff_id);
$staff_nama = $_SESSION['staff_nama'];
$staff_jabatan = $_SESSION['staff_jabatan'];
$msg = ''; $msg_type = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf()) {
    $msg = 'Permintaan tidak valid. Silakan muat ulang halaman dan coba lagi.'; $msg_type = 'error';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
$pass_lama = $_POST['password_lama'] ?? '';
$pass_baru = $_POST['password_baru'] ?? '';
$pass_konfirmasi = $_POST['password_konfirmasi'] ?? '';
if (empty($pass_lama) || empty($pass_baru) || empty($pass_konfirmasi)) {
$msg = "Semua field wajib diisi!"; $msg_type = 'error';
} elseif (!password_is_valid($pass_baru)) {
$msg = "Password baru minimal 8 karakter serta harus mengandung huruf dan angka!"; $msg_type = 'error';
} elseif ($pass_baru !== $pass_konfirmasi) {
$msg = "Konfirmasi password tidak cocok dengan password baru!"; $msg_type = 'error';
} elseif ($pass_lama === $pass_baru) {
$msg = "Password baru tidak boleh sama dengan password lama!"; $msg_type = 'error';
} else {
$stmt = $pdo->prepare("SELECT password FROM staff WHERE id = ?");
$stmt->execute([$staff_id]);
$current_hash = $stmt->fetchColumn();
if (!password_verify($pass_lama, $current_hash)) {
$msg = "Password lama salah! Silakan coba lagi."; $msg_type = 'error';
} else {
$new_hash = password_hash($pass_baru, PASSWORD_DEFAULT);
$pdo->prepare("UPDATE staff SET password = ? WHERE id = ?")->execute([$new_hash, $staff_id]);
$pdo->prepare("INSERT INTO activity_log (staff_id, action, description, ip_address) VALUES (?, 'change_password', 'Mengubah password sendiri', ?)")
->execute([$staff_id, $_SERVER['REMOTE_ADDR']]);
$msg = "Password berhasil diubah! Silakan gunakan password baru saat login berikutnya."; $msg_type = 'success';
}
}
}

// Navigasi
$pageTitle = 'Ganti Password';
$navActive = 'change_password';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ganti Password - AL MUTQON</title>
<link rel="icon" href="assets/1.png">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="assets/app.css?v=20260919l" rel="stylesheet">
</head>
<body>

<?php include __DIR__ . '/_sidebar.php'; ?>
<?php include __DIR__ . '/_masthead.php'; ?>

<main class="page" style="max-width:560px">
    <div class="panel" style="margin-bottom:18px">
        <div class="panel__bd row">
            <?php if ($akun['avatar'] !== ''): ?>
            <img src="<?= htmlspecialchars($akun['avatar']) ?>" alt="" style="width:44px;height:44px;border-radius:50%;object-fit:cover;flex-shrink:0">
            <?php else: ?>
            <span class="user-chip__av" style="background:var(--forest);color:#fff;width:44px;height:44px;font-size:18px;"><?= strtoupper(substr($staff_nama, 0, 1)) ?></span>
            <?php endif; ?>
            <span>
                <strong><?= htmlspecialchars($staff_nama) ?></strong>
                <span class="faint" style="display:block;font-size:12px"><?= htmlspecialchars($staff_jabatan) ?></span>
            </span>
        </div>
    </div>

    <?php if ($msg): ?>
    <div class="alert <?= $msg_type === 'success' ? 'alert-ok' : 'alert-err' ?>">
        <span><?= htmlspecialchars($msg) ?></span>
    </div>
    <?php endif; ?>

    <div class="panel panel--edge">
        <div class="panel__bd">
            <h2 class="panel__title" style="font-size:18px;margin-bottom:4px">Ubah Kata Sandi</h2>
            <p class="faint" style="font-size:12.5px;margin-bottom:20px">Masukkan password lama untuk verifikasi, lalu buat password baru.</p>
            <form method="POST" class="stack" id="form-pass">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <div class="field">
                    <label class="field__label" for="pass-lama">Password Lama</label>
                    <div class="row" style="gap:8px;flex-wrap:nowrap">
                        <input class="input" type="password" name="password_lama" id="pass-lama" required placeholder="Masukkan password saat ini" style="flex:1">
                        <button type="button" class="btn btn-light pass-toggle" onclick="togglePass('pass-lama', this)" title="Tampilkan/sembunyikan">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        </button>
                    </div>
                </div>
                <div class="field">
                    <label class="field__label" for="pass-baru">Password Baru</label>
                    <div class="row" style="gap:8px;flex-wrap:nowrap">
                        <input class="input" type="password" name="password_baru" id="pass-baru" required minlength="8" placeholder="Minimal 8 karakter" style="flex:1">
                        <button type="button" class="btn btn-light pass-toggle" onclick="togglePass('pass-baru', this)" title="Tampilkan/sembunyikan">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        </button>
                    </div>
                    <div class="strength"><div id="strength-bar" class="strength__fill" style="width:0"></div></div>
                    <p id="strength-text" class="faint" style="font-size:12px"></p>
                </div>
                <div class="field">
                    <label class="field__label" for="pass-konf">Konfirmasi Password Baru</label>
                    <div class="row" style="gap:8px;flex-wrap:nowrap">
                        <input class="input" type="password" name="password_konfirmasi" id="pass-konf" required minlength="8" placeholder="Ulangi password baru" style="flex:1">
                        <button type="button" class="btn btn-light pass-toggle" onclick="togglePass('pass-konf', this)" title="Tampilkan/sembunyikan">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        </button>
                    </div>
                    <p id="match-text" class="hidden" style="font-size:12px;margin-top:6px"></p>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Simpan Password Baru</button>
            </form>
        </div>
    </div>

    <div class="alert alert-warn" style="margin-top:18px">
        <div>
            <strong style="font-size:12px">Tips Keamanan</strong>
            <ul style="font-size:12px;margin:6px 0 0;padding-left:18px">
                <li>Gunakan kombinasi huruf besar, kecil, angka, dan simbol.</li>
                <li>Jangan gunakan password yang sama dengan akun lain.</li>
                <li>Ganti password secara berkala (minimal 3 bulan sekali).</li>
            </ul>
        </div>
    </div>

    <p class="faint textc" style="font-size:11.5px;margin-top:22px">&copy; 2026 AL MUTQON TECH &bull; Tokens diamankan HMAC-SHA256</p>
</main>

<script>
function togglePass(inputId, btn) {
    const input = document.getElementById(inputId);
    if (input.type === 'password') { input.type = 'text'; btn.classList.add('active'); }
    else { input.type = 'password'; btn.classList.remove('active'); }
}
document.getElementById('pass-baru').addEventListener('input', function() {
    const val = this.value;
    const bar = document.getElementById('strength-bar');
    const text = document.getElementById('strength-text');
    let strength = 0;
    if (val.length >= 8) strength++;
    if (val.length >= 12) strength++;
    if (/[A-Z]/.test(val)) strength++;
    if (/[0-9]/.test(val)) strength++;
    if (/[^A-Za-z0-9]/.test(val)) strength++;
    const levels = [
        { w: '0%',   lv: '',   label: '' },
        { w: '20%',  lv: 'wk', label: 'Sangat Lemah' },
        { w: '40%',  lv: 'wk', label: 'Lemah' },
        { w: '60%',  lv: 'md', label: 'Cukup' },
        { w: '80%',  lv: 'ok', label: 'Kuat' },
        { w: '100%', lv: 'ok', label: 'Sangat Kuat' }
    ];
    const level = val.length === 0 ? levels[0] : levels[Math.min(strength, 5)];
    bar.style.width = level.w;
    bar.dataset.lv = level.lv;
    bar.classList.toggle('hidden-fill', level.w === '0%');
    text.textContent = level.label;
    text.className = strength <= 2 && val.length > 0 ? 'err-text' : 'ok-text';
    text.style.fontSize = '12px';
    if (val.length === 0) text.className = 'faint';
});
document.getElementById('pass-konf').addEventListener('input', function() {
    const baru = document.getElementById('pass-baru').value;
    const konf = this.value;
    const text = document.getElementById('match-text');
    if (konf.length === 0) { text.classList.add('hidden'); return; }
    text.classList.remove('hidden');
    if (baru === konf) {
        text.textContent = 'Password cocok';
        text.className = 'ok-text';
    } else {
        text.textContent = 'Password tidak cocok';
        text.className = 'err-text';
    }
    text.style.fontSize = '12px';
    text.style.marginTop = '6px';
});
</script>
<script src="assets/nav.js?v=20260919c"></script>
</body>
</html>