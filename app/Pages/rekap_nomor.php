<?php
// app/Pages/rekap_nomor.php — rekap penomoran dokumen (surat/sertifikat bernomor).
// Cakupan: HANYA jenis 'dokumen' bernomor_urut; invoice/struk TIDAK masuk rekap.
// Akses: staff login (semua role, read-only).
require dirname(__DIR__) . '/Bootstrap.php';

if (!isset($_SESSION['is_staff'])) {
    header('Location: staff.php');
    exit;
}

$staff_id = (int) ($_SESSION['staff_id'] ?? 0);
$akun = akun_ringkas($pdo, $staff_id);
$stmt = $pdo->prepare('SELECT nama_lengkap, jabatan FROM staff WHERE id = ?');
$stmt->execute([$staff_id]);
$me = $stmt->fetch();
$staff_nama = $me['nama_lengkap'] ?? ($_SESSION['staff_nama'] ?? '');
$staff_jabatan = $me['jabatan'] ?? ($_SESSION['staff_jabatan'] ?? '');

$rows = $pdo->query(
    "SELECT nomor_urut, no_surat, perihal, tanggal_dokumen, status, code_6digit
     FROM dokumen WHERE jenis = 'dokumen' AND nomor_urut IS NOT NULL
     ORDER BY nomor_urut ASC"
)->fetchAll();

$byNomor = [];
$max = 0;
foreach ($rows as $r) {
    $n = (int) $r['nomor_urut'];
    $byNomor[$n] = $r;
    if ($n > $max) {
        $max = $n;
    }
}
$gap = [];
for ($i = 1; $i <= $max; $i++) {
    if (!isset($byNomor[$i])) {
        $gap[] = $i;
    }
}

// Navigasi
$pageTitleFull = 'Rekap Nomor - AL MUTQON';
$navActive = 'rekap_nomor';
?>
<?php include __DIR__ . '/_head.php'; ?>

<?php include __DIR__ . '/_sidebar.php'; ?>
<?php include __DIR__ . '/_masthead.php'; ?>

<main class="page page--wide">
    <div class="grid3" style="margin-bottom:22px">
        <div class="stat">
            <span class="stat__body">
                <span class="stat__value">#<?= str_pad((string) $max, 3, '0', STR_PAD_LEFT) ?></span>
                <span class="stat__label">Nomor Terakhir</span>
            </span>
        </div>
        <div class="stat">
            <span class="stat__body">
                <span class="stat__value"><?= count($byNomor) ?></span>
                <span class="stat__label">Nomor Terpakai</span>
            </span>
        </div>
        <div class="stat">
            <span class="stat__body">
                <span class="stat__value"><?= count($gap) ?></span>
                <span class="stat__label">Lompat / Belum Terpakai</span>
            </span>
        </div>
    </div>

    <div class="panel">
        <div class="panel__hd">
            <h2 class="panel__title">Urutan Nomor Dokumen</h2>
            <span class="badge badge-forest"><?= count($byNomor) ?> nomor</span>
        </div>
        <div class="panel__bd">
            <?php if ($max > 0): ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:90px">Nomor</th>
                            <th>No. Surat</th>
                            <th>Perihal</th>
                            <th>Tanggal</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for ($i = 1; $i <= $max; $i++): ?>
                            <?php if (isset($byNomor[$i])): $r = $byNomor[$i]; ?>
                            <tr>
                                <td><span class="badge badge-forest mono">#<?= str_pad((string) $i, 3, '0', STR_PAD_LEFT) ?></span></td>
                                <td><strong><?= htmlspecialchars($r['no_surat']) ?></strong><br><span class="faint mono" style="font-size:12px"><?= htmlspecialchars($r['code_6digit']) ?></span></td>
                                <td><?= htmlspecialchars($r['perihal']) ?></td>
                                <td><?= date('d M Y', strtotime($r['tanggal_dokumen'])) ?></td>
                                <td>
                                    <?php if (($r['status'] ?? 'active') === 'active'): ?>
                                        <span class="badge badge-ok">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge badge-err">Dicabut</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php else: ?>
                            <tr>
                                <td><span class="badge badge-muted mono">#<?= str_pad((string) $i, 3, '0', STR_PAD_LEFT) ?></span></td>
                                <td colspan="4"><span class="faint">Belum terpakai</span></td>
                            </tr>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
            <p class="faint" style="font-size:12.5px;margin:14px 0 0">Hanya dokumen bernomor (surat/sertifikat). Invoice/struk tidak memakai nomor urut dan tidak tampil di sini.</p>
            <?php else: ?>
            <div class="textc" style="padding:48px 0">
                <p class="faint">Belum ada nomor terpakai.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script src="assets/nav.js?v=<?= ASSET_VERSION ?>"></script>
</body>
</html>
