<?php
declare(strict_types=1);

/**
 * app/Support/deploy_checks.php — daftar pemeriksaan kesiapan deploy.
 *
 * Satu sumber kebenaran untuk dua pemakai:
 *   - app/Support/check_deploy.php (CLI, dipakai saat deploy dari terminal)
 *   - public/diagnosa.php        (browser, untuk pemilik hosting non-teknis)
 *
 * Sengaja menerima PDO nullable: halaman diagnosa harus tetap bisa dibuka dan
 * melaporkan "koneksi gagal" ketika database-nya yang bermasalah — justru
 * di saat itulah dibutuhkan.
 */

/**
 * @return list<array{ok: bool, warn: bool, label: string, detail: string}>
 */
function deploy_checks(?PDO $pdo): array
{
    $results = [];
    $add = static function (bool $ok, string $label, string $detail, bool $warn = false) use (&$results): void {
        $results[] = ['ok' => $ok, 'warn' => $warn, 'label' => $label, 'detail' => $detail];
    };

    // --- Environment aplikasi -------------------------------------------
    $add(APP_ENV === 'production', 'APP_ENV = production', 'Saat ini APP_ENV = ' . APP_ENV);
    $add(APP_DEBUG === false, 'APP_DEBUG nonaktif', 'Saat ini APP_DEBUG = ' . var_export(APP_DEBUG, true));
    $add(str_starts_with(strtolower(APP_URL), 'https://'), 'APP_URL memakai HTTPS', 'Saat ini APP_URL = ' . APP_URL);
    $add(strlen(APP_SECRET_KEY) >= 32, 'APP_SECRET_KEY memadai', 'APP_SECRET_KEY minimal 32 karakter');
    $add(
        APP_ENV !== 'production' || strlen(APP_SETUP_TOKEN) >= 32,
        'APP_SETUP_TOKEN untuk /diagnosa.php',
        'APP_SETUP_TOKEN kosong atau terlalu pendek — halaman diagnosa nonaktif',
        APP_ENV !== 'production'
    );

    // --- Runtime --------------------------------------------------------
    $add(PHP_VERSION_ID >= 80100, 'Versi PHP >= 8.1', 'Versi PHP ' . PHP_VERSION);
    $add(extension_loaded('pdo_mysql'), 'Ekstensi pdo_mysql', 'pdo_mysql tidak termuat');
    $add(extension_loaded('fileinfo'), 'Ekstensi fileinfo', 'fileinfo tidak termuat — validasi unggah PDF gagal');
    $add(extension_loaded('mbstring'), 'Ekstensi mbstring', 'mbstring tidak termuat — pemrosesan teks beraccent bermasalah');
    $add(ini_get('upload_max_filesize') !== '', 'upload_max_filesize diatur', 'Batas bawaan server: ' . ini_get('upload_max_filesize'));
    $add(
        (int) ini_get('upload_max_filesize') >= 12,
        'Batas unggah >= 12M',
        'upload_max_filesize = ' . ini_get('upload_max_filesize') . ' (butuh minimal 12M agar PDF 10 MB bisa masuk)',
        true
    );

    // --- Lokasi & izin tulis --------------------------------------------
    $add(is_dir(STORAGE_PATH) && is_writable(STORAGE_PATH), 'storage/ writable', STORAGE_PATH . ' tidak ada atau tidak writable');
    $add(is_dir(UPLOAD_DIR) && is_writable(UPLOAD_DIR), 'Direktori unggah PDF writable', UPLOAD_DIR . ' tidak ada atau tidak writable');
    $add(is_dir(AVATAR_DIR) && is_writable(AVATAR_DIR), 'Direktori avatar writable', AVATAR_DIR . ' tidak ada atau tidak writable');
    $add(
        is_dir(LOG_DIR) && is_writable(LOG_DIR),
        'Direktori log writable',
        LOG_DIR . ' tidak ada atau tidak writable — pesan error tidak akan terekam',
        true
    );

    // --- Document root & .htaccess ---------------------------------------
    $documentRoot = rtrim(str_replace('\\', '/', (string) ($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
    $expectedRoot = rtrim(str_replace('\\', '/', PUBLIC_PATH), '/');
    $add(
        $documentRoot === '' || $documentRoot === $expectedRoot,
        'Document root = public/',
        'Document root saat ini ' . ($documentRoot === '' ? 'tidak diketahui' : $documentRoot)
            . '. Bila ini root proyek, pastikan mod_rewrite aktif atau file .htaccess tidak diabaikan.',
        $documentRoot !== '' && $documentRoot !== $expectedRoot
    );
    $add(
        is_file(PUBLIC_PATH . '/.htaccess') && is_file(BASE_PATH . '/.htaccess'),
        '.htaccess tersedia',
        'Berkas .htaccess tidak ditemukan — proteksi direktori & header keamanan tidak aktif',
        true
    );
    $add(
        is_file(PUBLIC_PATH . '/assets/avatars/.htaccess'),
        'Proteksi folder avatar',
        'public/assets/avatars/.htaccess tidak ditemukan — berkas avatar bisa diunduh sebagai skrip',
        true
    );

    // --- Kredensial (hanya status, nilai tidak pernah ditampilkan) --------
    $add(strlen((string) env_get('VERIF_API_TOKEN', '')) >= 32, 'VERIF_API_TOKEN tersedia', 'VERIF_API_TOKEN minimal 32 karakter');
    $add(trim((string) env_get('VERIF_API_SYSTEM_ACTOR_KEY', '')) !== '', 'System actor key tersedia', 'VERIF_API_SYSTEM_ACTOR_KEY wajib diisi');
    $add(trim((string) env_get('DB_PASSWORD', '')) !== '', 'Password database terisi', 'DB_PASSWORD kosong');

    // --- Database --------------------------------------------------------
    if ($pdo === null) {
        $add(false, 'Koneksi database', 'Tidak dapat terhubung ke database — periksa DB_HOST/DB_PORT/DB_USER/DB_PASSWORD');
    } else {
        $add(true, 'Koneksi database', 'Terhubung');
        $databaseName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
        $sessionTz = (string) $pdo->query('SELECT @@session.time_zone')->fetchColumn();
        $databaseNow = (string) $pdo->query('SELECT NOW()')->fetchColumn();
        $add(
            deploy_utc_offset($sessionTz) === (new DateTimeImmutable('now', new DateTimeZone(APP_TIMEZONE)))->getOffset(),
            'Zona waktu MySQL = zona aplikasi',
            'Sesi MySQL time_zone = ' . $sessionTz . ', PHP = ' . APP_TIMEZONE . ' (NOW() ' . $databaseNow . ')',
            true
        );

        foreach (['staff', 'system_actors', 'dokumen', 'activity_log', 'password_resets'] as $table) {
            $statement = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?');
            $statement->execute([$databaseName, $table]);
            $add($statement->fetchColumn() > 0, 'Tabel ' . $table, 'Tabel ' . $table . ' belum ada — import app/Database/schema.sql lewat phpMyAdmin');
        }

        $add((int) $pdo->query('SELECT COUNT(*) FROM system_actors WHERE is_active = 1')->fetchColumn() > 0, 'System actor aktif', 'Belum ada system actor aktif untuk API internal v1');
    }

    // --- Kredensial reset password ---------------------------------------
    $mailEnabled = env_bool('MAIL_ENABLED', false);
    $add(
        !$mailEnabled || filter_var((string) env_get('MAIL_FROM', ''), FILTER_VALIDATE_EMAIL) !== false,
        $mailEnabled ? 'Mail aktif dengan pengirim valid' : 'Mail dinonaktifkan (nilai aman)',
        $mailEnabled ? 'MAIL_FROM tidak valid' : 'Pemulihan password lewat email tidak aktif — siapkan alternatif bila dibutuhkan'
    );

    return $results;
}

/**
 * @param list<array{ok: bool, warn: bool, label: string, detail: string}> $results
 * @return array{failed: int, warned: int, passed: int}
 */
function deploy_check_summary(array $results): array
{
    $failed = 0;
    $warned = 0;
    $passed = 0;
    foreach ($results as $result) {
        if (!$result['ok'] && !$result['warn']) {
            $failed++;
        } elseif (!$result['ok']) {
            $warned++;
        } else {
            $passed++;
        }
    }

    return ['failed' => $failed, 'warned' => $warned, 'passed' => $passed];
}

/**
 * Ubah offset MySQL ("+07:00", "-05:00", "SYSTEM") menjadi detik.
 * "SYSTEM" atau format lain menghasilkan null agar pemanggil bisa menandai
 * pemeriksaan sebagai peringatan, bukan gagal.
 */
function deploy_utc_offset(string $mySqlOffset): ?int
{
    if (!preg_match('/^([+-])(\d{1,2}):?(\d{2})$/', trim($mySqlOffset), $matches)) {
        return null;
    }

    $seconds = ((int) $matches[2] * 3600) + ((int) $matches[3] * 60);

    return $matches[1] === '-' ? -$seconds : $seconds;
}
