<?php
require dirname(__DIR__) . '/Bootstrap.php';

if (!isset($_SESSION['is_staff'])) { header('Location: staff.php'); exit; }

$staff_id = (int) ($_SESSION['staff_id'] ?? 0);
$akun = akun_ringkas($pdo, $staff_id);
$role = (string) ($akun['role'] ?? 'staff');
if (!in_array($role, ['admin', 'direktur'], true)) {
    http_response_code(403);
    exit('Akses halaman ini hanya untuk Admin dan Direktur.');
}

$pageTitle = 'Jejak Audit';
$pageTitleFull = null;
$navActive = 'audit_log';
$q = trim((string) ($_GET['q'] ?? ''));
$action = trim((string) ($_GET['action'] ?? ''));
$per_page = 30;
$page = max(1, (int) ($_GET['page'] ?? 1));
$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(l.description LIKE ? OR s.nama_lengkap LIKE ? OR sa.display_name LIKE ?)';
    $like = '%' . $q . '%';
    $params = [$like, $like, $like];
}
if ($action !== '') {
    $where[] = 'l.action = ?';
    $params[] = $action;
}
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$stmt = $pdo->prepare("SELECT COUNT(*) FROM activity_log l LEFT JOIN staff s ON s.id = l.staff_id LEFT JOIN system_actors sa ON sa.id = l.system_actor_id $where_sql");
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();
$pages = max(1, (int) ceil($total / $per_page));
$page = min($page, $pages);
$offset = ($page - 1) * $per_page;
$stmt = $pdo->prepare("SELECT l.action, l.description, l.ip_address, l.created_at,
        COALESCE(s.nama_lengkap, sa.display_name) AS nama_lengkap
    FROM activity_log l LEFT JOIN staff s ON s.id = l.staff_id LEFT JOIN system_actors sa ON sa.id = l.system_actor_id
    $where_sql ORDER BY l.created_at DESC LIMIT $per_page OFFSET $offset");
$stmt->execute($params);
$logs = $stmt->fetchAll();
$actions = $pdo->query('SELECT DISTINCT action FROM activity_log ORDER BY action ASC')->fetchAll(PDO::FETCH_COLUMN);
$query = static function (array $extra = []) use ($q, $action): string {
    $values = $extra;
    if ($q !== '' && !array_key_exists('q', $values)) { $values['q'] = $q; }
    if ($action !== '' && !array_key_exists('action', $values)) { $values['action'] = $action; }
    return http_build_query($values);
};
?>
<?php include __DIR__ . '/_head.php'; ?>
<?php include __DIR__ . '/_sidebar.php'; ?>
<?php include __DIR__ . '/_masthead.php'; ?>
<main class="page page--wide audit-page">
    <section class="panel" aria-labelledby="audit-title">
        <div class="panel__hd">
            <div><h2 class="panel__title" id="audit-title">Riwayat Perubahan Sistem</h2><p class="faint" style="font-size:12.5px;margin-top:3px">Catatan pembuatan, perubahan akses, dan status dokumen.</p></div>
            <span class="badge badge-forest" id="audit-total"><?= $total ?> aktivitas</span>
        </div>
        <div class="panel__bd">
            <form method="get" action="audit_log.php" class="audit-filter" role="search" id="audit-search-form">
                <input class="input search-input" id="audit-search" type="search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Cari pelaku atau keterangan..." autocomplete="off" aria-label="Cari jejak audit">
                <label class="audit-filter__field"><span class="field__label">Jenis aktivitas</span><select class="select" id="audit-action" name="action"><option value="">Semua aktivitas</option><?php foreach ($actions as $item): ?><option value="<?= htmlspecialchars($item) ?>" <?= $action === $item ? 'selected' : '' ?>><?= htmlspecialchars(str_replace('_', ' ', ucfirst($item))) ?></option><?php endforeach; ?></select></label>
                <div class="audit-filter__buttons"><?php if ($q !== '' || $action !== ''): ?><a class="btn btn-light btn-sm" href="audit_log.php">Reset</a><?php endif; ?></div>
            </form>
        </div>
        <div class="table-wrap">
            <table class="table audit-table"><thead><tr><th>Waktu</th><th>Pelaku</th><th>Aktivitas</th><th>Keterangan</th><th>IP</th></tr></thead><tbody id="audit-results">
            <?php if ($logs): foreach ($logs as $log): ?><tr><td><?= htmlspecialchars(date('d M Y, H:i', strtotime($log['created_at']))) ?></td><td><?= htmlspecialchars($log['nama_lengkap'] ?: 'Sistem') ?></td><td><span class="badge badge-muted"><?= htmlspecialchars(str_replace('_', ' ', $log['action'])) ?></span></td><td><?= htmlspecialchars($log['description']) ?></td><td class="mono faint"><?= htmlspecialchars($log['ip_address']) ?></td></tr><?php endforeach; else: ?><tr><td colspan="5" class="textc faint">Tidak ada aktivitas yang cocok.</td></tr><?php endif; ?>
            </tbody></table>
        </div>
        <?php if ($pages > 1): ?><div class="pager audit-pager" id="audit-pager"><?php if ($page > 1): ?><a class="btn btn-light btn-sm" href="audit_log.php?<?= htmlspecialchars($query(['page' => $page - 1])) ?>">&larr; Sebelumnya</a><?php endif; ?><span class="faint" style="font-size:12.5px">Halaman <?= $page ?> dari <?= $pages ?></span><?php if ($page < $pages): ?><a class="btn btn-light btn-sm" href="audit_log.php?<?= htmlspecialchars($query(['page' => $page + 1])) ?>">Berikutnya &rarr;</a><?php endif; ?></div><?php endif; ?>
    </section>
</main>
<script>
(() => {
    const form = document.getElementById('audit-search-form');
    const input = document.getElementById('audit-search');
    const action = document.getElementById('audit-action');
    const results = document.getElementById('audit-results');
    const counter = document.getElementById('audit-total');
    const pager = document.getElementById('audit-pager');
    if (!form || !input || !action || !results || !counter) return;
    let timer;
    let controller;
    let requestNumber = 0;
    const search = async () => {
        if (controller) controller.abort();
        controller = new AbortController();
        const current = ++requestNumber;
        const params = new URLSearchParams({ q: input.value.trim(), action: action.value });
        input.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch('api/search_audit.php?' + params.toString(), { credentials: 'same-origin', signal: controller.signal, headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('Pencarian gagal dimuat.');
            const data = await response.json();
            if (current !== requestNumber) return;
            results.innerHTML = data.html;
            counter.textContent = data.total + ' aktivitas';
            if (pager) pager.hidden = true;
            const url = new URL(window.location.href);
            if (input.value.trim()) url.searchParams.set('q', input.value.trim()); else url.searchParams.delete('q');
            if (action.value) url.searchParams.set('action', action.value); else url.searchParams.delete('action');
            url.searchParams.delete('page');
            window.history.replaceState({}, '', url);
        } catch (error) {
            if (error.name !== 'AbortError') results.innerHTML = '<tr><td colspan="5" class="textc err-text">Pencarian tidak dapat dimuat. Silakan coba lagi.</td></tr>';
        } finally {
            if (current === requestNumber) input.removeAttribute('aria-busy');
        }
    };
    input.addEventListener('input', () => { window.clearTimeout(timer); timer = window.setTimeout(search, 180); });
    action.addEventListener('change', search);
    form.addEventListener('submit', (event) => { event.preventDefault(); window.clearTimeout(timer); search(); });
})();
</script>
<script src="assets/nav.js?v=<?= ASSET_VERSION ?>"></script>
</body></html>
