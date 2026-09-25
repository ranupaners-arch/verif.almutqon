<?php
declare(strict_types=1);

require dirname(__DIR__) . '/Bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!isset($_SESSION['is_staff'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Sesi tidak ditemukan.']);
    exit;
}

$staffId = (int) ($_SESSION['staff_id'] ?? 0);
$roleStmt = $pdo->prepare('SELECT role FROM staff WHERE id = ?');
$roleStmt->execute([$staffId]);
$is_admin_or_direktur = in_array((string) $roleStmt->fetchColumn(), ['admin', 'direktur'], true);

$q = trim((string) ($_GET['q'] ?? ''));
if (mb_strlen($q) > 255) {
    http_response_code(422);
    echo json_encode(['error' => 'Kata pencarian terlalu panjang.']);
    exit;
}
$status = (string) ($_GET['status'] ?? '');
$status = in_array($status, ['active', 'revoked'], true) ? $status : '';

$where = ["d.jenis = 'dokumen'"];
$params = [];
if ($q !== '') {
    $where[] = '(d.no_surat LIKE ? OR d.perihal LIKE ? OR d.penerima LIKE ?)';
    $like = '%' . $q . '%';
    $params = [$like, $like, $like];
}
if ($status !== '') {
    $where[] = 'd.status = ?';
    $params[] = $status;
}
$where_sql = implode(' AND ', $where);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM dokumen d WHERE $where_sql");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

$docStmt = $pdo->prepare("SELECT d.*, COALESCE(s.nama_lengkap, sa.display_name) AS staff_name
    FROM dokumen d LEFT JOIN staff s ON d.staff_id = s.id LEFT JOIN system_actors sa ON d.system_actor_id = sa.id
    WHERE $where_sql
    ORDER BY (d.nomor_urut IS NULL), d.nomor_urut DESC
    LIMIT 100");
$docStmt->execute($params);
$docs = $docStmt->fetchAll();

ob_start();
if ($docs) {
    echo '<div class="doclist">';
    foreach ($docs as $doc) {
        include dirname(__DIR__) . '/Pages/_doc_item.php';
    }
    echo '</div>';
} else {
    echo '<div class="textc" style="padding:48px 0"><p class="faint">Tidak ada dokumen yang cocok dengan pencarian/filter.</p></div>';
}
$html = ob_get_clean();

echo json_encode([
    'total' => $total,
    'html' => $html,
    'limited' => $total > 100,
], JSON_UNESCAPED_UNICODE);
