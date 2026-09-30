<?php
declare(strict_types=1);

// app/Http/api_v1_staf.php — API internal v1 untuk sinkron personel ALMUTQON.
// Endpoint publik: POST /api/v1/staf.php (auth Bearer, envelope JSON).
// Aksi (field `action`): upsert | toggle | rename_id.
// Batasan integritas (cermin manage_staff.php + matriks izin):
//   - NIP SYSTEM-* tak tersentuh; baris role direktur tak tersentuh;
//     role `direktur` hanya lewat dashboard (API: staff|admin saja).
//   - Toggle: direktur terakhir yang aktif tak bisa dinonaktifkan.
// Natural key utama = almutqon_user_id; NIP dan legacy_nip menjadi fallback transisi.

require __DIR__ . '/api_v1_boot.php';

$svc = api_service_account($pdo);
$svcId = (int) $svc['id'];

$input = api_input();

$action = (string) ($input['action'] ?? '');
if (!in_array($action, ['upsert', 'toggle', 'rename_id', 'update_email', 'update_password', 'update_profile'], true)) {
    api_respond(400, ['success' => false, 'message' => 'Aksi tidak dikenali (upsert|toggle|rename_id|update_email|update_password|update_profile).']);
}

$ip = api_ip();

function api_staf_payload(array $s): array
{
    return [
        'id' => (int) $s['id'],
        'nip' => (string) $s['nip'],
        'nama_lengkap' => (string) $s['nama_lengkap'],
        'role' => (string) $s['role'],
        'is_active' => (int) $s['is_active'],
        'almutqon_user_id' => $s['almutqon_user_id'] === null ? null : (int) $s['almutqon_user_id'],
    ];
}

function api_count_active_direktur(PDO $pdo, array $excludedIds = []): int
{
    $excludedIds = array_map('intval', $excludedIds);
    $sql = "SELECT COUNT(*) FROM staff WHERE role = 'direktur' AND is_active = 1";
    if ($excludedIds !== []) {
        $sql .= ' AND id NOT IN (' . implode(',', $excludedIds) . ')';
    }
    return (int) $pdo->query($sql)->fetchColumn();
}

$nip = trim((string) ($input['nip'] ?? ''));
if ($nip === '' || strlen($nip) > 100) {
    api_respond(400, ['success' => false, 'message' => 'NIP wajib diisi (maksimal 100 karakter).']);
}
if (str_starts_with($nip, 'SYSTEM-')) {
    api_respond(403, ['success' => false, 'message' => 'Akun sistem tidak bisa diubah via API.']);
}

$linkRaw = $input['almutqon_user_id'] ?? null;
$link = ($linkRaw === null || $linkRaw === '') ? null : max(0, (int) $linkRaw);
if ($link === 0) {
    $link = null;
}
$legacyNip = trim((string) ($input['legacy_nip'] ?? ''));

$ada = false;
if ($link !== null) {
    $stmt = $pdo->prepare('SELECT * FROM staff WHERE almutqon_user_id = ? LIMIT 1');
    $stmt->execute([$link]);
    $ada = $stmt->fetch();
}
if (!$ada) {
    $stmt = $pdo->prepare('SELECT * FROM staff WHERE nip = ? LIMIT 1');
    $stmt->execute([$nip]);
    $ada = $stmt->fetch();
}
if (!$ada && $legacyNip !== '' && $legacyNip !== $nip) {
    $stmt = $pdo->prepare('SELECT * FROM staff WHERE nip = ? LIMIT 1');
    $stmt->execute([$legacyNip]);
    $ada = $stmt->fetch();
}

// Pergantian business identifier saja. Aman untuk direktur karena tidak menyentuh
// role, password, status aktif, atau izin; tautan internal harus sudah cocok.
if ($action === 'rename_id') {
    if ($link === null || !$ada || (int) ($ada['almutqon_user_id'] ?? 0) !== $link) {
        api_respond(409, ['success' => false, 'message' => 'Tautan staff belum cocok untuk migrasi Staff ID.']);
    }
    if (preg_match('/^[0-9]{2}90[0-9]{6}$/', $nip) !== 1) {
        api_respond(400, ['success' => false, 'message' => 'Format Staff ID baru tidak valid.']);
    }
    $stmt = $pdo->prepare('SELECT id FROM staff WHERE nip = ? AND id <> ? LIMIT 1');
    $stmt->execute([$nip, (int) $ada['id']]);
    if ($stmt->fetch()) {
        api_respond(409, ['success' => false, 'message' => 'Staff ID baru sudah dipakai baris lain.']);
    }
    try {
        $pdo->prepare('UPDATE staff SET nip = ? WHERE id = ?')->execute([$nip, (int) $ada['id']]);
        $pdo->prepare('INSERT INTO activity_log (staff_id, system_actor_id, action, dokumen_id, description, ip_address) VALUES (NULL, ?, \'staff_edit\', NULL, ?, ?)')
            ->execute([$svcId, 'Migrasi Staff ID via API v1: ' . $legacyNip . ' -> ' . $nip, $ip]);
    } catch (Throwable $t) {
        error_log('API v1 staf rename_id gagal: ' . $t->getMessage());
        api_respond(500, ['success' => false, 'message' => 'Gagal memigrasikan Staff ID.']);
    }
    $stmt = $pdo->prepare('SELECT * FROM staff WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $ada['id']]);
    api_respond(200, ['success' => true, 'action' => 'rename_id', 'data' => api_staf_payload($stmt->fetch())]);
}

if ($action === 'update_email') {
    $email = trim((string) ($input['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
        api_respond(400, ['success' => false, 'message' => 'Email tidak valid.']);
    }
    if (!$ada) {
        api_respond(404, ['success' => false, 'message' => 'Staff tidak ditemukan.']);
    }
    try {
        $pdo->prepare('UPDATE staff SET email = ? WHERE id = ?')->execute([$email, (int) $ada['id']]);
        $pdo->prepare("INSERT INTO activity_log (staff_id, system_actor_id, action, dokumen_id, description, ip_address) VALUES (NULL, ?, 'staff_edit', NULL, ?, ?)")
            ->execute([$svcId, 'Mengubah email staff via API v1: ' . $nip, $ip]);
    } catch (Throwable $t) {
        error_log('API v1 staf update_email gagal: ' . $t->getMessage());
        api_respond(500, ['success' => false, 'message' => 'Gagal memperbarui email staff.']);
    }
    $stmt = $pdo->prepare('SELECT * FROM staff WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $ada['id']]);
    api_respond(200, ['success' => true, 'action' => 'update_email', 'data' => api_staf_payload($stmt->fetch())]);
}

if ($action === 'update_password') {
    $passwordHash = trim((string) ($input['password_hash'] ?? ''));
    if ($passwordHash === '' || password_get_info($passwordHash)['algo'] === 0 || strlen($passwordHash) > 255) {
        api_respond(400, ['success' => false, 'message' => 'Hash password tidak valid.']);
    }
    if (!$ada) {
        api_respond(404, ['success' => false, 'message' => 'Staff tidak ditemukan.']);
    }
    try {
        $pdo->prepare('UPDATE staff SET password = ? WHERE id = ?')->execute([$passwordHash, (int) $ada['id']]);
        $pdo->prepare("INSERT INTO activity_log (staff_id, system_actor_id, action, dokumen_id, description, ip_address) VALUES (NULL, ?, 'staff_password', NULL, ?, ?)")
            ->execute([$svcId, 'Menyinkronkan password staff via API v1: ' . $nip, $ip]);
    } catch (Throwable $t) {
        error_log('API v1 staf update_password gagal: ' . $t->getMessage());
        api_respond(500, ['success' => false, 'message' => 'Gagal memperbarui password staff.']);
    }
    $stmt = $pdo->prepare('SELECT * FROM staff WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $ada['id']]);
    api_respond(200, ['success' => true, 'action' => 'update_password', 'data' => api_staf_payload($stmt->fetch())]);
}

if ($action === 'update_profile') {
    $nama = trim((string) ($input['nama_lengkap'] ?? ''));
    $email = trim((string) ($input['email'] ?? ''));
    if ($nama === '' || strlen($nama) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
        api_respond(400, ['success' => false, 'message' => 'Nama atau email profil tidak valid.']);
    }
    if (!$ada) {
        api_respond(404, ['success' => false, 'message' => 'Staff tidak ditemukan.']);
    }

    $newAvatar = null;
    $avatarPath = null;
    if (isset($_FILES['avatar']) && (int) ($_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $upload = $_FILES['avatar'];
        $mimeMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $upload['tmp_name']);
        if ((int) ($upload['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || (int) $upload['size'] > 1024 * 1024 || !isset($mimeMap[$mime])) {
            api_respond(400, ['success' => false, 'message' => 'Avatar tidak valid (JPG/PNG/WebP, maksimal 1 MB).']);
        }
        $avatarDir = AVATAR_DIR;
        if (!is_dir($avatarDir) && !mkdir($avatarDir, 0755, true)) {
            api_respond(500, ['success' => false, 'message' => 'Penyimpanan avatar tidak tersedia.']);
        }
        $newAvatar = 'av_api_' . (int) $ada['id'] . '_' . bin2hex(random_bytes(6)) . '.' . $mimeMap[$mime];
        $avatarPath = $avatarDir . '/' . $newAvatar;
        if (!move_uploaded_file((string) $upload['tmp_name'], $avatarPath)) {
            api_respond(500, ['success' => false, 'message' => 'Avatar gagal disimpan.']);
        }
    }
    try {
        $newDbAvatar = $newAvatar === null ? (string) ($ada['avatar'] ?? '') : 'assets/avatars/' . $newAvatar;
        $pdo->prepare('UPDATE staff SET nama_lengkap = ?, email = ?, avatar = ? WHERE id = ?')->execute([$nama, $email, $newDbAvatar !== '' ? $newDbAvatar : null, (int) $ada['id']]);
        $pdo->prepare("INSERT INTO activity_log (staff_id, system_actor_id, action, dokumen_id, description, ip_address) VALUES (NULL, ?, 'staff_edit', NULL, ?, ?)")
            ->execute([$svcId, 'Menyinkronkan profil staff via API v1: ' . $nip, $ip]);
    } catch (Throwable $t) {
        if ($avatarPath !== null) @unlink($avatarPath);
        error_log('API v1 staf update_profile gagal: ' . $t->getMessage());
        api_respond(500, ['success' => false, 'message' => 'Gagal memperbarui profil staff.']);
    }
    if ($newAvatar !== null && !empty($ada['avatar'])) {
        $old = AVATAR_DIR . '/' . basename((string) $ada['avatar']);
        if (is_file($old)) @unlink($old);
    }
    $stmt = $pdo->prepare('SELECT * FROM staff WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $ada['id']]);
    api_respond(200, ['success' => true, 'action' => 'update_profile', 'data' => api_staf_payload($stmt->fetch())]);
}

// --------------------------------------------------------------- upsert ---
if ($action === 'upsert') {
    $nama = trim((string) ($input['nama_lengkap'] ?? $input['nama'] ?? ''));
    $jabatan = trim((string) ($input['jabatan'] ?? ''));
    $email = trim((string) ($input['email'] ?? ''));
    $role = trim((string) ($input['role'] ?? 'staff'));
    $aktif = isset($input['is_active']) ? ((int) $input['is_active'] === 1 ? 1 : 0) : 1;
    if ($nama === '' || strlen($nama) > 255) {
        api_respond(400, ['success' => false, 'message' => 'Nama lengkap wajib diisi (maksimal 255 karakter).']);
    } elseif ($jabatan === '' || strlen($jabatan) > 255) {
        api_respond(400, ['success' => false, 'message' => 'Jabatan wajib diisi (maksimal 255 karakter).']);
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
        api_respond(400, ['success' => false, 'message' => 'Email tidak valid (maksimal 255 karakter).']);
    } elseif (!in_array($role, ['staff', 'admin'], true)) {
        api_respond(403, ['success' => false, 'message' => 'Peran direktur hanya lewat dashboard.']);
    }

    if ($ada) {
        if (($ada['role'] ?? '') === 'direktur') {
            api_respond(403, ['success' => false, 'message' => 'Baris direktur hanya lewat dashboard.']);
        }
        if ($link !== null) {
            $stmt = $pdo->prepare('SELECT id FROM staff WHERE almutqon_user_id = ? AND id <> ? LIMIT 1');
            $stmt->execute([$link, (int) $ada['id']]);
            if ($stmt->fetch()) {
                api_respond(409, ['success' => false, 'message' => 'Tautan ID sudah dipakai baris lain.']);
            }
        }
        $stmt = $pdo->prepare('SELECT id FROM staff WHERE nip = ? AND id <> ? LIMIT 1');
        $stmt->execute([$nip, (int) $ada['id']]);
        if ($stmt->fetch()) {
            api_respond(409, ['success' => false, 'message' => 'Staff ID baru sudah dipakai baris lain.']);
        }
        try {
            $pdo->prepare('UPDATE staff SET nip = ?, nama_lengkap = ?, jabatan = ?, email = ?, role = ?, is_active = ?, almutqon_user_id = ? WHERE id = ?')
                ->execute([$nip, $nama, $jabatan, $email, $role, $aktif, $link, (int) $ada['id']]);
            $pdo->prepare('INSERT INTO activity_log (staff_id, system_actor_id, action, dokumen_id, description, ip_address) VALUES (NULL, ?, \'staff_edit\', NULL, ?, ?)')
                ->execute([$svcId, 'Mengubah data staff via API v1: ' . $nip, $ip]);
        } catch (\Throwable $t) {
            error_log('API v1 staf_upsert gagal: ' . $t->getMessage());
            api_respond(500, ['success' => false, 'message' => 'Gagal menyimpan data staff.']);
        }
        $stmt = $pdo->prepare('SELECT * FROM staff WHERE id = ? LIMIT 1');
        $stmt->execute([(int) $ada['id']]);
        api_respond(200, ['success' => true, 'action' => 'upsert', 'created' => false, 'data' => api_staf_payload($stmt->fetch())]);
    }

    if ($link !== null) {
        $stmt = $pdo->prepare('SELECT id FROM staff WHERE almutqon_user_id = ? LIMIT 1');
        $stmt->execute([$link]);
        if ($stmt->fetch()) {
            api_respond(409, ['success' => false, 'message' => 'Tautan ID sudah dipakai baris lain.']);
        }
    }
    try {
        // Password acak tak diketahui siapa pun; aktivasi via reset oleh direktur/admin.
        $hash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
        $pdo->prepare('INSERT INTO staff (nip, nama_lengkap, jabatan, email, password, role, is_active, almutqon_user_id) VALUES (?,?,?,?,?,?,?,?)')
            ->execute([$nip, $nama, $jabatan, $email, $hash, $role, $aktif, $link]);
        $newId = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO activity_log (staff_id, system_actor_id, action, dokumen_id, description, ip_address) VALUES (NULL, ?, \'staff_add\', NULL, ?, ?)')
            ->execute([$svcId, 'Menambah staff via API v1: ' . $nip, $ip]);
    } catch (\Throwable $t) {
        error_log('API v1 staf_upsert gagal: ' . $t->getMessage());
        api_respond(500, ['success' => false, 'message' => 'Gagal menyimpan data staff.']);
    }
    $stmt = $pdo->prepare('SELECT * FROM staff WHERE id = ? LIMIT 1');
    $stmt->execute([$newId]);
    api_respond(200, ['success' => true, 'action' => 'upsert', 'created' => true, 'data' => api_staf_payload($stmt->fetch())]);
}

// --------------------------------------------------------------- toggle ---
if (!$ada) {
    api_respond(404, ['success' => false, 'message' => 'Staff tidak ditemukan.']);
}
if (($ada['role'] ?? '') === 'direktur') {
    api_respond(403, ['success' => false, 'message' => 'Baris direktur hanya lewat dashboard.']);
}
$baru = isset($input['is_active']) ? ((int) $input['is_active'] === 1 ? 1 : 0) : (((int) $ada['is_active'] === 1) ? 0 : 1);
$lepas = !empty($input['hapus_tautan']);
try {
    if ($lepas) {
        $pdo->prepare('UPDATE staff SET is_active = ?, almutqon_user_id = NULL WHERE id = ?')->execute([$baru, (int) $ada['id']]);
    } else {
        $pdo->prepare('UPDATE staff SET is_active = ? WHERE id = ?')->execute([$baru, (int) $ada['id']]);
    }
    $pdo->prepare('INSERT INTO activity_log (staff_id, system_actor_id, action, dokumen_id, description, ip_address) VALUES (NULL, ?, \'staff_toggle\', NULL, ?, ?)')
        ->execute([$svcId, 'Mengubah status aktif staff via API v1: ' . $nip, $ip]);
} catch (\Throwable $t) {
    error_log('API v1 staf_toggle gagal: ' . $t->getMessage());
    api_respond(500, ['success' => false, 'message' => 'Gagal mengubah status staff.']);
}
$stmt = $pdo->prepare('SELECT * FROM staff WHERE id = ? LIMIT 1');
$stmt->execute([(int) $ada['id']]);
api_respond(200, ['success' => true, 'action' => 'toggle', 'data' => api_staf_payload($stmt->fetch())]);
