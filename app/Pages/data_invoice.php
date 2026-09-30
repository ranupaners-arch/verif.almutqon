<?php
// app/Pages/data_invoice.php — daftar invoice/struk pembayaran (jenis = 'invoice').
// Dokumen surat/sertifikat tetap di dashboard.php. Aksi mutasi memakai bersama
// app/Http/dokumen_actions.php. Akses: staff login (semua role).
require dirname(__DIR__) . '/Bootstrap.php';
require_once dirname(__DIR__) . '/Http/dokumen_actions.php';

if (!isset($_SESSION['is_staff'])) {
    header('Location: staff.php');
    exit;
}

$staff_id = (int) ($_SESSION['staff_id'] ?? 0);
$akun = akun_ringkas($pdo, $staff_id);

$stmt = $pdo->prepare('SELECT role, nama_lengkap, jabatan FROM staff WHERE id = ?');
$stmt->execute([$staff_id]);
$staff_row = $stmt->fetch();
$staff_role = $staff_row['role'] ?? 'staff';
$staff_nama = $staff_row['nama_lengkap'] ?? ($_SESSION['staff_nama'] ?? '');
$staff_jabatan = $staff_row['jabatan'] ?? ($_SESSION['staff_jabatan'] ?? '');

$is_admin_or_direktur = in_array($staff_role, ['direktur', 'admin']);

$success_msg = '';
$error_msg = '';

// Guard CSRF global untuk semua POST mutasi.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf()) {
    $error_msg = 'Permintaan tidak valid. Silakan muat ulang halaman dan coba lagi.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_document') {
    dokumen_aksi_hapus($pdo, $staff_id, $is_admin_or_direktur, 'data_invoice.php');
}

if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'deleted') {
        $success_msg = 'Dokumen berhasil dihapus!';
    }
}
$redirect_err = dokumen_ambil_pesan();
if ($redirect_err !== '' && $error_msg === '') {
    $error_msg = $redirect_err;
}

// FETCH: hanya invoice, terbaru dulu (tanpa nomor urut).
$stmt = $pdo->prepare("SELECT d.*, COALESCE(s.nama_lengkap, sa.display_name) AS staff_name FROM dokumen d LEFT JOIN staff s ON d.staff_id = s.id LEFT JOIN system_actors sa ON d.system_actor_id = sa.id WHERE d.jenis = 'invoice' ORDER BY d.created_at DESC");
$stmt->execute();
$docs = $stmt->fetchAll();

$stat = $pdo->query("SELECT COUNT(*) AS total FROM dokumen WHERE jenis = 'invoice'")->fetch();

// Navigasi
$pageTitle = 'Data Invoice';
$pageTitleFull = null;
$navActive = 'data_invoice';
?>
<?php include __DIR__ . '/_head.php'; ?>

<?php include __DIR__ . '/_sidebar.php'; ?>
<?php include __DIR__ . '/_masthead.php'; ?>

<main class="page page--wide">
    <!-- STATS -->
    <div class="stat" style="margin-bottom:22px">
        <span class="stat__body">
            <span class="stat__value"><?= (int) ($stat['total'] ?? 0) ?></span>
            <span class="stat__label">Total Invoice</span>
        </span>
    </div>

    <!-- NOTIFIKASI -->
    <?php if ($success_msg): ?>
    <div class="alert alert-ok">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0"><path d="M5 13l4 4L19 7"/></svg>
        <span><?= htmlspecialchars($success_msg) ?></span>
    </div>
    <?php endif; ?>

    <?php if ($error_msg): ?>
    <div class="alert alert-err">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0"><path d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span><?= htmlspecialchars($error_msg) ?></span>
    </div>
    <?php endif; ?>

    <!-- RIWAYAT INVOICE -->
    <div class="panel">
        <div class="panel__hd">
            <h2 class="panel__title">Riwayat Invoice / Struk</h2>
            <span class="badge badge-gold"><?= count($docs) ?> invoice</span>
        </div>
        <div class="panel__bd">
            <?php if (count($docs) > 0): ?>
            <div class="doclist">
                <?php $noUploadPdf = true; $noStatusAction = true; ?>
                <?php foreach ($docs as $doc): ?>
                    <?php include __DIR__ . '/_doc_item.php'; ?>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="textc" style="padding:48px 0">
                <p class="faint">Belum ada invoice. Invoice tercatat otomatis saat pembayaran dikonfirmasi LUNAS.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script src="assets/nav.js?v=<?= ASSET_VERSION ?>"></script>
</body>
</html>
