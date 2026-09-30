<?php
declare(strict_types=1);

/**
 * app/Config/env.php — pembacaan konfigurasi aplikasi.
 *
 * Urutan prioritas (yang atas menang):
 *   1. environment server  (getenv) — mis. "Environment Variables" cPanel
 *   2. $_SERVER / $_ENV
 *   3. file .env di root project
 *
 * Parser .env sengaja dibuat sendiri, bukan parse_ini_file: nilai yang
 * mengandung `#` atau `;` (mis. password database) akan merusak parser INI.
 * Aturan file .env:
 *   - satu variabel per baris, format KEY=value
 *   - nilai boleh diapit " atau ' jika mengandung spasi
 *   - komentar hanya boleh di baris sendiri, diawali `;` atau `#`
 *   - TIDAK ada komentar di akhir baris (agar `#` di password tidak terpotong)
 */

$GLOBALS['__envFileValues'] = [];
$__envFile = BASE_PATH . '/.env';

if (is_file($__envFile) && is_readable($__envFile)) {
    $__envLines = file($__envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (is_array($__envLines)) {
        foreach ($__envLines as $__line) {
            $__line = trim($__line);
            if ($__line === '' || $__line[0] === ';' || $__line[0] === '#') {
                continue;
            }
            $__pos = strpos($__line, '=');
            if ($__pos === false) {
                continue;
            }
            $__key = trim(substr($__line, 0, $__pos));
            if ($__key === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $__key)) {
                continue;
            }
            $__value = trim(substr($__line, $__pos + 1));
            $__len = strlen($__value);
            if ($__len >= 2 && $__value[0] === '"' && $__value[$__len - 1] === '"') {
                $__value = substr($__value, 1, -1);
            } elseif ($__len >= 2 && $__value[0] === "'" && $__value[$__len - 1] === "'") {
                $__value = substr($__value, 1, -1);
            }
            $GLOBALS['__envFileValues'][$__key] = $__value;
        }
    }
}
unset($__envFile, $__envLines, $__line, $__pos, $__key, $__value, $__len);

/**
 * Nilai konfigurasi, atau $default bila tidak ditemukan.
 */
function env_get(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    if ($value === false) {
        $value = $_SERVER[$key] ?? $_ENV[$key] ?? null;
    }
    if ($value === null || $value === false) {
        $value = $GLOBALS['__envFileValues'][$key] ?? null;
    }

    return ($value === null || $value === false) ? $default : (string) $value;
}

function env_str(string $key, string $default): string
{
    $value = env_get($key, $default);

    return $value === null ? $default : $value;
}

function env_int(string $key, int $default): int
{
    $value = env_get($key, null);

    return ($value === null || !is_numeric(trim($value))) ? $default : (int) trim($value);
}

function env_bool(string $key, bool $default): bool
{
    $value = env_get($key, null);
    if ($value === null || trim($value) === '') {
        return $default;
    }
    $parsed = filter_var(trim($value), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

    return $parsed === null ? $default : $parsed;
}

// Dijaga agar getenv() lama (dipakai script yang belum dimigrasi) tetap bekerja.
if (function_exists('putenv')) {
    foreach ($GLOBALS['__envFileValues'] as $__k => $__v) {
        if (getenv($__k) === false) {
            @putenv($__k . '=' . $__v);
        }
    }
    unset($__k, $__v);
}
