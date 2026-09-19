<?php
declare(strict_types=1);

// app/Http/dokumen_actions.php — aksi mutasi dokumen bersama untuk dashboard.php
// dan data_invoice.php (agar tidak ada duplikasi logika otorisasi/audit).
// Pemanggil wajib sudah: Bootstrap ($pdo), cek sesi staff, dan verify_csrf().
// $kembali = halaman tujuan redirect sukses (mis. 'dashboard.php').
// Tiap fungsi mengembalikan ['success' => string, 'error' => string].

function dokumen_aksi_upload(PDO $pdo, int $staffId, bool $isAdminOrDirektur): array
{
    $success = '';
    $error = '';

    $pdo->prepare("INSERT INTO activity_log (action, description, ip_address) VALUES ('upload_attempt', 'Percobaan upload PDF', ?)")
        ->execute([$_SERVER['REMOTE_ADDR'] ?? 'cli']);

    if (!rate_limit_ok($pdo, 'upload_attempt', (string) ($_SERVER['REMOTE_ADDR'] ?? 'cli'), MAX_UPLOAD_ATTEMPTS, UPLOAD_RATE_WINDOW_SECONDS)) {
        $error = 'Terlalu banyak unggahan dalam waktu singkat. Silakan coba lagi nanti.';
    } else {
        $doc_id = filter_input(INPUT_POST, 'doc_id', FILTER_VALIDATE_INT);
        if (!$doc_id) {
            $error = 'ID dokumen tidak valid.';
        } else {
            $stmt = $pdo->prepare('SELECT staff_id FROM dokumen WHERE id = ?');
            $stmt->execute([$doc_id]);
            $target = $stmt->fetch();

            if (!$target) {
                $error = 'Dokumen tidak ditemukan.';
            } elseif (!$isAdminOrDirektur && (int) $target['staff_id'] !== (int) $staffId) {
                $error = 'Anda hanya boleh mengunggah PDF pada dokumen yang Anda buat.';
            } else {
                $upload_error = (int) ($_FILES['file_surat']['error'] ?? UPLOAD_ERR_NO_FILE);
                if ($upload_error !== UPLOAD_ERR_OK) {
                    $error = match ($upload_error) {
                        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Ukuran file melebihi batas maksimal 10 MB.',
                        UPLOAD_ERR_NO_FILE => 'Silakan pilih file PDF terlebih dahulu.',
                        UPLOAD_ERR_PARTIAL => 'File hanya terkirim sebagian. Silakan coba lagi.',
                        default => 'Gagal mengupload file. Silakan coba lagi.',
                    };
                } elseif ((int) $_FILES['file_surat']['size'] > MAX_UPLOAD_BYTES) {
                    $error = 'Ukuran file melebihi batas maksimal 10 MB.';
                } else {
                    $new_filename = uniqid('cert_') . '.pdf';
                    $target_file = STORAGE_PATH . '/uploads/' . $new_filename;

                    $file_ext = strtolower(pathinfo($_FILES['file_surat']['name'], PATHINFO_EXTENSION));
                    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['file_surat']['tmp_name']);

                    if ($file_ext !== 'pdf' || $mime !== 'application/pdf') {
                        $error = 'Format file harus PDF!';
                    } elseif (!is_dir(STORAGE_PATH . '/uploads/') && !mkdir(STORAGE_PATH . '/uploads/', 0755, true)) {
                        $error = 'Gagal membuat folder penyimpanan file.';
                    } elseif (!move_uploaded_file($_FILES['file_surat']['tmp_name'], $target_file)) {
                        $error = 'Gagal memindahkan file. Cek permission folder uploads/.';
                    } else {
                        try {
                            $stmt = $pdo->prepare('UPDATE dokumen SET pdf_path = ? WHERE id = ?');
                            $stmt->execute([$new_filename, $doc_id]);
                            $pdo->prepare("INSERT INTO activity_log (staff_id, action, dokumen_id, description, ip_address) VALUES (?, 'upload_pdf', ?, ?, ?)")
                                ->execute([$staffId, $doc_id, "Mengupload PDF untuk dokumen ID: $doc_id", $_SERVER['REMOTE_ADDR'] ?? 'cli']);
                            $success = 'File PDF berhasil diupload dan dilampirkan ke dokumen!';
                        } catch (PDOException $e) {
                            error_log('Upload PDF update DB gagal: ' . $e->getMessage());
                            @unlink($target_file);
                            $error = 'Gagal memperbarui data dokumen. Silakan coba lagi.';
                        }
                    }
                }
            }
        }
    }

    if ($error !== '') {
        $pdo->prepare("INSERT INTO activity_log (staff_id, action, dokumen_id, description, ip_address) VALUES (?, 'upload_failed', ?, ?, ?)")
            ->execute([$staffId, $doc_id ?? null, $error, $_SERVER['REMOTE_ADDR'] ?? 'cli']);
    }

    return ['success' => $success, 'error' => $error];
}

function dokumen_aksi_hapus(PDO $pdo, int $staffId, bool $isAdminOrDirektur, string $kembali): void
{
    $error_msg = '';
    $id_hapus = filter_input(INPUT_POST, 'doc_id', FILTER_VALIDATE_INT);
    if (!$isAdminOrDirektur) {
        $error_msg = 'Hanya admin/direktur yang boleh menghapus dokumen.';
    } elseif (!$id_hapus) {
        $error_msg = 'ID dokumen tidak valid.';
    } else {
        $stmt = $pdo->prepare("SELECT pdf_path, no_surat FROM dokumen WHERE id = ?");
        $stmt->execute([$id_hapus]);
        $doc = $stmt->fetch();
        if (!$doc) {
            $error_msg = 'Dokumen tidak ditemukan.';
        } else {
            $filePath = STORAGE_PATH . '/uploads/' . basename((string) ($doc['pdf_path'] ?? ''));
            if ($doc['pdf_path'] && file_exists($filePath)) {
                unlink($filePath);
            }
            try {
                $pdo->prepare("DELETE FROM dokumen WHERE id = ?")->execute([$id_hapus]);
                $pdo->prepare("INSERT INTO activity_log (staff_id, action, dokumen_id, description, ip_address) VALUES (?, 'delete_document', ?, ?, ?)")
                    ->execute([$staffId, $id_hapus, "Menghapus dokumen: " . $doc['no_surat'], $_SERVER['REMOTE_ADDR'] ?? 'cli']);
                header("Location: {$kembali}?msg=deleted");
                exit;
            } catch (PDOException $e) {
                error_log('Menghapus dokumen gagal: ' . $e->getMessage());
                $error_msg = 'Gagal menghapus dokumen. Silakan coba lagi.';
            }
        }
    }
    $_SESSION['aksi_error'] = $error_msg;
    header("Location: {$kembali}");
    exit;
}

function dokumen_aksi_status(PDO $pdo, int $staffId, bool $isAdminOrDirektur, string $kembali): void
{
    $aksi = (string) ($_POST['action'] ?? '');
    if (!in_array($aksi, ['revoke_document', 'restore_document'], true) || !$isAdminOrDirektur) {
        $_SESSION['aksi_error'] = 'Aksi tidak diizinkan.';
        header("Location: {$kembali}");
        exit;
    }
    $id_cabut = filter_input(INPUT_POST, 'doc_id', FILTER_VALIDATE_INT);
    $status_baru = ($aksi === 'revoke_document') ? 'revoked' : 'active';
    if (!$id_cabut) {
        $_SESSION['aksi_error'] = 'ID dokumen tidak valid.';
        header("Location: {$kembali}");
        exit;
    }
    try {
        $pdo->prepare("UPDATE dokumen SET status = ? WHERE id = ?")->execute([$status_baru, $id_cabut]);
        $desc = $status_baru === 'revoked'
            ? "Mencabut dokumen ID: $id_cabut"
            : "Mengaktifkan kembali dokumen ID: $id_cabut";
        $pdo->prepare("INSERT INTO activity_log (staff_id, action, dokumen_id, description, ip_address) VALUES (?, ?, ?, ?, ?)")
            ->execute([$staffId, $status_baru === 'revoked' ? 'revoke_document' : 'restore_document', $id_cabut, $desc, $_SERVER['REMOTE_ADDR'] ?? 'cli']);
        header('Location: ' . $kembali . '?msg=' . ($status_baru === 'revoked' ? 'revoked' : 'restored'));
        exit;
    } catch (PDOException $e) {
        error_log('Update status dokumen gagal: ' . $e->getMessage());
        $_SESSION['aksi_error'] = 'Gagal memperbarui status dokumen. Silakan coba lagi.';
        header("Location: {$kembali}");
        exit;
    }
}

// Pesan error yang dititipkan via redirect (aksi hapus/status) → pindahkan ke $error_msg.
function dokumen_ambil_pesan(): string
{
    $msg = (string) ($_SESSION['aksi_error'] ?? '');
    unset($_SESSION['aksi_error']);
    return $msg;
}
