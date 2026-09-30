<?php
declare(strict_types=1);

/**
 * app/Support/errors.php — penanganan error global.
 *
 * Tujuannya sederhana: di production pengguna tidak boleh melihat stack trace
 * atau path server, sementara pengembang tetap bisa menemukan penyebab lewat
 * storage/logs/app-YYYY-MM-DD.log.
 */

if (!function_exists('app_log')) {
    /**
     * Tulis satu baris ke log harian. Tidak pernah melempar exception —
     * proses logging gagal harus diam-diam diabaikan, bukan menjatuhkan request.
     */
    function app_log(string $level, string $message): void
    {
        try {
            if (!is_dir(LOG_DIR) && !@mkdir(LOG_DIR, 0755, true) && !is_dir(LOG_DIR)) {
                return;
            }
            $line = sprintf(
                "[%s] %s: %s%s",
                date('Y-m-d H:i:s'),
                strtoupper($level),
                $message,
                PHP_EOL
            );
            @file_put_contents(LOG_DIR . '/app-' . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
        } catch (Throwable $ignored) {
            // Logging harus tidak pernah menggagalkan request.
        }
    }
}

if (!function_exists('app_apply_timezone')) {
    /**
     * Set zona waktu proses. Tanpa ini server memakai UTC sehingga cap waktu
     * pada log dan transcript tidak sama dengan waktu yang ditampilkan di UI.
     */
    function app_apply_timezone(): void
    {
        $timezone = APP_TIMEZONE !== '' ? APP_TIMEZONE : 'Asia/Jakarta';
        if (!in_array($timezone, timezone_identifiers_list(), true)) {
            app_log('warning', 'APP_TIMEZONE tidak dikenal (' . $timezone . '), memakai Asia/Jakarta');
            $timezone = 'Asia/Jakarta';
        }
        date_default_timezone_set($timezone);
    }
}

if (!function_exists('app_request_is_https')) {
    /**
     * Deteksi apakah request yang masuk memakai HTTPS.
     *
     * Di belakang reverse proxy (Cloudflare, load balancer) $_SERVER['HTTPS']
     * kosong; header X-Forwarded-Proto baru diisi bila APP_TRUST_PROXY=true.
     * Header dari klien tidak pernah dipercaya secara default, karena pemanggil
     * bisa mengirimnya sendiri dan memaksa cookie session tidak aman.
     */
    function app_request_is_https(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        if ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) {
            return true;
        }
        if (APP_TRUST_PROXY) {
            $proto = strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
            if ($proto === 'https') {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('app_error_page')) {
    /**
     * Balas 500 tanpa membocorkan detail internal.
     */
    function app_error_page(string $context = ''): void
    {
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, "Fatal: " . $context . PHP_EOL);
            exit(1);
        }
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
        }
        $detail = APP_DEBUG
            ? '<pre style="white-space:pre-wrap;text-align:left">' . htmlspecialchars($context, ENT_QUOTES, 'UTF-8') . '</pre>'
            : '';
        echo '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>Terjadi kesalahan</title></head><body>'
            . '<h1>Terjadi kesalahan pada server</h1>'
            . '<p>Sila muat ulang halaman beberapa saat lagi. '
            . 'Jika kesalahan berlanjut, hubungi administrator.</p>' . $detail
            . '</body></html>';
    }
}

if (!function_exists('app_register_error_handlers')) {
    /**
     * Pasang handler global.
     *
     * Warning/notice/deprecated di production hanya dicatat lalu dibiarkan
     * berjalan, supaya perubahan kecil di refactor tidak tiba-tiba mengubah
     * halaman yang tadinya masih merespons 200 menjadi 500. Di luar production
     * warning diperlakukan sebagai exception agar-developer langsung melihat
     * stack trace-nya.
     */
    function app_register_error_handlers(): void
    {
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if ((error_reporting() & $severity) === 0) {
                return false;
            }
            $level = ($severity & (E_ERROR | E_USER_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR)) ? 'error' : 'warning';
            app_log($level, $message . ' di ' . $file . ':' . $line);

            if (APP_DEBUG) {
                throw new ErrorException($message, 0, $severity, $file, $line);
            }

            return true;
        });

        set_exception_handler(static function (Throwable $e): void {
            app_log('error', $e::class . ': ' . $e->getMessage() . ' di ' . $e->getFile() . ':' . $e->getLine());
            app_error_page($e->getMessage() . ' di ' . $e->getFile() . ':' . $e->getLine());
        });

        register_shutdown_function(static function (): void {
            $last = error_get_last();
            if ($last === null || !in_array($last['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                return;
            }
            $context = ($last['message'] ?? '') . ' di ' . ($last['file'] ?? '') . ':' . ($last['line'] ?? 0);
            app_log('fatal', $context);
            app_error_page($context);
        });
    }
}
