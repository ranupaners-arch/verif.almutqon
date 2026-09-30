<?php
declare(strict_types=1);

/**
 * app/Support/mail.php — pengiriman email reset password.
 *
 * Fungsi di sini dipanggil dari seluruh aplikasi; nama & tanda tangannya
 * sengaja tidak diubah.
 */

/**
 * Kirim email melalui mail transport server. MAIL_ENABLED harus aktif.
 * Mengembalikan ['ok' => bool, 'error' => string].
 */
function send_mail(string $to, string $subject, string $body): array
{
    if (!filter_var(env_get('MAIL_ENABLED', 'false'), FILTER_VALIDATE_BOOL)) {
        return ['ok' => false, 'error' => 'Mail transport dinonaktifkan'];
    }

    $from = trim((string) (env_get('MAIL_FROM', '')));
    $fromName = trim((string) (env_get('MAIL_FROM_NAME', 'AL MUTQON Verification')));
    if (!filter_var($to, FILTER_VALIDATE_EMAIL) || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Alamat email tidak valid'];
    }

    $safeFromName = str_replace(["\r", "\n"], '', $fromName);
    $headers = [
        'From: ' . $safeFromName . ' <' . $from . '>',
        'Content-Type: text/plain; charset=UTF-8',
        'X-Mailer: PHP/' . PHP_VERSION,
    ];

    $sent = mail($to, $subject, $body, implode("\r\n", $headers));
    return $sent
        ? ['ok' => true, 'error' => '']
        : ['ok' => false, 'error' => 'Mail transport gagal mengirim pesan'];
}
