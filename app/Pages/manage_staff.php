<?php
require dirname(__DIR__) . '/Bootstrap.php';

if (!isset($_SESSION['is_staff'])) { header("Location: staff.php"); exit; }

$staff_id = $_SESSION['staff_id'];
$stmt = $pdo->prepare("SELECT role FROM staff WHERE id = ?");
$stmt->execute([$staff_id]);
$current_role = $stmt->fetchColumn();

if (!in_array($current_role, ['direktur', 'admin'])) {
    $_SESSION['manage_msg'] = 'Akses ditolak! Halaman ini hanya untuk Direktur dan Admin.';
    $_SESSION['manage_msg_type'] = 'error';
    header('Location: dashboard.php');
    exit;
}

$is_direktur = ($current_role === 'direktur');
$is_admin_or_direktur = in_array($current_role, ['direktur', 'admin']);
$akun = akun_ringkas($pdo, (int) $staff_id);

function validate_staff_input(string $nama, string $jabatan, string $email, string $role, ?string $nip = null): string
{
    if ($nip !== null && (trim($nip) === '' || strlen($nip) > 100)) {
        return 'NIP wajib diisi (maksimal 100 karakter).';
    }
    if (trim($nama) === '' || strlen($nama) > 255) {
        return 'Nama lengkap wajib diisi (maksimal 255 karakter).';
    }
    if (trim($jabatan) === '' || strlen($jabatan) > 255) {
        return 'Jabatan wajib diisi (maksimal 255 karakter).';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
        return 'Email tidak valid.';
    }
    if (!in_array($role, ['staff', 'admin', 'direktur'], true)) {
        return 'Role tidak valid.';
    }
    return '';
}

function count_active_direktur(PDO $pdo, array $excludedIds = []): int
{
    $excludedIds = array_map('intval', $excludedIds);
    $sql = "SELECT COUNT(*) FROM staff WHERE role = 'direktur' AND is_active = 1";
    if ($excludedIds !== []) {
        $sql .= ' AND id NOT IN (' . implode(',', $excludedIds) . ')';
    }
    return (int) $pdo->query($sql)->fetchColumn();
}

$msg = ''; 
$msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf()) {
    $msg = 'Permintaan tidak valid. Silakan muat ulang halaman dan coba lagi.';
    $msg_type = 'error';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // 1. Tambah Staff (Direktur & Admin)
    if ($action === 'add' && $is_admin_or_direktur) {
        $nip = trim($_POST['nip'] ?? '');
        $nama = trim($_POST['nama_lengkap'] ?? '');
        $jabatan = trim($_POST['jabatan'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? '';
        $password = $_POST['password'] ?? '';

        $validasi = validate_staff_input($nama, $jabatan, $email, $role, $nip);
        if (!$is_direktur && $role === 'direktur') {
            $msg = 'Anda tidak memiliki hak untuk membuat role Direktur!';
            $msg_type = 'error';
        } elseif ($validasi !== '') {
            $msg = $validasi;
            $msg_type = 'error';
        } elseif (!password_is_valid($password)) {
            $msg = 'Password minimal 8 karakter dan harus mengandung huruf serta angka.';
            $msg_type = 'error';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO staff (nip, nama_lengkap, jabatan, email, password, role, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
                $stmt->execute([$nip, $nama, $jabatan, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
                $pdo->prepare("INSERT INTO activity_log (staff_id, action, description, ip_address) VALUES (?, 'staff_add', ?, ?)")
                    ->execute([$staff_id, "Menambahkan staff baru: $nama ($nip)", $_SERVER['REMOTE_ADDR']]);
                $msg = 'Staff baru berhasil ditambahkan!';
                $msg_type = 'success';
            } catch (PDOException $e) {
                error_log('Menambah staff gagal: ' . $e->getMessage());
                $msg = ($e->getCode() == 23000) ? 'NIP/email sudah terdaftar!' : 'Gagal menambahkan staff. Silakan coba lagi.';
                $msg_type = 'error';
            }
        }
    }
    
    // 2. Edit Staff
    if ($action === 'edit') {
        $id = (int) ($_POST['staff_id'] ?? 0);

        $stmt = $pdo->prepare("SELECT role FROM staff WHERE id = ?");
        $stmt->execute([$id]);
        $sasaran = $stmt->fetch();

        if (!$sasaran) {
            $msg = 'Staff tidak ditemukan.';
            $msg_type = 'error';
        } else {
            $nama = trim($_POST['nama_lengkap'] ?? '');
            $jabatan = trim($_POST['jabatan'] ?? '');
            $email = trim($_POST['email'] ?? '');

            $role = $sasaran['role'];
            if ($is_direktur && isset($_POST['role'])) {
                $role = $_POST['role'];
            }

            $validasi = validate_staff_input($nama, $jabatan, $email, $role);
            if (!$is_direktur && $sasaran['role'] === 'direktur') {
                $msg = 'Admin tidak dapat mengubah data direktur.';
                $msg_type = 'error';
            } elseif (!$is_direktur && isset($_POST['role']) && $role !== $sasaran['role']) {
                $msg = 'Hanya Direktur yang dapat mengubah role.';
                $msg_type = 'error';
            } elseif ($validasi !== '') {
                $msg = $validasi;
                $msg_type = 'error';
            } elseif ($sasaran['role'] === 'direktur' && $role !== 'direktur' && count_active_direktur($pdo, [$id]) === 0) {
                $msg = 'Tidak dapat menurunkan role Direktur terakhir yang aktif.';
                $msg_type = 'error';
            } else {
                try {
                    $pdo->prepare("UPDATE staff SET nama_lengkap=?, jabatan=?, email=?, role=? WHERE id=?")
                        ->execute([$nama, $jabatan, $email, $role, $id]);
                    $pdo->prepare("INSERT INTO activity_log (staff_id, action, description, ip_address) VALUES (?, 'staff_edit', ?, ?)")
                        ->execute([$staff_id, "Mengubah data staff ID: $id", $_SERVER['REMOTE_ADDR']]);
                    if ((int) $id === (int) $staff_id) {
                        $_SESSION['staff_nama'] = $nama;
                        $_SESSION['staff_jabatan'] = $jabatan;
                    }
                    $msg = 'Data staff berhasil diupdate!';
                    $msg_type = 'success';
                } catch (PDOException $e) {
                    error_log('Update staff gagal: ' . $e->getMessage());
                    $msg = 'Gagal mengubah data staff. Silakan coba lagi.';
                    $msg_type = 'error';
                }
            }
        }
    }
    
    // 3. Toggle Status Aktif (Hanya Direktur)
    if ($action === 'toggle_status' && $is_direktur) {
        $id = (int) ($_POST['staff_id'] ?? 0);
        if ($id === (int) $staff_id) {
            $msg = 'Anda tidak dapat menonaktifkan akun Anda sendiri!';
            $msg_type = 'error';
        } else {
            $stmt = $pdo->prepare("SELECT is_active, role, nama_lengkap FROM staff WHERE id = ?");
            $stmt->execute([$id]);
            $sasaran = $stmt->fetch();
            if (!$sasaran) {
                $msg = 'Staff tidak ditemukan.';
                $msg_type = 'error';
            } elseif ($sasaran['is_active'] && $sasaran['role'] === 'direktur' && count_active_direktur($pdo, [$id]) === 0) {
                $msg = 'Tidak dapat menonaktifkan Direktur terakhir yang aktif.';
                $msg_type = 'error';
            } else {
                try {
                    $pdo->prepare("UPDATE staff SET is_active = NOT is_active WHERE id = ?")->execute([$id]);
                    $pdo->prepare("INSERT INTO activity_log (staff_id, action, description, ip_address) VALUES (?, 'staff_toggle', ?, ?)")
                        ->execute([$staff_id, "Mengubah status aktif staff: {$sasaran['nama_lengkap']} (ID: $id)", $_SERVER['REMOTE_ADDR']]);
                    $msg = 'Status akses staff berhasil diubah!';
                    $msg_type = 'success';
                } catch (PDOException $e) {
                    error_log('Toggle status staff gagal: ' . $e->getMessage());
                    $msg = 'Gagal mengubah status staff. Silakan coba lagi.';
                    $msg_type = 'error';
                }
            }
        }
    }
    
    // 4. Reset Password (Hanya Direktur)
    if ($action === 'reset_password' && $is_direktur) {
        $id = (int) ($_POST['staff_id'] ?? 0);
        $new_pass = $_POST['new_password'] ?? '';
        $stmt = $pdo->prepare("SELECT nama_lengkap FROM staff WHERE id = ?");
        $stmt->execute([$id]);
        $sasaran = $stmt->fetch();
        if (!$sasaran) {
            $msg = 'Staff tidak ditemukan.';
            $msg_type = 'error';
        } elseif (!password_is_valid($new_pass)) {
            $msg = 'Password minimal 8 karakter dan harus mengandung huruf serta angka.';
            $msg_type = 'error';
        } else {
            try {
                $pdo->prepare("UPDATE staff SET password = ? WHERE id = ?")->execute([password_hash($new_pass, PASSWORD_DEFAULT), $id]);
                $pdo->prepare("INSERT INTO activity_log (staff_id, action, description, ip_address) VALUES (?, 'staff_reset_password', ?, ?)")
                    ->execute([$staff_id, "Meriset password staff: {$sasaran['nama_lengkap']} (ID: $id)", $_SERVER['REMOTE_ADDR']]);
                $msg = 'Password berhasil direset!';
                $msg_type = 'success';
            } catch (PDOException $e) {
                error_log('Reset password staff gagal: ' . $e->getMessage());
                $msg = 'Gagal meriset password. Silakan coba lagi.';
                $msg_type = 'error';
            }
        }
    }
    
    if ($msg) {
        $_SESSION['manage_msg'] = $msg;
        $_SESSION['manage_msg_type'] = $msg_type;
        header("Location: manage_staff.php");
        exit;
    }
}

if (isset($_SESSION['manage_msg'])) {
    $msg = $_SESSION['manage_msg'];
    $msg_type = $_SESSION['manage_msg_type'];
    unset($_SESSION['manage_msg'], $_SESSION['manage_msg_type']);
}

$staff_list = $pdo->query("SELECT * FROM staff ORDER BY is_active DESC, role DESC, nama_lengkap ASC")->fetchAll();

// Navigasi
$pageTitle = 'Manajemen Staff';
$navActive = 'manage_staff';
?>
<!DOCTYPE html>
<html lang="id" data-theme="<?= $akun['theme'] ?>">
<head>
<script>try{if(document.documentElement.getAttribute('data-theme')==='system'){document.documentElement.setAttribute('data-theme',matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light');}}catch(e){}</script>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manajemen Staff - AL MUTQON</title>
<link rel="icon" href="assets/1.png">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="assets/app.css?v=20260919c" rel="stylesheet">
</head>
<body>

<?php include __DIR__ . '/_sidebar.php'; ?>
<?php include __DIR__ . '/_masthead.php'; ?>

<main class="page page--wide">
    <?php if ($msg): ?>
    <div class="alert <?= $msg_type === 'success' ? 'alert-ok' : 'alert-err' ?>">
        <span><?= htmlspecialchars($msg) ?></span>
    </div>
    <?php endif; ?>

    <?php if ($is_admin_or_direktur): ?>
    <div class="panel" style="margin-bottom:22px">
        <div class="panel__hd">
            <h2 class="panel__title">Tambah Staff Baru</h2>
            <?php if (!$is_direktur): ?><span class="badge badge-warn">Sebagai Admin, Anda tidak dapat membuat akun dengan role Direktur</span><?php endif; ?>
        </div>
        <div class="panel__bd">
            <form method="POST" class="grid2">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="add">
                <div class="field">
                    <label class="field__label">NIP / ID Pengguna</label>
                    <input class="input" type="text" name="nip" required placeholder="Nomor Induk Pegawai">
                </div>
                <div class="field">
                    <label class="field__label">Nama Lengkap</label>
                    <input class="input" type="text" name="nama_lengkap" required>
                </div>
                <div class="field">
                    <label class="field__label">Jabatan</label>
                    <input class="input" type="text" name="jabatan" required>
                </div>
                <div class="field">
                    <label class="field__label">Email</label>
                    <input class="input" type="email" name="email" required>
                </div>
                <div class="field">
                    <label class="field__label">Role</label>
                    <select class="select" name="role" required>
                        <option value="staff">Staff</option>
                        <option value="admin">Admin</option>
                        <?php if ($is_direktur): ?><option value="direktur">Direktur</option><?php endif; ?>
                    </select>
                    <?php if (!$is_direktur): ?><span class="faint" style="font-size:12px">*Hanya Direktur yang dapat membuat role Direktur.</span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label">Password Awal</label>
                    <input class="input" type="password" name="password" required minlength="8" placeholder="Min. 8 karakter (huruf &amp; angka)">
                </div>
                <div style="grid-column:1 / -1">
                    <button class="btn btn-primary" type="submit">Tambahkan Staff</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <div class="panel">
        <div class="panel__hd">
            <h2 class="panel__title">Daftar Staff &amp; Hak Akses</h2>
            <span class="badge badge-forest"><?= count($staff_list) ?> akun</span>
        </div>
        <div class="panel__bd">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>NIP</th>
                            <th>Nama &amp; Jabatan</th>
                            <th>Role</th>
                            <th class="textc">Status</th>
                            <th class="textc">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($staff_list as $s): ?>
                        <tr class="<?= !$s['is_active'] ? 'is-off' : '' ?>">
                            <td><span class="mono" style="font-size:12px"><?= htmlspecialchars($s['nip']) ?></span></td>
                            <td>
                                <p style="font-weight:600"><?= htmlspecialchars($s['nama_lengkap']) ?></p>
                                <p class="faint" style="font-size:12px"><?= htmlspecialchars($s['jabatan']) ?></p>
                            </td>
                            <td>
                                <span class="badge <?= $s['role'] === 'direktur' ? 'badge-gold' : ($s['role'] === 'admin' ? 'badge-blue' : 'badge-muted') ?>">
                                    <?= htmlspecialchars($s['role'] === 'direktur' ? 'Direktur' : ucfirst($s['role'])) ?>
                                </span>
                            </td>
                            <td class="textc">
                                <?php if ($s['is_active']): ?>
                                    <span class="badge badge-ok">Aktif</span>
                                <?php else: ?>
                                    <span class="badge badge-err">Nonaktif</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="row" style="justify-content:center">
                                    <?php if ($is_direktur || $s['role'] !== 'direktur'): ?>
                                    <button type="button" class="btn btn-soft btn-sm" onclick="openEditModal(<?= (int) $s['id'] ?>, <?= htmlspecialchars((string) json_encode($s['nama_lengkap']), ENT_QUOTES) ?>, <?= htmlspecialchars((string) json_encode($s['jabatan']), ENT_QUOTES) ?>, <?= htmlspecialchars((string) json_encode($s['email']), ENT_QUOTES) ?>, <?= htmlspecialchars((string) json_encode($s['role']), ENT_QUOTES) ?>)">Edit</button>
                                    <?php endif; ?>
                                    <?php if ($is_direktur): ?>
                                    <button type="button" class="btn btn-warn btn-sm" onclick="openResetModal(<?= (int) $s['id'] ?>, <?= htmlspecialchars((string) json_encode($s['nama_lengkap']), ENT_QUOTES) ?>)">Reset Pass</button>
                                    <?php if ($s['id'] != $staff_id): ?>
                                    <form method="POST" onsubmit="return confirm('Yakin ingin mengubah status akses staff ini?');">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="staff_id" value="<?= (int) $s['id'] ?>">
                                        <button type="submit" class="btn btn-sm <?= $s['is_active'] ? 'btn-danger' : 'btn-soft' ?>">
                                            <?= $s['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- MODAL EDIT -->
<div id="modal-edit" class="modal-overlay hidden">
    <div class="modal">
        <h3>Edit Data Staff</h3>
        <p class="modal-desc">Perbarui informasi akun staff berikut.</p>
        <form method="POST" class="stack">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="staff_id" id="edit-id">
            <div class="field">
                <label class="field__label" for="edit-nama">Nama Lengkap</label>
                <input class="input" type="text" name="nama_lengkap" id="edit-nama" required>
            </div>
            <div class="field">
                <label class="field__label" for="edit-jabatan">Jabatan</label>
                <input class="input" type="text" name="jabatan" id="edit-jabatan" required>
            </div>
            <div class="field">
                <label class="field__label" for="edit-email">Email</label>
                <input class="input" type="email" name="email" id="edit-email" required>
            </div>
            <div class="field">
                <label class="field__label" for="edit-role">Role</label>
                <select name="role" id="edit-role" class="select" <?= !$is_direktur ? 'disabled' : '' ?>>
                    <option value="staff">Staff</option>
                    <option value="admin">Admin</option>
                    <?php if ($is_direktur): ?><option value="direktur">Direktur</option><?php endif; ?>
                </select>
                <?php if (!$is_direktur): ?><span class="faint" style="font-size:12px">*Hanya Direktur yang dapat mengubah role.</span><?php endif; ?>
            </div>
            <div class="btn-row">
                <button type="button" class="btn btn-light" onclick="closeModal('modal-edit')">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL RESET PASSWORD -->
<div id="modal-reset" class="modal-overlay hidden">
    <div class="modal">
        <h3>Reset Password</h3>
        <p class="modal-desc">Untuk: <strong id="reset-nama"></strong></p>
        <form method="POST" class="stack">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="staff_id" id="reset-id">
            <div class="field">
                <label class="field__label" for="reset-pass">Password Baru</label>
                <input class="input" type="password" name="new_password" id="reset-pass" required minlength="8" placeholder="Min. 8 karakter (huruf &amp; angka)">
            </div>
            <div class="btn-row">
                <button type="button" class="btn btn-light" onclick="closeModal('modal-reset')">Batal</button>
                <button type="submit" class="btn btn-warn">Reset Password</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/_modal_shared.php'; ?>
<script>
function openEditModal(id, nama, jabatan, email, role) {
    document.getElementById('edit-id').value = id;
    document.getElementById('edit-nama').value = nama;
    document.getElementById('edit-jabatan').value = jabatan;
    document.getElementById('edit-email').value = email;
    document.getElementById('edit-role').value = role;
    openModal('modal-edit');
}
function openResetModal(id, nama) {
    document.getElementById('reset-id').value = id;
    document.getElementById('reset-nama').textContent = nama;
    document.getElementById('reset-pass').value = '';
    openModal('modal-reset');
}
</script>
<script src="assets/nav.js?v=20260919c"></script>
</body>
</html>