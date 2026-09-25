<?php
declare(strict_types=1);

require dirname(__DIR__) . '/Bootstrap.php';

$documentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$documentId) {
    http_response_code(404);
    exit('Dokumen tidak ditemukan.');
}

// Signed URL: token = HMAC-SHA256("download:{id}", APP_SECRET_KEY) agar ID berurut
// tidak bisa diedit/ditebak bebas. Link dibuat di halaman verifikasi (index.php).
$providedToken = (string) ($_GET['t'] ?? '');
$expectedToken = hash_hmac('sha256', 'download:' . $documentId, APP_SECRET_KEY);
if ($providedToken === '' || !hash_equals($expectedToken, $providedToken)) {
    http_response_code(403);
    exit('Tautan unduhan tidak valid. Buka halaman verifikasi dokumen, lalu tekan tombol unduh.');
}

$stmt = $pdo->prepare('SELECT pdf_path FROM dokumen WHERE id = ? AND status = ?');
$stmt->execute([$documentId, 'active']);
$document = $stmt->fetch();
$filePath = resolve_pdf_path($document['pdf_path'] ?? null);

if ($filePath === null) {
    http_response_code(404);
    exit('PDF belum tersedia.');
}

header('Content-Type: application/pdf');
header('Content-Length: ' . (string) filesize($filePath));
header('Content-Disposition: inline; filename="dokumen-' . $documentId . '.pdf"');
header('X-Content-Type-Options: nosniff');
readfile($filePath);
