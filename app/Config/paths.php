<?php
declare(strict_types=1);

/**
 * app/Config/paths.php — lokasi direktori kerja.
 *
 * Semua path-nya dapat dioverride lewat environment karena shared hosting
 * hosted di luar document root. Nilai default menunjuk ke struktur standard.
 */

define('STORAGE_PATH', rtrim(env_str('APP_STORAGE_PATH', BASE_PATH . '/storage'), '/'));
define('PUBLIC_PATH', rtrim(env_str('APP_PUBLIC_PATH', BASE_PATH . '/public'), '/'));

// Berkas yang diunggah pengguna tidak boleh berada di dalam web root.
define('UPLOAD_DIR', rtrim(env_str('APP_UPLOAD_DIR', STORAGE_PATH . '/uploads'), '/'));

// Default masih di dalam web root karena nilai yang tersimpan di kolom
// `staff.avatar` berupa path relatif `assets/avatars/...`. Memindahkannya ke
// storage harus disertai migrasi path, jadi tidak dilakukan di refactor ini.
define('AVATAR_DIR', rtrim(env_str('APP_AVATAR_DIR', PUBLIC_PATH . '/assets/avatars'), '/'));
// Log tidak boleh dibaca lewat HTTP, jadi diletakkan di luar document root.
define('LOG_DIR', rtrim(env_str('APP_LOG_DIR', STORAGE_PATH . '/logs'), '/'));
