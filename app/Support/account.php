<?php
declare(strict_types=1);

/**
 * app/Support/account.php — ringkasan akun staff untuk UI masthead.
 *
 * Fungsi di sini dipanggil dari seluruh aplikasi; nama & tanda tangannya
 * sengaja tidak diubah.
 */

/**
 * Ringkasan akun staff untuk menu akun masthead + tema halaman.
 * Selalu baca terbaru dari database (bukan snapshot session).
 */
function akun_ringkas(PDO $pdo, int $staffId): array
{
    $stmt = $pdo->prepare('SELECT nama_lengkap, jabatan, avatar, theme, role, nip, email FROM staff WHERE id = ? LIMIT 1');
    $stmt->execute([$staffId]);
    $r = $stmt->fetch() ?: [];
    $theme = (string) ($r['theme'] ?? 'light');
    if (!in_array($theme, ['light', 'dark', 'system'], true)) {
        $theme = 'light';
    }
    return [
        'nama' => (string) ($r['nama_lengkap'] ?? ''),
        'jabatan' => (string) ($r['jabatan'] ?? ''),
        'avatar' => (string) ($r['avatar'] ?? ''),
        'theme' => $theme,
        'role' => (string) ($r['role'] ?? 'staff'),
        'nip' => (string) ($r['nip'] ?? ''),
        'email' => (string) ($r['email'] ?? ''),
    ];
}
