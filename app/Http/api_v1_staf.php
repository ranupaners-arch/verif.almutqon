<?php
declare(strict_types=1);

// app/Http/api_v1_staf.php — API internal v1 untuk sinkron personel ALMUTQON.
// Endpoint publik: POST /api/v1/staf.php (auth Bearer, envelope JSON).
// Aksi (field `action`): upsert | toggle.
// Batasan integritas (cermin manage_staff.php + matriks izin):
//   - NIP SYSTEM-* tak tersentuh; baris role direktur tak tersentuh;
//     role `direktur` hanya lewat dashboard (API: staff|admin saja).
//   - Toggle: direktur terakhir yang aktif tak bisa dinonaktifkan.
// Natural key = NIP (untuk ALMUTQON: id_pengajar PGJ-XXXX).

require __DIR__ . '/api_v1_boot.php';

$svc = api_service_account($pdo);
$svcId = (int) $svc['id'];

$input = api_input();

$action = (string) ($input['action'] ?? '');
if (!in_array($action, ['upsert', 'toggle'], true)) {
    api_respond(400, ['success' => false, 'message' => 'Aksi tidak dikenali (upsert|toggle).']);
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

$stmt = $pdo->prepare('SELECT * FROM staff WHERE nip = ? LIMIT 1');
$stmt->execute([$nip]);
$ada = $stmt->fetch();

// --------------------------------------------------------------- upsert ---
if ($action === 'upsert') {
    $nama = trim((string) ($input['nama_lengkap'] ?? $input['nama'] ?? ''));
    $jabatan = trim((string) ($input['jabatan'] ?? ''));
    $email = trim((string) ($input['email'] ?? ''));
    $role = trim((string) ($input['role'] ?? 'staff'));
    $aktif = isset($input['is_active']) ? ((int) $input['is_active'] === 1 ? 1 : 0) : 1;
    $linkRaw = $input['almutqon_user_id'] ?? null;
    $link = ($linkRaw === null || $linkRaw === '') ? null : max(0, (int) $linkRaw);
    if ($link === 0) {
        $link = null;
    }

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
        try {
            $pdo->prepare('UPDATE staff SET nama_lengkap = ?, jabatan = ?, email = ?, role = ?, is_active = ?, almutqon_user_id = ? WHERE id = ?')
                ->execute([$nama, $jabatan, $email, $role, $aktif, $link, (int) $ada['id']]);
            $pdo->prepare('INSERT INTO activity_log (staff_id, action, dokumen_id, description, ip_address) VALUES (?, \'staff_edit\', NULL, ?, ?)')
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
        $pdo->prepare('INSERT INTO activity_log (staff_id, action, dokumen_id, description, ip_address) VALUES (?, \'staff_add\', NULL, ?, ?)')
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
    $pdo->prepare('INSERT INTO activity_log (staff_id, action, dokumen_id, description, ip_address) VALUES (?, \'staff_toggle\', NULL, ?, ?)')
        ->execute([$svcId, 'Mengubah status aktif staff via API v1: ' . $nip, $ip]);
} catch (\Throwable $t) {
    error_log('API v1 staf_toggle gagal: ' . $t->getMessage());
    api_respond(500, ['success' => false, 'message' => 'Gagal mengubah status staff.']);
}
$stmt = $pdo->prepare('SELECT * FROM staff WHERE id = ? LIMIT 1');
$stmt->execute([(int) $ada['id']]);
api_respond(200, ['success' => true, 'action' => 'toggle', 'data' => api_staf_payload($stmt->fetch())]);
