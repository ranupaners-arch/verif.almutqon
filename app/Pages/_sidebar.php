<?php
// app/Pages/_sidebar.php — off-canvas left drawer: navigasi utama halaman staff.
// Variabel tersedia sebelum include: $pdo, $staff_id, $akun (akun_ringkas()).
// Opsional: $isAdminOrDirektur (bool), $navActive ('dashboard'|'data_invoice'|'rekap_nomor'|'manage_staff'|'profil'|'change_password').
$navActive = $navActive ?? 'dashboard';
$__sidebar = akun_ringkas($pdo, (int) $staff_id);
$__isAdmin = in_array((string) ($__sidebar['role'] ?? 'staff'), ['admin', 'direktur'], true);
$__nav = [
    'dashboard'    => ['label' => 'Dashboard',    'href' => 'dashboard.php',     'icon' => 'home'],
    'data_invoice' => ['label' => 'Data Invoice', 'href' => 'data_invoice.php',  'icon' => 'receipt_long'],
    'rekap_nomor'  => ['label' => 'Rekap Nomor',  'href' => 'rekap_nomor.php',   'icon' => 'format_list_numbered'],
];
if ($__isAdmin) {
    $__nav['manage_staff'] = ['label' => 'Manajemen Staff', 'href' => 'manage_staff.php', 'icon' => 'group'];
}
$__nav['profil']          = ['label' => 'Profil',         'href' => 'profil.php',          'icon' => 'account_circle'];
$__nav['change_password'] = ['label' => 'Ganti Password', 'href' => 'change_password.php', 'icon' => 'key'];
$__flash = $_SESSION['akun_flash'] ?? null;
unset($_SESSION['akun_flash']);
?>
<div class="sidebar-backdrop" id="sidebarBackdrop" aria-hidden="true"></div>
<aside class="sidebar" id="appSidebar" aria-label="Menu navigasi utama" aria-hidden="true">
    <div class="sidebar__brand">
        <img src="assets/1.png" alt="Logo AL MUTQON" class="sidebar__brand-img">
        <span class="sidebar__brand-text">AL MUTQON<span class="sidebar__brand-sub">Verification System</span></span>
    </div>

    <nav class="sidebar__nav">
        <?php foreach ($__nav as $__k => $__item): ?>
        <a class="sidebar__nav-link<?= $navActive === $__k ? ' active' : '' ?>" href="<?= htmlspecialchars($__item['href']) ?>" aria-current="<?= $navActive === $__k ? 'page' : 'false' ?>">
            <span class="material-symbols-rounded" aria-hidden="true"><?= htmlspecialchars($__item['icon']) ?></span>
            <?= htmlspecialchars($__item['label']) ?>
        </a>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar__foot">
        <a class="sidebar__user" href="profil.php">
            <?php if (($__sidebar['avatar'] ?? '') !== ''): ?>
                <img class="sidebar__user-av" src="<?= htmlspecialchars($__sidebar['avatar']) ?>" alt="">
            <?php else: ?>
                <span class="sidebar__user-av"><?= htmlspecialchars(strtoupper(substr((string) $__sidebar['nama'], 0, 1))) ?></span>
            <?php endif; ?>
            <span style="min-width:0">
                <span class="sidebar__user-name"><?= htmlspecialchars($__sidebar['nama']) ?></span>
                <span class="sidebar__user-role"><?= htmlspecialchars($__sidebar['jabatan']) ?></span>
            </span>
        </a>
        <div class="sidebar__themes">
            <span style="font-size:12.5px;font-weight:600;color:#CBD5E1">Tema</span>
            <?php
            $themeStored   = (string) ($__sidebar['theme'] ?? 'light');
            $themeVariant  = 'icons';
            $themeEndpoint = 'api/theme.php';
            $themeCsrf     = csrf_token();
            include __DIR__ . '/_theme_switch.php';
            ?>
        </div>
        <a class="sidebar__logout" href="staff.php?logout=1">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            Keluar
        </a>
    </div>
</aside>

<?php if ($__flash): ?>
<div class="akun-toast<?= $__flash['ok'] ? '' : ' akun-toast--err' ?>" id="akun-toast" role="status"><?= htmlspecialchars($__flash['msg']) ?></div>
<?php endif; ?>