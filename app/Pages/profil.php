<?php
// app/Pages/profil.php — profil staff: avatar, info akun, kegiatan terakhir, tema.
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

// Kegiatan terakhir: activity_log milik saya (10 terbaru).
$actLabel = [
    'create_doc'         => 'Membuat dokumen',
    'delete_document'    => 'Menghapus dokumen',
    'revoke_document'    => 'Mencabut dokumen',
    'restore_document'   => 'Mengaktifkan dokumen',
    'upload_pdf'         => 'Upload PDF dokumen',
    'change_password'    => 'Mengubah password',
    'login'              => 'Login',
    'staff_add'          => 'Menambah staff',
    'staff_edit'         => 'Mengubah data staff',
    'staff_toggle'       => 'Ubah status staff',
    'staff_reset_password' => 'Reset password staff',
];
$stmt = $pdo->prepare("SELECT action, description, created_at FROM activity_log WHERE staff_id = ? ORDER BY id DESC LIMIT 10");
$stmt->execute([$staff_id]);
$activities = $stmt->fetchAll();
foreach ($activities as $i => $a) {
    $activities[$i]['label'] = $actLabel[$a['action']] ?? $a['action'];
    $ts = strtotime((string) $a['created_at']);
    $activities[$i]['time'] = $ts ? date('d M Y H:i', $ts) : '-';
}

// Waktu terakhir ganti password.
$stmt = $pdo->prepare("SELECT created_at FROM activity_log WHERE staff_id = ? AND action = 'change_password' ORDER BY id DESC LIMIT 1");
$stmt->execute([$staff_id]);
$lastPw = $stmt->fetchColumn();
$lastPwText = $lastPw ? date('d M Y H:i', strtotime((string) $lastPw)) : 'Belum pernah';

$pageTitle = 'Profil Saya';
$navActive = 'profil';
?>
<!DOCTYPE html>
<html lang="id" data-theme="<?= $akun['theme'] ?>">
<head>
<script>try{if(document.documentElement.getAttribute('data-theme')==='system'){document.documentElement.setAttribute('data-theme',matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light');}}catch(e){}</script>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Profil Saya - AL MUTQON</title>
<link rel="icon" href="assets/1.png">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="assets/app.css?v=20260919c" rel="stylesheet">
</head>
<body>

<?php include __DIR__ . '/_sidebar.php'; ?>
<?php include __DIR__ . '/_masthead.php'; ?>

<main class="page" style="max-width:640px">
    <div class="panel" style="margin-bottom:16px">
        <div class="panel__hd">
            <h2 class="panel__title">Profil</h2>
            <span class="badge <?= $roleBadge ?>"><?= htmlspecialchars($roleLabel) ?></span>
        </div>
        <div class="panel__bd">
            <div class="row" style="align-items:center;margin-bottom:18px">
                <span id="avatar-now">
                    <?php if ($akun['avatar'] !== ''): ?>
                        <img src="<?= htmlspecialchars($akun['avatar']) ?>" alt="Avatar" style="width:72px;height:72px;border-radius:50%;object-fit:cover;flex-shrink:0">
                    <?php else: ?>
                        <span class="account-menu__avatar" style="width:72px;height:72px;font-size:30px;"><?= htmlspecialchars(strtoupper(substr($akun['nama'], 0, 1))) ?></span>
                    <?php endif; ?>
                </span>
                <div style="min-width:0">
                    <p style="font-weight:700;font-size:17px;margin:0"><?= htmlspecialchars($akun['nama']) ?></p>
                    <p class="faint" style="font-size:13px;margin:2px 0 0"><?= htmlspecialchars($akun['jabatan']) ?> &bull; <?= htmlspecialchars($roleLabel) ?></p>
                </div>
            </div>
            <form method="POST" action="api/avatar.php" enctype="multipart/form-data" class="stack">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="aksi" value="simpan">
                <input type="hidden" name="kembali" value="profil.php">
                <div class="field">
                    <label class="field__label" for="avatar-file-profil">Ganti Avatar (JPG/PNG/WebP, maks 1 MB)</label>
                    <div class="row" style="gap:10px;align-items:center">
                        <input class="input" id="avatar-file-profil" type="file" name="avatar" accept=".jpg,.jpeg,.png,.webp" required style="flex:1">
                        <img id="avatar-preview" alt="Pratinjau" class="hidden" style="width:44px;height:44px;border-radius:50%;object-fit:cover;flex-shrink:0">
                    </div>
                </div>
                <div class="row">
                    <button type="submit" class="btn btn-primary">Simpan Avatar</button>
                    <?php if ($akun['avatar'] !== ''): ?>
                    <button type="submit" class="btn btn-light" formaction="api/avatar.php" formnovalidate onclick="this.form.aksi.value='hapus'">Hapus Avatar</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="panel" style="margin-bottom:16px">
        <div class="panel__hd">
            <h2 class="panel__title">Info Akun</h2>
        </div>
        <div class="panel__bd">
            <div class="doc-grid">
                <div class="doc-cell">
                    <p class="doc-cell__label">ID Pengguna / NIP</p>
                    <p class="doc-cell__value mono"><?= htmlspecialchars($akun['nip']) ?></p>
                </div>
                <div class="doc-cell">
                    <p class="doc-cell__label">Email</p>
                    <p class="doc-cell__value" style="font-size:14px"><?= htmlspecialchars($akun['email']) ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="panel" style="margin-bottom:16px">
        <div class="panel__hd">
            <h2 class="panel__title">Keamanan</h2>
        </div>
        <div class="panel__bd">
            <div class="kv" style="margin-bottom:14px">
                <p class="kv__label">Terakhir Ganti Password</p>
                <p class="doc-cell__value" style="font-weight:600;font-size:14.5px"><?= htmlspecialchars($lastPwText) ?></p>
            </div>
            <a class="btn btn-primary btn-block" href="change_password.php">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                Ganti Password
            </a>
        </div>
    </div>

    <div class="panel" style="margin-bottom:16px">
        <div class="panel__hd">
            <h2 class="panel__title">Aktivitas Terakhir Saya</h2>
        </div>
        <div class="panel__bd">
            <?php if (count($activities) > 0): ?>
            <div class="stack" style="gap:0">
                <?php foreach ($activities as $a): ?>
                <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;padding:11px 0;border-bottom:1px solid var(--line)">
                    <span style="font-size:13.5px;font-weight:600"><?= htmlspecialchars($a['label']) ?></span>
                    <span class="faint" style="font-size:12px;flex-shrink:0"><?= htmlspecialchars($a['time']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="faint" style="font-size:13px">Belum ada aktivitas tercatat.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="panel" style="margin-bottom:16px">
        <div class="panel__hd">
            <h2 class="panel__title">Tema Tampilan</h2>
        </div>
        <div class="panel__bd">
            <?php
            $themeStored   = (string) $akun['theme'];
            $themeVariant  = 'big';
            $themeEndpoint = 'api/theme.php';
            $themeCsrf     = csrf_token();
            include __DIR__ . '/_theme_switch.php';
            ?>
            <p class="faint" style="font-size:12.5px;margin:10px 0 0">Sistem mengikuti terang/gelap perangkat secara otomatis.</p>
        </div>
    </div>
</main>

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
</script>
<script src="assets/nav.js?v=20260919c"></script>
</body>
</html>