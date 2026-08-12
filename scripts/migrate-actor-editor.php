<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Database;

if (PHP_SAPI !== 'cli') {
    exit("Esta migración solo puede ejecutarse por CLI.\n");
}

$apply = in_array('--apply', $argv, true);
$prefix = Database::prefix();
$changes = [
    $prefix . 'jet_cct_contactos' => ['pais' => 'TEXT NULL'],
    $prefix . 'jet_cct_club_pph' => ['pais' => 'TEXT NULL'],
    $prefix . 'jet_cct_copropiedades' => ['pais' => 'TEXT NULL'],
    $prefix . 'jet_cct_proveedores' => ['pais' => 'TEXT NULL', 'indicativo' => 'TEXT NULL'],
    $prefix . 'jet_cct_funcionarios' => ['pais' => 'TEXT NULL', 'indicativo' => 'TEXT NULL'],
];
$auditTable = $prefix . 'marketing_actor_change_log';
$pending = [];
foreach ($changes as $table => $columns) {
    if (!Database::tableExists($table)) {
        throw new RuntimeException("No existe la tabla requerida {$table}.");
    }
    foreach ($columns as $column => $definition) {
        if (!Database::columnExists($table, $column)) {
            $pending[] = ['table' => $table, 'column' => $column, 'definition' => $definition];
        }
    }
}

echo $apply ? "Modo APLICAR\n" : "Modo revisión; usa --apply para ejecutar.\n";
foreach ($pending as $item) {
    echo "Agregar {$item['table']}.{$item['column']} {$item['definition']}\n";
}
if (!Database::tableExists($auditTable)) {
    echo "Crear {$auditTable}\n";
}
if (!$apply) {
    exit(0);
}

$backupDir = dirname(__DIR__) . '/storage/backups';
if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
    throw new RuntimeException('No fue posible crear el directorio de respaldos.');
}
$stamp = date('Ymd_His');
$backupPath = $backupDir . '/actor_editor_migration_' . $stamp . '.jsonl';
$handle = fopen($backupPath, 'wb');
if (!$handle) {
    throw new RuntimeException('No fue posible crear el respaldo.');
}
foreach (array_keys($changes) as $table) {
    $result = Database::connection()->query("SELECT * FROM `{$table}`");
    if (!$result) {
        fclose($handle);
        throw new RuntimeException("No fue posible respaldar {$table}.");
    }
    while ($row = $result->fetch_assoc()) {
        fwrite($handle, json_encode(['table' => $table, 'row' => $row], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    }
    $result->free();
}
fclose($handle);
echo "Respaldo creado: {$backupPath}\n";

foreach ($pending as $item) {
    $sql = "ALTER TABLE `{$item['table']}` ADD COLUMN `{$item['column']}` {$item['definition']}";
    if (!Database::connection()->query($sql)) {
        throw new RuntimeException("Falló la migración de {$item['table']}.{$item['column']}: " . Database::connection()->error);
    }
}

$sql = "CREATE TABLE IF NOT EXISTS `{$auditTable}` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `actor_type` VARCHAR(60) NOT NULL,
    `actor_id` BIGINT NOT NULL,
    `changed_by` BIGINT NOT NULL,
    `changed_by_name` VARCHAR(190) NOT NULL DEFAULT '',
    `reason` VARCHAR(190) NOT NULL DEFAULT '',
    `detail` TEXT NULL,
    `version_before` VARCHAR(40) NOT NULL DEFAULT '',
    `before_json` LONGTEXT NOT NULL,
    `after_json` LONGTEXT NOT NULL,
    `impact_json` LONGTEXT NOT NULL,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    KEY `actor_lookup` (`actor_type`, `actor_id`, `created_at`),
    KEY `changed_by_lookup` (`changed_by`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
if (!Database::connection()->query($sql)) {
    throw new RuntimeException('No fue posible crear la tabla de auditoría: ' . Database::connection()->error);
}
echo "Migración completada.\n";
