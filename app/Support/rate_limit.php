<?php
declare(strict_types=1);

/**
 * app/Support/rate_limit.php — pembatasan laju berbasis tabel activity_log.
 *
 * Fungsi di sini dipanggil dari seluruh aplikasi; nama & tanda tangannya
 * sengaja tidak diubah.
 */

/**
 * Rate limit generik berbasis activity_log: hitung entri `action` untuk
 * `identifier` (IP) dalam jendela `windowSeconds` detik.
 */
function rate_limit_ok(PDO $pdo, string $action, string $identifier, int $maxAttempts, int $windowSeconds): bool
{
    // Batas waktu dihitung MySQL, bukan PHP: created_at ditulis NOW() sehingga
    // keduanya dijamin memakai zona waktu yang sama.
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM activity_log
         WHERE action = ? AND ip_address = ?
           AND created_at > (NOW() - INTERVAL ? SECOND)'
    );
    $stmt->execute([$action, $identifier, $windowSeconds]);

    return (int) $stmt->fetchColumn() < $maxAttempts;
}

function rate_limit_record(PDO $pdo, string $action, string $identifier, string $description): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO activity_log (staff_id, system_actor_id, action, dokumen_id, description, ip_address)
         VALUES (NULL, NULL, ?, NULL, ?, ?)'
    );
    $stmt->execute([$action, $description, $identifier]);
}
