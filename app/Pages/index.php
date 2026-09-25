<?php
require dirname(__DIR__) . '/Bootstrap.php';

$token = $_GET['token'] ?? '';
$code  = $_GET['code']  ?? '';
$dokumen = null;
$is_valid = false;
$error_msg = '';
$staff_login_error = $_SESSION['staff_login_error'] ?? '';
unset($_SESSION['staff_login_error']);

if (!empty($token)) {
    $stmt = $pdo->prepare("SELECT * FROM dokumen WHERE token = ?");
    $stmt->execute([$token]);
    $dokumen = $stmt->fetch();
    if (!$dokumen) {
        $error_msg = 'Token tidak ditemukan dalam sistem.';
    } elseif ($dokumen['status'] === 'active') {
        $is_valid = true;
    } else {
        $error_msg = 'Dokumen ini telah dicabut dan tidak lagi valid.';
    }
} elseif (!empty($code)) {
    $stmt = $pdo->prepare("SELECT * FROM dokumen WHERE code_6digit = ?");
    $stmt->execute([strtoupper(trim($code))]);
    $dokumen = $stmt->fetch();
    if (!$dokumen) {
        $error_msg = 'Kode 6-digit tidak valid.';
    } elseif ($dokumen['status'] === 'active') {
        $is_valid = true;
    } else {
        $error_msg = 'Dokumen ini telah dicabut dan tidak lagi valid.';
    }
}

// Smart back button: Cek apakah user adalah staff yang sedang login
$is_staff_logged_in = isset($_SESSION['is_staff']) && $_SESSION['is_staff'] === true;
$back_url = $is_staff_logged_in ? 'dashboard.php' : 'index.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AL MUTQON - Validasi Dokumen Digital</title>
<link rel="icon" type="image/png" href="assets/favicon.png?v=20260919b">
    <link rel="shortcut icon" href="assets/favicon.ico?v=20260919b">
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="assets/app.css?v=20260919l" rel="stylesheet">
<link href="assets/portal.css?v=20260919d" rel="stylesheet">
</head>
<body>

<?php if (!$is_valid && empty($error_msg)): ?>
<!-- STATE A: BERANDA (PORTAL PUBLIK) -->
<main class="beranda">
    <aside class="beranda__side">
        <a class="beranda__brand" href="index.php">
            <img src="assets/1.png" alt="" class="beranda__logo">
            <span class="beranda__title">AL MUTQON ARABIC LEARNING<span class="beranda__sub">Sistem Validasi Dokumen Digital</span></span>
        </a>
        <footer class="beranda__foot">Al-Mutqon Validation System - Secure HMAC-SHA256</footer>
    </aside>

    <section class="beranda__main">
    <div class="landing">
        <div class="landing__nav">
            <button type="button" id="btn-check" class="landing__tab tab-active" onclick="switchTab('check')">Cek Dokumen</button>
            <button type="button" id="btn-scan" class="landing__tab" onclick="switchTab('scan')">Scan QR</button>
            <button type="button" id="btn-staff" class="landing__tab" onclick="switchTab('staff')">Portal Staff</button>
        </div>

        <!-- CEK DOKUMEN -->
        <section id="section-check" class="landing__panel">
            <div class="landing__icon">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            </div>
            <h2>Validasi Dokumen</h2>
            <p>Periksa keaslian dokumen dengan memasukkan kode 6 digit yang tertera pada dokumen.</p>
            <form method="GET" action="index.php" class="stack">
                <input type="text" name="code" placeholder="X X X X X X" maxlength="6"
                       class="input code-input" required
                       oninput="this.value = this.value.replace(/[^a-zA-Z0-9]/g, '').toUpperCase()">
                <button type="submit" class="btn btn-primary btn-block">Verifikasi Dokumen</button>
            </form>
        </section>

        <!-- SCAN QR -->
        <section id="section-scan" class="landing__panel hidden">
            <h2>Scan QR Code</h2>
            <p>Arahkan kamera ke QR Code pada dokumen AL MUTQON.</p>
            <div class="scan-frame">
                <div class="scan-inner">
                    <div id="reader"></div>
                    <div id="scan-empty" class="scan-empty">
                        <span class="scan-empty__icon">
                            <svg width="46" height="46" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M7 2h10a2 2 0 012 2v15a2 2 0 01-2 2H7a2 2 0 01-2-2V4a2 2 0 012-2z"/><path d="M9 6h6v9.5H9z"/><path d="M9 20h6"/></svg>
                        </span>
                        <p class="scan-empty__title">Request Camera Permissions</p>
                        <label class="scan-empty__file" for="scan-file">Scan an Image File</label>
                    </div>
                    <div class="scan-line"></div>
                </div>
            </div>
            <p id="scan-status" class="faint" style="font-size:13px;margin:14px 0">Kamera akan aktif otomatis saat tab Scan dibuka.</p>
            <input type="file" id="scan-file" accept="image/*" capture="environment" class="hidden">
        </section>

        <!-- PORTAL STAFF -->
        <section id="section-staff" class="landing__panel landing__panel--staff hidden">
            <div class="landing__icon">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            </div>
            <h2>Selamat Datang.</h2>
            <p>Login ke portal manajemen dan pengesahan dokumen.</p>
            <?php if ($staff_login_error !== ''): ?>
                <div class="alert alert-err" role="alert"><?= htmlspecialchars($staff_login_error) ?></div>
            <?php endif; ?>
            <form method="POST" action="staff.php" class="stack">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <div class="field">
                    <label class="field__label" for="s-nip">ID Pengguna</label>
                    <input class="input" id="s-nip" type="text" name="nip" placeholder="Masukkan ID Anda" required>
                </div>
                <div class="field">
                    <label class="field__label" for="s-pass">Kata Sandi</label>
                    <input class="input" id="s-pass" type="password" name="password" placeholder="Masukkan kata sandi Anda" required>
                    <?php if ($staff_login_error !== ''): ?>
                        <p class="landing__recovery">Lupa kata sandi? <a href="lupa_password.php">Pulihkan akses</a></p>
                    <?php endif; ?>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Masuk ke Sistem</button>
            </form>
        </section>
    </div>
    </section>
</main>

<?php elseif ($is_valid): ?>
<!-- STATE B: HASIL VERIFIKASI -->
<div class="verify-wrap">
    <div class="verify-sheet">
        <div class="verify-head">
            <img src="assets/1.png" alt="Logo Al Mutqon" class="verify-head__logo">
            <h1 class="verify-head__title">Sertifikat Validasi Digital</h1>
            <span class="verify-head__org">AL MUTQON ARABIC LEARNING</span>
        </div>
        <div class="verify-body">
            <div class="verify-kicker">
                <div class="seal">
                    <svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
                </div>
                <h2>Dokumen Sah &amp; Terverifikasi</h2>
                <p>Keaslian dokumen ini dijamin melalui token kriptografi HMAC-SHA256 anti-pemalsuan yang divalidasi secara real-time terhadap catatan database terpusat AL MUTQON.</p>
            </div>

            <div class="verify-actions">
                <?php if (!empty($dokumen['pdf_path'])): ?>
                    <?php $dlToken = hash_hmac('sha256', 'download:' . (int) $dokumen['id'], APP_SECRET_KEY); ?>
                    <a href="download.php?id=<?= (int) $dokumen['id'] ?>&t=<?= $dlToken ?>" target="_blank" class="btn btn-primary">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Lihat / Unduh Surat Fisik Asli
                    </a>
                <?php else: ?>
                    <button class="btn" disabled>PDF Belum Diupload</button>
                <?php endif; ?>
            </div>

            <div class="doc-grid">
                <div class="doc-cell">
                    <p class="doc-cell__label">Kode Verifikasi</p>
                    <p class="doc-cell__value mono" style="letter-spacing:.14em"><?= htmlspecialchars($dokumen['code_6digit']) ?></p>
                </div>
                <div class="doc-cell">
                    <p class="doc-cell__label">Waktu Pengesahan</p>
                    <p class="doc-cell__value"><?= date('d M Y, H:i', strtotime($dokumen['waktu_pengesahan'])) ?> WIB</p>
                </div>
                <div class="doc-cell">
                    <p class="doc-cell__label">Tanggal Dokumen</p>
                    <p class="doc-cell__value"><?= date('d F Y', strtotime($dokumen['tanggal_dokumen'])) ?></p>
                </div>
                <div class="doc-cell">
                    <p class="doc-cell__label">Nomor Surat</p>
                    <p class="doc-cell__value"><?= htmlspecialchars($dokumen['no_surat']) ?></p>
                </div>
                <div class="doc-cell">
                    <p class="doc-cell__label">Perihal</p>
                    <p class="doc-cell__value"><?= htmlspecialchars($dokumen['perihal']) ?></p>
                </div>
                <div class="doc-cell">
                    <p class="doc-cell__label">Penerima</p>
                    <p class="doc-cell__value"><?= htmlspecialchars($dokumen['penerima']) ?></p>
                </div>
            </div>

            <div class="sig-block">
                <div class="sig-block__av"><?= strtoupper(substr($dokumen['penandatangan'], 0, 1)) ?></div>
                <div>
                    <p class="sig-block__label">Disahkan Secara Digital Oleh</p>
                    <p class="sig-block__name"><?= htmlspecialchars($dokumen['penandatangan']) ?></p>
                    <p class="sig-block__role"><span class="sig-block__dot"></span><?= htmlspecialchars($dokumen['jabatan']) ?></p>
                </div>
            </div>

            <div class="legal">
                <h4>Pernyataan Hukum</h4>
                <p>Dokumen ini telah ditandatangani secara elektronik yang diterbitkan oleh AL MUTQON Arabic Learning sesuai dengan UU ITE No. 11 Tahun 2008. Informasi dokumen dapat dilihat menggunakan aplikasi pembaca PDF, dan pembuktian keasliannya dapat dilakukan melalui sistem validasi digital resmi AL MUTQON Arabic Learning.</p>
            </div>
        </div>
        <div class="verify-back">
            <a class="verify-back__link" href="<?= $back_url ?>">&larr; <?= $is_staff_logged_in ? 'Kembali ke Dashboard' : 'Kembali ke Beranda' ?></a>
        </div>
    </div>
</div>

<?php else: ?>
<!-- STATE C: ERROR -->
<div class="verify-wrap" style="align-items:center">
    <div class="verify-sheet" style="max-width:460px">
        <div class="verify-body textc" style="padding:40px 28px">
            <div class="seal" style="border-color:#ECC4C0;background:var(--err-soft);color:var(--err)">
                <svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M6 18L18 6M6 6l12 12"/></svg>
            </div>
            <h2 style="font-size:21px">Dokumen Tidak Tervalidasi</h2>
            <p class="muted" style="font-size:13.5px;margin:10px 0 22px"><?= htmlspecialchars($error_msg) ?></p>
            <a href="index.php" class="btn btn-primary">Coba Lagi</a>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (!$is_valid && empty($error_msg)): ?>
<script>
let html5QrcodeScanner = null;
let html5QrCode = null;

function switchTab(tabName) {
    ['check', 'scan', 'staff'].forEach(t => {
        document.getElementById('section-' + t).classList.toggle('hidden', t !== tabName);
    });
    ['btn-check', 'btn-scan', 'btn-staff'].forEach(id => {
        document.getElementById(id).classList.toggle('tab-active', id === 'btn-' + tabName);
    });
    if (tabName === 'scan') startScanner();
    else stopScanner();
}

function startScanner() {
    if (html5QrcodeScanner) return;
    const statusEl = document.getElementById('scan-status');
    const frame = document.querySelector('.scan-frame');
    const empty = document.getElementById('scan-empty');
    if (!window.isSecureContext) {
        if (statusEl) statusEl.innerHTML = '<span class="warn-text">Kamera butuh HTTPS. Buka halaman ini via https://' + location.host + '</span>';
        return;
    }
    html5QrcodeScanner = new Html5QrcodeScanner("reader", { fps: 10, qrbox: { width: 250, height: 250 } }, false);
    html5QrcodeScanner.render(onScanSuccess, () => {})
        .then(() => {
            if (empty) empty.classList.add('hidden');
            if (frame) frame.classList.add('scanning');
            if (statusEl) statusEl.textContent = 'Kamera aktif. Arahkan ke QR code...';
        })
        .catch(err => {
            if (statusEl) statusEl.innerHTML = '<span class="warn-text">Tidak dapat mengakses kamera. Izinkan akses kamera di browser.</span>';
            console.error(err);
        });
}

function stopScanner() {
    const frame = document.querySelector('.scan-frame');
    const empty = document.getElementById('scan-empty');
    if (html5QrcodeScanner) {
        html5QrcodeScanner.clear().then(() => {
            html5QrcodeScanner = null;
            const reader = document.getElementById('reader');
            if (reader) reader.innerHTML = '';
            if (frame) frame.classList.remove('scanning');
            if (empty) empty.classList.remove('hidden');
        }).catch(err => console.error(err));
    }
}

function scanFromFile(file) {
    if (!file) return;
    if (!html5QrCode) html5QrCode = new Html5Qrcode("reader");
    const statusEl = document.getElementById('scan-status');
    if (statusEl) statusEl.textContent = 'Membaca gambar...';
    html5QrCode.scanFile(file, true)
    .then(decodedText => onScanSuccess(decodedText))
    .catch(err => { if (statusEl) statusEl.innerHTML = '<span class="err-text">QR tidak terbaca pada gambar. Gunakan gambar yang lebih jelas.</span>'; console.error(err); });
}

function onScanSuccess(decodedText) {
    stopScanner();
    const statusEl = document.getElementById('scan-status');
    let token = '';
    try { token = new URLSearchParams(new URL(decodedText).search).get('token') || ''; }
    catch (e) { if (decodedText.trim().length === 6) token = decodedText.trim(); }
    if (token) {
        if (statusEl) statusEl.textContent = 'QR Terdeteksi! Mengalihkan...';
        window.location.href = token.length === 6
            ? 'index.php?code=' + encodeURIComponent(token)
            : 'index.php?token=' + encodeURIComponent(token);
    } else {
        if (statusEl) statusEl.innerText = '';
        Swal.fire({ icon: 'error', title: 'QR Tidak Dikenali', text: 'Bukan QR Code dokumen AL MUTQON yang valid.', confirmButtonColor: '#166534' });
    }
}

// Deep-link: index.php#scan / #check / #staff membuka tab yang sesuai
(function() {
    const tab = (location.hash || '').replace('#', '');
    if (['check', 'scan', 'staff'].includes(tab)) switchTab(tab);

    const fileInput = document.getElementById('scan-file');
    fileInput.addEventListener('change', function() {
        if (this.files && this.files[0]) scanFromFile(this.files[0]);
        this.value = '';
    });
})();
</script>
<?php endif; ?>
</body>
</html>
