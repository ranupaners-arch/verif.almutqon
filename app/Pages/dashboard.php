<?php
require dirname(__DIR__) . '/Bootstrap.php';
require_once dirname(__DIR__) . '/Http/dokumen_actions.php';

if (!isset($_SESSION['is_staff'])) { header("Location: staff.php"); exit; }

$staff_id = (int) ($_SESSION['staff_id'] ?? 0);
$akun = akun_ringkas($pdo, $staff_id);

$stmt = $pdo->prepare("SELECT role, nama_lengkap, jabatan FROM staff WHERE id = ?");
$stmt->execute([$staff_id]);
$staff_row = $stmt->fetch();
$staff_role = $staff_row['role'] ?? 'staff';
$staff_nama = $staff_row['nama_lengkap'] ?? ($_SESSION['staff_nama'] ?? '');
$staff_jabatan = $staff_row['jabatan'] ?? ($_SESSION['staff_jabatan'] ?? '');

$is_admin_or_direktur = in_array($staff_role, ['direktur', 'admin']);

$success_msg = ''; 
$error_msg = '';

// 1. HANDLE UPLOAD PDF (via shared actions)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_pdf') {
    if (!verify_csrf()) {
        $error_msg = 'Permintaan tidak valid. Silakan muat ulang halaman dan coba lagi.';
    } else {
        $result = dokumen_aksi_upload($pdo, $staff_id, $is_admin_or_direktur);
        $success_msg = $result['success'];
        $error_msg   = $result['error'];
    }
}

// Shell variables
$pageTitle = 'Dashboard';
$pageTitleFull = null;
$navActive = 'dashboard';

// 2. HANDLE BUAT DOKUMEN BARU (TANPA PDF)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf() && isset($_POST['no_surat']) && !isset($_POST['action'])) {
    $no_surat = trim($_POST['no_surat']);
    $perihal = trim($_POST['perihal']);
    $penerima = trim($_POST['penerima']);
    $tanggal = trim($_POST['tanggal_dokumen']);
    $jabatan_ttd = trim($_POST['jabatan_ttd']);

    $tanggal_ok = false;
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $tanggal, $tp)) {
        $tanggal_ok = checkdate((int) $tp[2], (int) $tp[3], (int) $tp[1]);
    }

    $validasi_dokumen = '';
    if ($no_surat === '' || strlen($no_surat) > 255) {
        $validasi_dokumen = 'Nomor surat wajib diisi (maksimal 255 karakter).';
    } elseif ($perihal === '' || strlen($perihal) > 255) {
        $validasi_dokumen = 'Perihal wajib diisi (maksimal 255 karakter).';
    } elseif ($penerima === '' || strlen($penerima) > 255) {
        $validasi_dokumen = 'Penerima wajib diisi (maksimal 255 karakter).';
    } elseif (!$tanggal_ok) {
        $validasi_dokumen = 'Tanggal dokumen tidak valid.';
    } elseif ($jabatan_ttd === '' || strlen($jabatan_ttd) > 255) {
        $validasi_dokumen = 'Jabatan penandatangan wajib diisi (maksimal 255 karakter).';
    }

    if ($validasi_dokumen) {
        $error_msg = $validasi_dokumen;
    } else {
        $stmt = $pdo->prepare("SELECT id FROM dokumen WHERE no_surat = ?"); 
        $stmt->execute([$no_surat]);
        if ($stmt->fetch()) { 
            $error_msg = "Nomor surat $no_surat sudah terdaftar!"; 
        } else {
        $token = hash_hmac('sha256', $no_surat . $penerima . $tanggal . time(), APP_SECRET_KEY);
        $code_6digit = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        
        try {
            $pdo->beginTransaction();
            $nomor_urut = null;
            if (preg_match('/^(\d+)/', $no_surat, $matches)) {
                $nomor_urut = intval($matches[1]);
            } else {
                $stmt = $pdo->query("SELECT MAX(nomor_urut) AS max_urut FROM dokumen WHERE jenis = 'dokumen' FOR UPDATE");
                $last = $stmt->fetch();
                $nomor_urut = (int) ($last['max_urut'] ?? 0) + 1;
            }

            $stmt = $pdo->prepare("INSERT INTO dokumen (token, code_6digit, nomor_urut, jenis, no_surat, perihal, penerima, tanggal_dokumen, penandatangan, jabatan, staff_id, status, pdf_path) VALUES (?, ?, ?, 'dokumen', ?, ?, ?, ?, ?, ?, ?, 'active', NULL)");
            $stmt->execute([$token, $code_6digit, $nomor_urut, $no_surat, $perihal, $penerima, $tanggal, $staff_nama, $jabatan_ttd, $staff_id]);

            $doc_id = $pdo->lastInsertId();
            $pdo->prepare("INSERT INTO activity_log (staff_id, action, dokumen_id, description, ip_address) VALUES (?, 'create_doc', ?, ?, ?)")->execute([$staff_id, $doc_id, "Membuat dokumen: $no_surat", $_SERVER['REMOTE_ADDR']]);

            $pdo->commit();
            $success_msg = "Dokumen dan QR Code berhasil dibuat! (File PDF dapat diupload nanti)";
            $last_token = $token;
            $last_code = $code_6digit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Membuat dokumen gagal: ' . $e->getMessage());
            $error_msg = 'Gagal menyimpan dokumen. Silakan coba lagi.';
        }
    }
    }
}

// 3. HANDLE HAPUS DOKUMEN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf() && ($_POST['action'] ?? '') === 'delete_document' && $is_admin_or_direktur) {
    $id_hapus = filter_input(INPUT_POST, 'doc_id', FILTER_VALIDATE_INT);
    if (!$id_hapus) {
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
                    ->execute([$staff_id, $id_hapus, "Menghapus dokumen: " . $doc['no_surat'], $_SERVER['REMOTE_ADDR']]);
                header("Location: dashboard.php?msg=deleted");
                exit;
            } catch (PDOException $e) {
                error_log('Menghapus dokumen gagal: ' . $e->getMessage());
                $error_msg = 'Gagal menghapus dokumen. Silakan coba lagi.';
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf() && in_array($_POST['action'] ?? '', ['revoke_document', 'restore_document'], true) && $is_admin_or_direktur) {
    $id_cabut = filter_input(INPUT_POST, 'doc_id', FILTER_VALIDATE_INT);
    $status_baru = ($_POST['action'] === 'revoke_document') ? 'revoked' : 'active';
    if (!$id_cabut) {
        $error_msg = 'ID dokumen tidak valid.';
    } else {
        try {
            $pdo->prepare("UPDATE dokumen SET status = ? WHERE id = ?")->execute([$status_baru, $id_cabut]);
            $desc = $status_baru === 'revoked'
                ? "Mencabut dokumen ID: $id_cabut"
                : "Mengaktifkan kembali dokumen ID: $id_cabut";
            $pdo->prepare("INSERT INTO activity_log (staff_id, action, dokumen_id, description, ip_address) VALUES (?, ?, ?, ?, ?)")
                ->execute([$staff_id, $status_baru === 'revoked' ? 'revoke_document' : 'restore_document', $id_cabut, $desc, $_SERVER['REMOTE_ADDR']]);
            header('Location: dashboard.php?msg=' . ($status_baru === 'revoked' ? 'revoked' : 'restored'));
            exit;
        } catch (PDOException $e) {
            error_log('Update status dokumen gagal: ' . $e->getMessage());
            $error_msg = 'Gagal memperbarui status dokumen. Silakan coba lagi.';
        }
    }
}

if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'deleted') {
        $success_msg = "Dokumen berhasil dihapus!";
    } elseif ($_GET['msg'] == 'revoked') {
        $success_msg = 'Dokumen berhasil dicabut dan tidak lagi dapat diverifikasi publik.';
    } elseif ($_GET['msg'] == 'restored') {
        $success_msg = 'Dokumen berhasil diaktifkan kembali.';
    }
}

// FETCH DATA — filter (status) + cari (q) + pagination
$q = trim((string) ($_GET['q'] ?? ''));
$statusF = (string) ($_GET['status'] ?? '');
$statusF = in_array($statusF, ['active', 'revoked'], true) ? $statusF : '';
$perPage = 10;
$curPage = max(1, (int) ($_GET['page'] ?? 1));

$where = ["d.jenis = 'dokumen'"];
$params = [];
if ($q !== '') {
    $where[] = '(d.no_surat LIKE ? OR d.perihal LIKE ? OR d.penerima LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}
if ($statusF !== '') {
    $where[] = 'd.status = ?';
    $params[] = $statusF;
}
$whereSql = implode(' AND ', $where);

$stmt = $pdo->prepare("SELECT COUNT(*) FROM dokumen d WHERE $whereSql");
$stmt->execute($params);
$totalDocs = (int) $stmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalDocs / $perPage));
$curPage = min($curPage, $totalPages);
$offset = ($curPage - 1) * $perPage;

$stmt = $pdo->prepare("SELECT d.*, COALESCE(s.nama_lengkap, sa.display_name) AS staff_name FROM dokumen d LEFT JOIN staff s ON d.staff_id = s.id LEFT JOIN system_actors sa ON d.system_actor_id = sa.id WHERE $whereSql ORDER BY (d.nomor_urut IS NULL), d.nomor_urut DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$docs = $stmt->fetchAll();

$buildQs = function (array $extra = []) use ($q, $statusF) {
    $arr = $extra;
    if ($q !== '') { $arr['q'] = $q; }
    if (!isset($arr['status']) && $statusF !== '') { $arr['status'] = $statusF; }
    return http_build_query($arr);
};

$stmt = $pdo->query("SELECT MAX(CASE WHEN jenis = 'dokumen' THEN nomor_urut END) AS last_num, SUM(jenis = 'dokumen') AS total, SUM(jenis = 'invoice') AS invoice FROM dokumen"); 
$stats = $stmt->fetch();

// Jumlah dokumen yang dibuat oleh staff yang sedang login.
$stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM dokumen WHERE staff_id = ? AND jenis = 'dokumen'");
$stmt->execute([$staff_id]);
$my_count = (int) ($stmt->fetch()['cnt'] ?? 0);

// Ringkasan operasional hanya untuk pengelola. Semua kondisi memakai data
// yang memang sudah ada agar tidak menambah proses manual bagi staff.
$operational = ['missing_pdf' => 0, 'revoked' => 0, 'today' => 0];
$staff_summary = [];
if ($is_admin_or_direktur) {
    $stmt = $pdo->query("SELECT
        SUM(CASE WHEN jenis = 'dokumen' AND status = 'active' AND (pdf_path IS NULL OR pdf_path = '') THEN 1 ELSE 0 END) AS missing_pdf,
        SUM(CASE WHEN status = 'revoked' THEN 1 ELSE 0 END) AS revoked,
        SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) AS today
        FROM dokumen");
    $operational = $stmt->fetch() ?: $operational;
}
if ($staff_role === 'direktur') {
    $stmt = $pdo->query("SELECT s.nama_lengkap, s.jabatan,
        SUM(CASE WHEN d.jenis = 'dokumen' THEN 1 ELSE 0 END) AS document_count,
        SUM(CASE WHEN d.jenis = 'invoice' THEN 1 ELSE 0 END) AS invoice_count,
        MAX(d.created_at) AS last_document_at
        FROM staff s
        LEFT JOIN dokumen d ON d.staff_id = s.id
        WHERE s.is_active = 1
        GROUP BY s.id, s.nama_lengkap, s.jabatan
        ORDER BY document_count DESC, last_document_at DESC, s.nama_lengkap ASC
        LIMIT 6");
    $staff_summary = $stmt->fetchAll();
}

// Shell variables
$pageTitle = 'Dashboard';
$navActive = 'dashboard';
$extraJs = ['assets/vendor/qr-code-styling.js'];
?>
<?php include __DIR__ . '/_head.php'; ?>

<?php include __DIR__ . '/_sidebar.php'; ?>
<?php include __DIR__ . '/_masthead.php'; ?>

<main class="page page--wide dashboard-page">
    <!-- STATS -->
    <div class="grid4" style="margin-bottom:24px">
        <div class="stat">
            <span class="stat__icon stat__icon--green material-symbols-rounded" aria-hidden="true">article</span>
            <span class="stat__body">
                <span class="stat__value"><?= (int) ($stats['total'] ?? 0) ?></span>
                <span class="stat__label">Total Dokumen</span>
            </span>
        </div>
        <div class="stat">
            <span class="stat__icon stat__icon--teal material-symbols-rounded" aria-hidden="true">format_list_numbered</span>
            <span class="stat__body">
                <span class="stat__value">#<?= str_pad((string) ($stats['last_num'] ?? 0), 3, '0', STR_PAD_LEFT) ?></span>
                <span class="stat__label">Nomor Urut</span>
            </span>
        </div>
        <div class="stat">
            <span class="stat__icon stat__icon--amber material-symbols-rounded" aria-hidden="true">receipt_long</span>
            <span class="stat__body">
                <span class="stat__value"><?= (int) ($stats['invoice'] ?? 0) ?></span>
                <span class="stat__label">Invoice / Struk</span>
            </span>
        </div>
        <div class="stat">
            <span class="stat__icon material-symbols-rounded" aria-hidden="true">person</span>
            <span class="stat__body">
                <span class="stat__value"><?= $my_count ?></span>
                <span class="stat__label">Dokumen Anda</span>
            </span>
        </div>
    </div>

    <!-- NOTIFIKASI -->
    <?php if ($success_msg): ?>
    <?php if (isset($last_token)): $verify_url = APP_URL . "/index.php?token=" . $last_token; ?>
    <div class="panel" style="margin-bottom:22px">
        <div class="panel__hd">
            <h2 class="panel__title ok-text"><?= htmlspecialchars($success_msg) ?></h2>
            <div class="row">
                <span class="badge badge-ok">Token &amp; QR dibuat</span>
                <span class="badge badge-gold mono"><?= htmlspecialchars($last_code) ?></span>
            </div>
        </div>
        <div class="panel__bd grid2">
            <div class="stack">
                <div class="kv">
                    <p class="kv__label">Kode Verifikasi 6 Digit</p>
                    <p class="kv__value"><?= htmlspecialchars($last_code) ?></p>
                </div>
                <div>
                    <p class="kv__label" style="margin-bottom:6px">Link Verifikasi</p>
                    <div class="copy-row">
                        <code><?= htmlspecialchars($verify_url) ?></code>
                        <button class="btn btn-soft btn-sm" onclick="navigator.clipboard.writeText(<?= htmlspecialchars((string) json_encode($verify_url), ENT_QUOTES) ?>)">Copy</button>
                    </div>
                </div>
            </div>
            <div class="panel" style="text-align:center">
                <p class="kv__label" style="margin-bottom:10px">QR Code Verifikasi</p>
                <div id="qr-code-container" style="display:flex;justify-content:center"></div>
                <button class="btn btn-primary btn-block" style="margin-top:14px" onclick="downloadQR()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Download QR (HD PNG)
                </button>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="alert alert-ok">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0"><path d="M5 13l4 4L19 7"/></svg>
        <span><?= htmlspecialchars($success_msg) ?></span>
    </div>
    <?php endif; endif; ?>

    <?php if ($error_msg): ?>
    <div class="alert alert-err">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0"><path d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span><?= htmlspecialchars($error_msg) ?></span>
    </div>
    <?php endif; ?>

    <?php if ($is_admin_or_direktur): ?>
    <section class="ops-grid" aria-label="Ringkasan operasional" style="margin-bottom:24px">
        <div class="panel ops-panel">
            <div class="ops-panel__icon material-symbols-rounded" aria-hidden="true">upload_file</div>
            <div>
                <p class="ops-panel__label">Perlu PDF</p>
                <p class="ops-panel__value"><?= (int) ($operational['missing_pdf'] ?? 0) ?></p>
                <p class="ops-panel__note">Dokumen aktif belum memiliki lampiran</p>
            </div>
        </div>
        <div class="panel ops-panel">
            <div class="ops-panel__icon ops-panel__icon--warn material-symbols-rounded" aria-hidden="true">gpp_maybe</div>
            <div>
                <p class="ops-panel__label">Dokumen Dicabut</p>
                <p class="ops-panel__value"><?= (int) ($operational['revoked'] ?? 0) ?></p>
                <p class="ops-panel__note">Tinjau alasan dan tindak lanjutnya</p>
            </div>
        </div>
        <div class="panel ops-panel">
            <div class="ops-panel__icon ops-panel__icon--slate material-symbols-rounded" aria-hidden="true">today</div>
            <div>
                <p class="ops-panel__label">Aktivitas Hari Ini</p>
                <p class="ops-panel__value"><?= (int) ($operational['today'] ?? 0) ?></p>
                <p class="ops-panel__note">Dokumen dan invoice yang baru dibuat</p>
            </div>
        </div>
        <a class="panel ops-panel ops-panel--link" href="audit_log.php">
            <div class="ops-panel__icon ops-panel__icon--dark material-symbols-rounded" aria-hidden="true">history</div>
            <div>
                <p class="ops-panel__label">Jejak Audit</p>
                <p class="ops-panel__value ops-panel__value--link">Lihat aktivitas</p>
                <p class="ops-panel__note">Perubahan dokumen dan akun</p>
            </div>
        </a>
    </section>
    <?php endif; ?>

    <div class="dash-grid">
        <!-- FORM BUAT DOKUMEN -->
        <div>
            <div class="panel panel--edge panel--sticky">
                <div class="panel__hd">
                    <h2 class="panel__title">Buat Dokumen Baru</h2>
                </div>
                <div class="panel__bd">
                    <p class="faint" style="font-size:12.5px;margin-bottom:18px">Generate QR Code &amp; token terlebih dahulu. File PDF dapat diupload menyusul.</p>
                    <form method="POST" class="stack">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                        <div class="field">
                            <label class="field__label" for="no_surat">Nomor Surat</label>
                            <input class="input" id="no_surat" type="text" name="no_surat" required placeholder="Cth: 08/SER/DR/VIII/2026">
                        </div>
                        <div class="field">
                            <label class="field__label" for="d-perihal">Perihal</label>
                            <input class="input" id="d-perihal" type="text" name="perihal" required placeholder="Cth: SK Kelulusan">
                        </div>
                        <div class="field">
                            <label class="field__label" for="d-penerima">Penerima</label>
                            <input class="input" id="d-penerima" type="text" name="penerima" required placeholder="Nama Penerima">
                        </div>
                        <div class="field">
                            <label class="field__label" for="d-tanggal">Tanggal Dokumen</label>
                            <input class="input" id="d-tanggal" type="date" name="tanggal_dokumen" required>
                        </div>
                        <div class="field">
                            <label class="field__label" for="jabatan_ttd">Jabatan Penandatangan</label>
                            <input class="input" id="jabatan_ttd" type="text" name="jabatan_ttd" required value="<?= htmlspecialchars($staff_jabatan) ?>">
                        </div>
                        <button class="btn btn-primary btn-block" type="submit">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                            Generate QR Code
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- RIWAYAT DOKUMEN -->
        <div>
            <div class="panel">
                <div class="panel__hd">
                    <h2 class="panel__title">Riwayat Dokumen</h2>
                    <span class="badge badge-forest" id="document-total"><?= $totalDocs ?> dokumen</span>
                </div>
                <div class="panel__bd">
                    <div class="row" style="justify-content:space-between;gap:10px;margin-bottom:14px;align-items:center">
                        <div class="filter-tabs" role="tablist" aria-label="Filter status dokumen">
                            <a class="filter-tab<?= $statusF === '' ? ' active' : '' ?>" href="dashboard.php?<?= htmlspecialchars(http_build_query($q !== '' ? ['q' => $q] : [])) ?>" role="tab" aria-selected="<?= $statusF === '' ? 'true' : 'false' ?>">Semua</a>
                            <a class="filter-tab<?= $statusF === 'active' ? ' active' : '' ?>" href="dashboard.php?<?= htmlspecialchars($buildQs(['status' => 'active'])) ?>" role="tab" aria-selected="<?= $statusF === 'active' ? 'true' : 'false' ?>">Aktif</a>
                            <a class="filter-tab<?= $statusF === 'revoked' ? ' active' : '' ?>" href="dashboard.php?<?= htmlspecialchars($buildQs(['status' => 'revoked'])) ?>" role="tab" aria-selected="<?= $statusF === 'revoked' ? 'true' : 'false' ?>">Dicabut</a>
                        </div>
                        <form method="get" action="dashboard.php" class="row" style="gap:6px" role="search" id="document-search-form">
                            <input type="hidden" name="status" value="<?= htmlspecialchars($statusF) ?>">
                            <input type="search" class="input search-input" id="document-search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Cari nomor / perihal / penerima..." autocomplete="off">
                        </form>
                    </div>
                    <div id="document-results" aria-live="polite">
                        <?php if (count($docs) > 0): ?>
                        <div class="doclist">
                            <?php foreach ($docs as $doc): ?>
                                <?php include __DIR__ . '/_doc_item.php'; ?>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="textc" style="padding:48px 0">
                            <p class="faint"><?= ($q !== '' || $statusF !== '') ? 'Tidak ada dokumen yang cocok dengan pencarian/filter.' : 'Belum ada dokumen. Buat dokumen pertama Anda.' ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($totalPages > 1): ?>
                    <div class="pager" id="document-pager">
                        <?php if ($curPage > 1): ?>
                        <a class="btn btn-light btn-sm" href="dashboard.php?<?= htmlspecialchars($buildQs(['page' => $curPage - 1])) ?>">&larr; Sebelumnya</a>
                        <?php else: ?>
                        <span class="btn btn-light btn-sm is-off">&larr; Sebelumnya</span>
                        <?php endif; ?>
                        <span class="faint" style="font-size:12.5px"><?= $curPage ?> / <?= $totalPages ?></span>
                        <?php if ($curPage < $totalPages): ?>
                        <a class="btn btn-light btn-sm" href="dashboard.php?<?= htmlspecialchars($buildQs(['page' => $curPage + 1])) ?>">Berikutnya &rarr;</a>
                        <?php else: ?>
                        <span class="btn btn-light btn-sm is-off">Berikutnya &rarr;</span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if ($staff_role === 'direktur'): ?>
    <section class="panel" style="margin-top:24px" aria-labelledby="staff-summary-title">
        <div class="panel__hd">
            <div>
                <h2 class="panel__title" id="staff-summary-title">Ringkasan Produktivitas Staf</h2>
                <p class="faint" style="font-size:12.5px;margin-top:3px">Enam akun aktif dengan aktivitas dokumen terbanyak.</p>
            </div>
            <a class="btn btn-soft btn-sm" href="manage_staff.php">Kelola Staff</a>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Staff</th><th>Dokumen</th><th>Invoice</th><th>Aktivitas Terakhir</th></tr></thead>
                <tbody>
                <?php foreach ($staff_summary as $summary): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($summary['nama_lengkap']) ?></strong><br><span class="faint" style="font-size:12px"><?= htmlspecialchars($summary['jabatan']) ?></span></td>
                        <td><?= (int) $summary['document_count'] ?></td>
                        <td><?= (int) $summary['invoice_count'] ?></td>
                        <td><?= $summary['last_document_at'] ? htmlspecialchars(date('d M Y, H:i', strtotime($summary['last_document_at']))) : '<span class="faint">Belum ada</span>' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>
</main>

<?php include __DIR__ . '/_upload_modal.php'; ?>

<script>
<?php if (isset($last_token) && $success_msg): ?>
const qrCodePreview = new QRCodeStyling({
    width: 220, height: 220, type: "svg",
    data: <?= json_encode($verify_url) ?>,
    image: <?= json_encode(APP_URL . '/assets/1.png') ?>,
    margin: 8,
    qrOptions: { errorCorrectionLevel: "H" },
    dotsOptions: { color: "#000000", type: "rounded" },
    backgroundOptions: { color: "#ffffff" },
    imageOptions: { crossOrigin: "anonymous", margin: 4, imageSize: 0.24 },
    cornersSquareOptions: { color: "#000000", type: "extra-rounded" },
    cornersDotOptions: { color: "#000000", type: "dot" }
});
qrCodePreview.append(document.getElementById("qr-code-container"));

function downloadQR() {
    const highResQR = new QRCodeStyling({
        width: 1000, height: 1000, type: "canvas",
        data: <?= json_encode($verify_url) ?>,
        image: <?= json_encode(APP_URL . '/assets/1.png') ?>,
        margin: 32,
        qrOptions: { errorCorrectionLevel: "H" },
        dotsOptions: { color: "#000000", type: "rounded" },
        backgroundOptions: { color: "#ffffff" },
        imageOptions: { crossOrigin: "anonymous", margin: 12, imageSize: 0.24 },
        cornersSquareOptions: { color: "#000000", type: "extra-rounded" },
        cornersDotOptions: { color: "#000000", type: "dot" }
    });
    highResQR.download({ name: <?= json_encode('QR-ALMUTQON-' . $last_code . '-HD') ?>, extension: "png" });
}
<?php endif; ?>
</script>
<script>
(() => {
    const form = document.getElementById('document-search-form');
    const input = document.getElementById('document-search');
    const results = document.getElementById('document-results');
    const counter = document.getElementById('document-total');
    const pager = document.getElementById('document-pager');
    const filterTabs = document.querySelectorAll('.filter-tab');
    if (!form || !input || !results || !counter) return;

    let timer;
    let controller;
    let requestNumber = 0;
    let activeStatus = form.querySelector('[name="status"]').value;
    const search = async (query, status = activeStatus) => {
        if (controller) controller.abort();
        controller = new AbortController();
        const thisRequest = ++requestNumber;
        const params = new URLSearchParams({ q: query });
        if (status) params.set('status', status);
        input.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch('api/search_documents.php?' + params.toString(), {
                credentials: 'same-origin',
                signal: controller.signal,
                headers: { Accept: 'application/json' },
            });
            if (!response.ok) throw new Error('Pencarian gagal dimuat.');
            const data = await response.json();
            if (thisRequest !== requestNumber) return;
            results.innerHTML = data.html;
            counter.textContent = data.total + ' dokumen';
            if (pager) pager.hidden = true;
            activeStatus = status;
            form.querySelector('[name="status"]').value = status;
            filterTabs.forEach((tab) => {
                const tabStatus = new URL(tab.href).searchParams.get('status') || '';
                const isActive = tabStatus === status;
                tab.classList.toggle('active', isActive);
                tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
            });
            const url = new URL(window.location.href);
            if (query) url.searchParams.set('q', query); else url.searchParams.delete('q');
            if (status) url.searchParams.set('status', status); else url.searchParams.delete('status');
            url.searchParams.delete('page');
            window.history.replaceState({}, '', url);
        } catch (error) {
            if (error.name !== 'AbortError') {
                results.innerHTML = '<div class="textc" style="padding:48px 0"><p class="err-text">Pencarian tidak dapat dimuat. Silakan coba lagi.</p></div>';
            }
        } finally {
            if (thisRequest === requestNumber) input.removeAttribute('aria-busy');
        }
    };

    input.addEventListener('input', () => {
        window.clearTimeout(timer);
        timer = window.setTimeout(() => {
            search(input.value.trim(), activeStatus);
        }, 180);
    });
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        window.clearTimeout(timer);
        search(input.value.trim(), activeStatus);
    });
    filterTabs.forEach((tab) => {
        tab.addEventListener('click', (event) => {
            event.preventDefault();
            const status = new URL(tab.href).searchParams.get('status') || '';
            search(input.value.trim(), status);
        });
    });
})();
</script>
<script src="assets/nav.js?v=<?= ASSET_VERSION ?>"></script>
</body>
</html>
