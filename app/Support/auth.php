<?php
declare(strict_types=1);

/**
 * app/Support/auth.php — token reset password staff.
 *
 * Fungsi di sini dipanggil dari seluruh aplikasi; nama & tanda tangannya
 * sengaja tidak diubah.
 */

/**
 * Simpan token reset password staff (hapus token lama email tsb), kembalikan token hex.
 */
function staff_store_reset_token(PDO $pdo, string $email): string
{
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $stmt = $pdo->prepare('DELETE FROM password_resets WHERE email = ?');
    $stmt->execute([$email]);

    $stmt = $pdo->prepare(
        'INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))'
    );
    $stmt->execute([$email, $tokenHash]);

    return $token;
}
/**
 * Cari token reset staff yang valid (ada, belum dipakai, belum kedaluwarsa).
 */
function staff_find_valid_reset(PDO $pdo, string $token): array|false
{
    if (!ctype_xdigit($token) || strlen($token) !== 64) {
        return false;
    }

    $stmt = $pdo->prepare(
        'SELECT id, email FROM password_resets
         WHERE token = ? AND used = 0 AND expires_at > NOW()
         ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([hash('sha256', $token)]);
    return $stmt->fetch();
}
/**
 * Tandai token reset staff sudah dipakai (sekali pakai).
 */
function staff_mark_reset_used(PDO $pdo, string $token): void
{
    $stmt = $pdo->prepare('UPDATE password_resets SET used = 1 WHERE token = ?');
    $stmt->execute([hash('sha256', $token)]);
}
