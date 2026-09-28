<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/Bootstrap.php';

function system_actor_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function system_actor_fk_exists(PDO $pdo, string $name): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema = DATABASE() AND constraint_name = ? AND constraint_type = \'FOREIGN KEY\'');
    $stmt->execute([$name]);
    return (int) $stmt->fetchColumn() > 0;
}

$pdo->exec("CREATE TABLE IF NOT EXISTS system_actors (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_key VARCHAR(100) NOT NULL UNIQUE,
    display_name VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$actorKey = trim((string) (getenv('VERIF_API_SYSTEM_ACTOR_KEY') ?: ''));
if ($actorKey === '') {
    fwrite(STDERR, "VERIF_API_SYSTEM_ACTOR_KEY belum dikonfigurasi.\n");
    exit(1);
}
$pdo->prepare('INSERT INTO system_actors (actor_key, display_name, is_active) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE display_name = VALUES(display_name), is_active = 1')
    ->execute([$actorKey, 'ALMUTQON Bridge (sistem)']);

if (!system_actor_column_exists($pdo, 'dokumen', 'system_actor_id')) {
    $pdo->exec('ALTER TABLE dokumen ADD COLUMN system_actor_id BIGINT UNSIGNED NULL AFTER staff_id');
}
if (!system_actor_column_exists($pdo, 'activity_log', 'system_actor_id')) {
    $pdo->exec('ALTER TABLE activity_log ADD COLUMN system_actor_id BIGINT UNSIGNED NULL AFTER staff_id');
}
$pdo->exec('ALTER TABLE dokumen MODIFY staff_id BIGINT UNSIGNED NULL');

if (!system_actor_fk_exists($pdo, 'fk_dokumen_system_actor')) {
    $pdo->exec('ALTER TABLE dokumen ADD CONSTRAINT fk_dokumen_system_actor FOREIGN KEY (system_actor_id) REFERENCES system_actors(id)');
}
if (!system_actor_fk_exists($pdo, 'fk_activity_system_actor')) {
    $pdo->exec('ALTER TABLE activity_log ADD CONSTRAINT fk_activity_system_actor FOREIGN KEY (system_actor_id) REFERENCES system_actors(id) ON DELETE SET NULL');
}

$actorStmt = $pdo->prepare('SELECT id FROM system_actors WHERE actor_key = ?');
$actorStmt->execute([$actorKey]);
$actorId = (int) $actorStmt->fetchColumn();
$legacyId = $pdo->query("SELECT id FROM staff WHERE nip = 'SYSTEM-ALMUTQON' LIMIT 1")->fetchColumn();
if ($legacyId !== false) {
    $pdo->prepare('UPDATE dokumen SET system_actor_id = ?, staff_id = NULL WHERE staff_id = ?')->execute([$actorId, (int) $legacyId]);
    $pdo->prepare('UPDATE activity_log SET system_actor_id = ?, staff_id = NULL WHERE staff_id = ?')->execute([$actorId, (int) $legacyId]);
}

echo "System actor ALMUTQON Bridge siap.\n";
