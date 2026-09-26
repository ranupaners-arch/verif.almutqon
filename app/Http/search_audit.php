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
if (!in_array((string) $roleStmt->fetchColumn(), ['admin', 'direktur'], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'Akses ditolak.']);
    exit;
}

$q = trim((string) ($_GET['q'] ?? ''));
$action = trim((string) ($_GET['action'] ?? ''));
if (mb_strlen($q) > 255 || mb_strlen($action) > 100) {
    http_response_code(422);
    echo json_encode(['error' => 'Parameter pencarian terlalu panjang.']);
    exit;
}

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
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM activity_log l LEFT JOIN staff s ON s.id = l.staff_id LEFT JOIN system_actors sa ON sa.id = l.system_actor_id $whereSql");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

$stmt = $pdo->prepare("SELECT l.action, l.description, l.ip_address, l.created_at,
        COALESCE(s.nama_lengkap, sa.display_name) AS nama_lengkap
    FROM activity_log l LEFT JOIN staff s ON s.id = l.staff_id LEFT JOIN system_actors sa ON sa.id = l.system_actor_id
    $whereSql ORDER BY l.created_at DESC LIMIT 100");
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

ob_start();
if ($logs) {
    foreach ($logs as $log) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars(date('d M Y, H:i', strtotime($log['created_at']))) . '</td>';
        echo '<td>' . htmlspecialchars($log['nama_lengkap'] ?: 'Sistem') . '</td>';
        echo '<td><span class="badge badge-muted">' . htmlspecialchars(str_replace('_', ' ', $log['action'])) . '</span></td>';
        echo '<td>' . htmlspecialchars($log['description']) . '</td>';
        echo '<td class="mono faint">' . htmlspecialchars($log['ip_address']) . '</td>';
        echo '</tr>';
    }
} else {
    echo '<tr><td colspan="5" class="textc faint">Tidak ada aktivitas yang cocok.</td></tr>';
}
$html = ob_get_clean();

echo json_encode(['total' => $total, 'html' => $html, 'limited' => $total > 100], JSON_UNESCAPED_UNICODE);
