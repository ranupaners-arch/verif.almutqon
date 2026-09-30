<?php
declare(strict_types=1);

/**
 * app/Support/security.php — proteksi CSRF dan validasi kekuatan password.
 *
 * Fungsi di sini dipanggil dari seluruh aplikasi; nama & tanda tangannya
 * sengaja tidak diubah.
 */


function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(): bool
{
    return isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], (string) $_POST['csrf_token']);
}

function password_is_valid(string $password): bool
{
    return strlen($password) >= 8
        && preg_match('/[A-Za-z]/', $password)
        && preg_match('/[0-9]/', $password);
}
