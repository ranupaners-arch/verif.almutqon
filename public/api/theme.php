<?php
// Endpoint tema dinonaktifkan setelah fitur ganti tema dihapus.
require dirname(__DIR__, 2) . '/app/Bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');
http_response_code(410);
echo json_encode(['success' => false, 'message' => 'Fitur tema sudah dihapus.']);
