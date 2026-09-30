<?php
declare(strict_types=1);

// app/Http/api_v1_dokumen.php — API internal v1 untuk integrasi ALMUTQON.
// Endpoint publik: POST /api/v1/dokumen.php (wrapper di public/api/v1/).
// Auth: header `Authorization: Bearer <VERIF_API_TOKEN>` (.env, JAGA RAHASIA).
// Aksi (field `action`): create | revoke | restore | replace_pdf. Selalu JSON.
// - replace_pdf: ganti/unggah PDF dokumen (by `token` atau `no_surat`), PDF wajib
//   via multipart field `pdf`. Dipakai almutqon untuk unggah struk BER-QR setelah
//   dokumen dibuat (URL verifikasi baru diketahui pasca-create).
//
// - create: idempoten by no_surat (sudah ada → kembalikan baris lama + created:false).
//   PDF opsional via multipart field `pdf` (aturan sama dengan dashboard:
//   .pdf + application/pdf + ≤ MAX_UPLOAD_BYTES, nama acak cert_*.pdf).
// - revoke/restore: cari by `token` (64 hex) atau `no_surat`; catat activity_log.
// Pencatat = system actor ALMUTQON Bridge, terpisah dari akun manusia.

require __DIR__ . '/api_v1_boot.php';

$svc = api_service_account($pdo);
$svcId = (int) $svc['id'];
$svcNama = (string) $svc['display_name'];

$input = api_input();

$action = (string) ($input['action'] ?? '');
if (!in_array($action, ['create', 'revoke', 'restore', 'replace_pdf'], true)) {
    api_respond(400, ['success' => false, 'message' => 'Aksi tidak dikenali (create|revoke|restore|replace_pdf).']);
}

$ip = api_ip();

function api_doc_payload(array $doc): array
{
    return [
        'id' => (int) $doc['id'],
        'no_surat' => (string) $doc['no_surat'],
        'jenis' => (string) ($doc['jenis'] ?? 'dokumen'),
        'token' => (string) $doc['token'],
        'code_6digit' => (string) $doc['code_6digit'],
        'verify_url' => APP_URL . '/index.php?token=' . $doc['token'],
        'status' => (string) $doc['status'],
        'has_pdf' => !empty($doc['pdf_path']),
    ];
}

function api_find_doc(PDO $pdo, string $token, string $noSurat): array|false
{
    if ($token !== '') {
        $stmt = $pdo->prepare('SELECT * FROM dokumen WHERE token = ? LIMIT 1');
        $stmt->execute([$token]);
        $doc = $stmt->fetch();
        if ($doc) {
            return $doc;
        }
    }
    if ($noSurat !== '') {
        $stmt = $pdo->prepare('SELECT * FROM dokumen WHERE no_surat = ? LIMIT 1');
        $stmt->execute([$noSurat]);
        $doc = $stmt->fetch();
        if ($doc) {
            return $doc;
        }
    }
    return false;
}

// ---------------------------------------------------------------- create ---
if ($action === 'create') {
    $noSurat = trim((string) ($input['no_surat'] ?? ''));
    $perihal = trim((string) ($input['perihal'] ?? ''));
    $penerima = trim((string) ($input['penerima'] ?? ''));
    $tanggal = trim((string) ($input['tanggal_dokumen'] ?? ''));
    $jabatanTtd = trim((string) ($input['jabatan_ttd'] ?? $input['jabatan'] ?? ''));
    $jenis = jenis_dokumen_from_input($input['jenis'] ?? null);
    if ($jenis === null) {
        api_respond(400, ['success' => false, 'message' => 'Jenis tidak dikenali (' . implode('|', JENIS_DOKUMEN) . ').']);
    }
    $penandatangan = trim((string) ($input['penandatangan'] ?? ''));
    if ($penandatangan === '') {
        $penandatangan = $svcNama;
    }

    $tanggalOk = false;
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $tanggal, $tp)) {
        $tanggalOk = checkdate((int) $tp[2], (int) $tp[3], (int) $tp[1]);
    }

    // Nomor wajib valid sebelum pencarian idempoten.
    if ($noSurat === '' || strlen($noSurat) > 255) {
        api_respond(400, ['success' => false, 'message' => 'Nomor surat wajib diisi (maksimal 255 karakter).']);
    }

    // Retry hanya perlu nomor dokumen; data lama tetap menjadi sumber kebenaran.
    $existing = api_find_doc($pdo, '', $noSurat);
    if ($existing) {
        api_respond(200, [
            'success' => true, 'action' => 'create', 'created' => false,
            'data' => api_doc_payload($existing),
        ]);
    }

    // Validasi sama dengan form dashboard (batas 255 + tanggal kalender valid).
    if ($perihal === '' || strlen($perihal) > 255) {
        api_respond(400, ['success' => false, 'message' => 'Perihal wajib diisi (maksimal 255 karakter).']);
    } elseif ($penerima === '' || strlen($penerima) > 255) {
        api_respond(400, ['success' => false, 'message' => 'Penerima wajib diisi (maksimal 255 karakter).']);
    } elseif (!$tanggalOk) {
        api_respond(400, ['success' => false, 'message' => 'Tanggal dokumen tidak valid (Y-m-d).']);
    } elseif ($jabatanTtd === '' || strlen($jabatanTtd) > 255) {
        api_respond(400, ['success' => false, 'message' => 'Jabatan penandatangan wajib diisi (maksimal 255 karakter).']);
    } elseif (strlen($penandatangan) > 255) {
        api_respond(400, ['success' => false, 'message' => 'Nama penandatangan maksimal 255 karakter.']);
    }

    // PDF opsional: validasi dulu SEBELUM insert agar gagal-cepat tanpa baris yatim.
    $pdfOk = false;
    $pdfError = '';
    $pdfTmp = '';
    if (isset($_FILES['pdf']) && (int) ($_FILES['pdf']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $pdfErrorCode = (int) ($_FILES['pdf']['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($pdfErrorCode !== UPLOAD_ERR_OK) {
            $pdfError = match ($pdfErrorCode) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Ukuran PDF melebihi batas maksimal 10 MB.',
                UPLOAD_ERR_PARTIAL => 'PDF hanya terkirim sebagian.',
                default => 'Gagal menerima PDF.',
            };
        } elseif ((int) $_FILES['pdf']['size'] > MAX_UPLOAD_BYTES) {
            $pdfError = 'Ukuran PDF melebihi batas maksimal 10 MB.';
        } else {
            $pdfTmp = (string) $_FILES['pdf']['tmp_name'];
            $pdfExt = strtolower(pathinfo((string) $_FILES['pdf']['name'], PATHINFO_EXTENSION));
            $pdfMime = (new finfo(FILEINFO_MIME_TYPE))->file($pdfTmp);
            if ($pdfExt !== 'pdf' || $pdfMime !== 'application/pdf') {
                $pdfError = 'Format file harus PDF.';
            } else {
                $pdfOk = true;
            }
        }
        if ($pdfError !== '') {
            api_respond(400, ['success' => false, 'message' => $pdfError]);
        }
    }

    try {
        $pdo->beginTransaction();

        // Invoice/struk tanpa nomor urut (tak masuk hitungan & rekap penomoran).
        $nomorUrut = null;
        if (jenis_dokumen_bernomor($jenis)) {
            if (preg_match('/^(\d+)/', $noSurat, $m)) {
                $nomorUrut = (int) $m[1];
            } else {
                $stmt = $pdo->query("SELECT MAX(nomor_urut) AS max_urut FROM dokumen WHERE jenis = 'dokumen' FOR UPDATE");
                $last = $stmt->fetch();
                $nomorUrut = (int) ($last['max_urut'] ?? 0) + 1;
            }
        }

        $token = hash_hmac('sha256', $noSurat . $penerima . $tanggal . time(), APP_SECRET_KEY);

        // Kode 6-digit UNIQUE: ulangi bila bentrok (perbaiki bug tabrakan dashboard).
        $docId = null;
        $code = '';
        for ($i = 0; $i < 5; $i++) {
            $code = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
            try {
                $stmt = $pdo->prepare("INSERT INTO dokumen (token, code_6digit, nomor_urut, jenis, no_surat, perihal, penerima, tanggal_dokumen, penandatangan, jabatan, staff_id, system_actor_id, status, pdf_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, 'active', NULL)");
                $stmt->execute([$token, $code, $nomorUrut, $jenis, $noSurat, $perihal, $penerima, $tanggal, $penandatangan, $jabatanTtd, $svcId]);
                $docId = (int) $pdo->lastInsertId();
                break;
            } catch (PDOException $e) {
                // 23000 pada code_6digit/no_surat → coba kode baru (no_surat dicek lagi di bawah).
                if ($e->getCode() !== '23000' || $i === 4) {
                    throw $e;
                }
            }
        }

        // Kecil kemungkinan no_surat disalip request lain di antara cek & insert:
        // perlakukan sebagai idempoten, bukan error.
        if ($docId === null) {
            $existing = api_find_doc($pdo, '', $noSurat);
            if ($existing) {
                $pdo->rollBack();
                api_respond(200, [
                    'success' => true, 'action' => 'create', 'created' => false,
                    'data' => api_doc_payload($existing),
                ]);
            }
            throw new RuntimeException('Gagal membuat kode verifikasi unik.');
        }

        $pdfName = null;
        $movedFile = null;
        if ($pdfOk) {
            $pdfName = uniqid('cert_') . '.pdf';
            $movedFile = STORAGE_PATH . '/uploads/' . $pdfName;
            if (!is_dir(STORAGE_PATH . '/uploads/') && !mkdir(STORAGE_PATH . '/uploads/', 0755, true)) {
                throw new RuntimeException('Gagal menyiapkan penyimpanan PDF.');
            }
            if (!move_uploaded_file($pdfTmp, $movedFile)) {
                throw new RuntimeException('Gagal menyimpan PDF.');
            }
            $stmt = $pdo->prepare('UPDATE dokumen SET pdf_path = ? WHERE id = ?');
            $stmt->execute([$pdfName, $docId]);
        }

        $pdo->prepare("INSERT INTO activity_log (staff_id, system_actor_id, action, dokumen_id, description, ip_address) VALUES (NULL, ?, 'create_doc', ?, ?, ?)")
            ->execute([$svcId, $docId, 'Membuat dokumen via API v1: ' . $noSurat, $ip]);

        $pdo->commit();

        $doc = api_find_doc($pdo, '', $noSurat);
        api_respond(200, [
            'success' => true, 'action' => 'create', 'created' => true,
            'data' => api_doc_payload($doc ?: ['id' => $docId, 'no_surat' => $noSurat, 'jenis' => $jenis, 'token' => $token, 'code_6digit' => $code, 'status' => 'active', 'pdf_path' => $pdfName]),
        ]);
    } catch (\Throwable $t) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (!empty($movedFile)) {
            @unlink($movedFile);
        }
        error_log('API v1 create gagal: ' . $t->getMessage());
        api_respond(500, ['success' => false, 'message' => 'Gagal menyimpan dokumen.']);
    }
}

// ------------------------------------------------------- revoke / restore ---
$tokenIn = trim((string) ($input['token'] ?? ''));
$noIn = trim((string) ($input['no_surat'] ?? ''));
if ($tokenIn !== '' && (!ctype_xdigit($tokenIn) || strlen($tokenIn) !== 64)) {
    api_respond(400, ['success' => false, 'message' => 'Format token tidak valid.']);
}
if ($tokenIn === '' && $noIn === '') {
    api_respond(400, ['success' => false, 'message' => 'Sertakan `token` atau `no_surat`.']);
}

$doc = api_find_doc($pdo, $tokenIn, $noIn);
if (!$doc) {
    api_respond(404, ['success' => false, 'message' => 'Dokumen tidak ditemukan.']);
}

// ------------------------------------------------------------- replace_pdf ---
if ($action === 'replace_pdf') {
    if (!isset($_FILES['pdf']) || (int) ($_FILES['pdf']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        api_respond(400, ['success' => false, 'message' => 'Field PDF wajib diisi.']);
    }
    $errCode = (int) $_FILES['pdf']['error'];
    if ($errCode !== UPLOAD_ERR_OK) {
        $msg = match ($errCode) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Ukuran PDF melebihi batas maksimal 10 MB.',
            UPLOAD_ERR_PARTIAL => 'PDF hanya terkirim sebagian.',
            default => 'Gagal menerima PDF.',
        };
        api_respond(400, ['success' => false, 'message' => $msg]);
    }
    if ((int) $_FILES['pdf']['size'] > MAX_UPLOAD_BYTES) {
        api_respond(400, ['success' => false, 'message' => 'Ukuran PDF melebihi batas maksimal 10 MB.']);
    }
    $pdfTmp = (string) $_FILES['pdf']['tmp_name'];
    $pdfExt = strtolower(pathinfo((string) $_FILES['pdf']['name'], PATHINFO_EXTENSION));
    $pdfMime = (new finfo(FILEINFO_MIME_TYPE))->file($pdfTmp);
    if ($pdfExt !== 'pdf' || $pdfMime !== 'application/pdf') {
        api_respond(400, ['success' => false, 'message' => 'Format file harus PDF.']);
    }

    $pdfName = uniqid('cert_') . '.pdf';
    $target = STORAGE_PATH . '/uploads/' . $pdfName;
    if (!is_dir(STORAGE_PATH . '/uploads/') && !mkdir(STORAGE_PATH . '/uploads/', 0755, true)) {
        api_respond(500, ['success' => false, 'message' => 'Gagal menyiapkan penyimpanan PDF.']);
    }
    if (!move_uploaded_file($pdfTmp, $target)) {
        api_respond(500, ['success' => false, 'message' => 'Gagal menyimpan PDF.']);
    }
    try {
        $pdo->prepare('UPDATE dokumen SET pdf_path = ? WHERE id = ?')->execute([$pdfName, (int) $doc['id']]);
        $pdo->prepare("INSERT INTO activity_log (staff_id, system_actor_id, action, dokumen_id, description, ip_address) VALUES (NULL, ?, 'replace_pdf', ?, ?, ?)")
            ->execute([$svcId, (int) $doc['id'], 'Mengganti PDF dokumen via API v1: ' . $doc['no_surat'], $ip]);
    } catch (\Throwable $t) {
        @unlink($target);
        error_log('API v1 replace_pdf gagal: ' . $t->getMessage());
        api_respond(500, ['success' => false, 'message' => 'Gagal memperbarui PDF dokumen.']);
    }
    // Berkas lama dihapus setelah DB sukses (kegagalan unlink tak fatal).
    $oldPath = trim((string) ($doc['pdf_path'] ?? ''));
    if ($oldPath !== '') {
        $oldFile = STORAGE_PATH . '/uploads/' . basename($oldPath);
        if (is_file($oldFile)) {
            @unlink($oldFile);
        }
    }
    $doc['pdf_path'] = $pdfName;
    api_respond(200, ['success' => true, 'action' => 'replace_pdf', 'data' => api_doc_payload($doc)]);
}

$newStatus = $action === 'revoke' ? 'revoked' : 'active';
try {
    $pdo->prepare('UPDATE dokumen SET status = ? WHERE id = ?')->execute([$newStatus, (int) $doc['id']]);
    $pdo->prepare('INSERT INTO activity_log (staff_id, system_actor_id, action, dokumen_id, description, ip_address) VALUES (NULL, ?, ?, ?, ?, ?)')
        ->execute([$svcId, $newStatus === 'revoked' ? 'revoke_document' : 'restore_document', (int) $doc['id'], ($newStatus === 'revoked' ? 'Mencabut' : 'Mengaktifkan kembali') . ' dokumen via API v1: ' . $doc['no_surat'], $ip]);
    $doc['status'] = $newStatus;
    api_respond(200, ['success' => true, 'action' => $action, 'data' => api_doc_payload($doc)]);
} catch (\Throwable $t) {
    error_log('API v1 ' . $action . ' gagal: ' . $t->getMessage());
    api_respond(500, ['success' => false, 'message' => 'Gagal memperbarui status dokumen.']);
}
