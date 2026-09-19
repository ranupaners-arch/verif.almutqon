<?php
// public/api/avatar.php — unggah/hapus avatar staff (dipakai menu akun masthead).
// POST (multipart, field `avatar`) + CSRF; `aksi` = simpan|hapus; `kembali` = halaman
// pengirim (whitelist). Selesai selalu redirect balik + flash sesi (toast di partial).
require dirname(__DIR__, 2) . '/app/Bootstrap.php';

if (!isset($_SESSION['is_staff'])) {
    header('Location: ../staff.php');
    exit;
}
$staffId = (int) ($_SESSION['staff_id'] ?? 0);

$boleh = ['dashboard.php', 'data_invoice.php', 'rekap_nomor.php', 'manage_staff.php', 'change_password.php', 'profil.php'];
$kembali = (string) ($_POST['kembali'] ?? 'dashboard.php');
if (!in_array($kembali, $boleh, true)) {
    $kembali = 'dashboard.php';
}

function avatar_flash(string $msg, bool $ok): void
{
    $_SESSION['akun_flash'] = ['ok' => $ok, 'msg' => $msg];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
    avatar_flash('Permintaan tidak valid. Silakan muat ulang halaman dan coba lagi.', false);
    header('Location: ../' . $kembali);
    exit;
}

$dir = dirname(__DIR__) . '/assets/avatars';

$aksi = (string) ($_POST['aksi'] ?? 'simpan');
if ($aksi === 'hapus') {
    $stmt = $pdo->prepare('SELECT avatar FROM staff WHERE id = ?');
    $stmt->execute([$staffId]);
    $lama = (string) ($stmt->fetchColumn() ?: '');
    if ($lama !== '') {
        $path = $dir . '/' . basename($lama);
        $real = realpath($path);
        $base = realpath($dir);
        if ($real !== false && $base !== false && str_starts_with($real, $base . '/')) {
            @unlink($real);
        }
    }
    $pdo->prepare('UPDATE staff SET avatar = NULL WHERE id = ?')->execute([$staffId]);
    avatar_flash('Avatar dihapus.', true);
    header('Location: ../' . $kembali);
    exit;
}

$errCode = (int) ($_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE);
if ($errCode !== UPLOAD_ERR_OK) {
    $msg = match ($errCode) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Ukuran file melebihi batas maksimal 1 MB.',
        UPLOAD_ERR_NO_FILE => 'Silakan pilih file gambar terlebih dahulu.',
        UPLOAD_ERR_PARTIAL => 'File hanya terkirim sebagian. Silakan coba lagi.',
        default => 'Gagal mengupload file. Silakan coba lagi.',
    };
    avatar_flash($msg, false);
    header('Location: ../' . $kembali);
    exit;
}

if ((int) $_FILES['avatar']['size'] > 1024 * 1024) {
    avatar_flash('Ukuran file melebihi batas maksimal 1 MB.', false);
    header('Location: ../' . $kembali);
    exit;
}

$ext = strtolower(pathinfo((string) $_FILES['avatar']['name'], PATHINFO_EXTENSION));
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['avatar']['tmp_name']);
$bolehMime = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
if (!isset($bolehMime[$ext]) || $mime !== $bolehMime[$ext]) {
    avatar_flash('Format file harus JPG, PNG, atau WebP.', false);
    header('Location: ../' . $kembali);
    exit;
}

if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
    avatar_flash('Gagal menyiapkan penyimpanan avatar.', false);
    header('Location: ../' . $kembali);
    exit;
}

$namaBaru = 'av_' . $staffId . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
if (!move_uploaded_file($_FILES['avatar']['tmp_name'], $dir . '/' . $namaBaru)) {
    avatar_flash('Gagal menyimpan file. Cek permission folder avatars/.', false);
    header('Location: ../' . $kembali);
    exit;
}

$stmt = $pdo->prepare('SELECT avatar FROM staff WHERE id = ?');
$stmt->execute([$staffId]);
$lama = (string) ($stmt->fetchColumn() ?: '');
$pdo->prepare('UPDATE staff SET avatar = ? WHERE id = ?')->execute(['assets/avatars/' . $namaBaru, $staffId]);
if ($lama !== '') {
    $path = $dir . '/' . basename($lama);
    $real = realpath($path);
    $base = realpath($dir);
    if ($real !== false && $base !== false && str_starts_with($real, $base . '/')) {
        @unlink($real);
    }
}

avatar_flash('Avatar berhasil diganti.', true);
header('Location: ../' . $kembali);
exit;
