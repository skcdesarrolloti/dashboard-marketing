<?php

declare(strict_types=1);

use App\Database;
use App\InventorySchema;
use App\InventoryService;

require dirname(__DIR__) . '/app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script solo puede ejecutarse por CLI.\n");
}

InventorySchema::ensure();

$backupPath = dirname(__DIR__) . '/storage/backups';
if (!is_dir($backupPath)) {
    mkdir($backupPath, 0775, true);
}
$backupFile = $backupPath . '/souvenir_inventory_pph_' . date('Ymd_His') . '.json';
$backup = [];
foreach (['jet_cct_inventario_pph', 'jet_cct_recompensas_pph'] as $name) {
    $table = Database::table($name);
    $backup[$table] = Database::tableExists($table) ? Database::rows("SELECT * FROM {$table} ORDER BY _ID") : [];
}
file_put_contents(
    $backupFile,
    json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
);

$summary = (new InventoryService())->syncFromPph();

echo "Migración de inventario completada.\n";
echo "Productos importados: " . (int) $summary['products_created'] . "\n";
echo "Productos actualizados: " . (int) $summary['products_updated'] . "\n";
echo "Entregas PPH importadas: " . (int) $summary['deliveries_imported'] . "\n";
echo "Respaldo: {$backupFile}\n";
