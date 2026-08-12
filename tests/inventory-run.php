<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Database;
use App\InventoryCategoryStore;
use App\InventoryService;

$failures = 0;
$assert = static function (bool $condition, string $label, string $detail = '') use (&$failures): void {
    if ($condition) {
        echo "OK  {$label}\n";
        return;
    }
    $failures++;
    echo "FAIL {$label}" . ($detail !== '' ? ": {$detail}" : '') . "\n";
};

$service = new InventoryService();
$sync = $service->syncFromPph();
$assert(
    (int) $sync['products_created'] === 0 && (int) $sync['deliveries_imported'] === 0,
    'sincronización PPH idempotente',
    json_encode($sync, JSON_UNESCAPED_UNICODE)
);

$products = Database::table('marketing_inventory_products');
$deliveries = Database::table('marketing_inventory_deliveries');
$items = Database::table('marketing_inventory_delivery_items');
$sourceProducts = Database::table('jet_cct_inventario_pph');
$sourceRewards = Database::table('jet_cct_recompensas_pph');

$assert(
    (int) Database::value("SELECT COUNT(*) FROM {$products} WHERE pph_product_id IS NOT NULL")
        === (int) Database::value("SELECT COUNT(*) FROM {$sourceProducts}"),
    'todos los productos PPH están importados'
);
$assert(
    (int) Database::value("SELECT COUNT(*) FROM {$deliveries} WHERE pph_reward_id IS NOT NULL")
        === (int) Database::value("SELECT COUNT(*) FROM {$sourceRewards}"),
    'todo el historial PPH está importado'
);
$assert(
    (int) Database::value(
        "SELECT COUNT(*)
           FROM {$products} p
           JOIN {$sourceProducts} s ON s._ID = p.pph_product_id
          WHERE p.sync_pph = 1
            AND p.quantity <> CAST(s.cantidad AS SIGNED)"
    ) === 0,
    'existencias PPH y locales coinciden'
);
$assert(
    (int) Database::value(
        "SELECT COUNT(*) FROM (
            SELECT pph_product_id
              FROM {$products}
             WHERE pph_product_id IS NOT NULL
             GROUP BY pph_product_id
            HAVING COUNT(*) > 1
        ) duplicated"
    ) === 0,
    'no existen productos PPH duplicados'
);
$assert(
    (int) Database::value(
        "SELECT COUNT(*)
           FROM {$items} i
           LEFT JOIN {$deliveries} d ON d.id = i.delivery_id
          WHERE d.id IS NULL"
    ) === 0,
    'no existen detalles de entrega huérfanos'
);

$categoryStore = new InventoryCategoryStore();
$categories = $categoryStore->all();
$categoryKeys = array_map(
    static fn (string $category): string => function_exists('mb_strtolower')
        ? mb_strtolower($category, 'UTF-8')
        : strtolower($category),
    $categories
);
$productTypes = array_column(
    Database::rows("SELECT DISTINCT type FROM {$products} WHERE type IS NOT NULL AND type <> ''"),
    'type'
);
$missingTypes = array_filter(
    $productTypes,
    static fn (string $type): bool => !in_array(
        function_exists('mb_strtolower') ? mb_strtolower($type, 'UTF-8') : strtolower($type),
        $categoryKeys,
        true
    )
);
$assert($missingTypes === [], 'todas las categorías de productos están guardadas en el JSON');

$categoryCount = count($categories);
$storedCategory = $categoryStore->add('souvenir');
$assert(
    $storedCategory === 'Souvenir' && count($categoryStore->all()) === $categoryCount,
    'las categorías JSON no se duplican por diferencias de mayúsculas'
);

$filteredDashboard = $service->dashboardData([
    'q' => '__producto_inexistente__',
    'history_source' => 'pph',
    'movement_type' => 'opening',
]);
$assert(
    $filteredDashboard['products'] === []
        && count($filteredDashboard['activeProducts']) === (int) Database::value("SELECT COUNT(*) FROM {$products} WHERE active = 1"),
    'los filtros de existencias no limitan los productos del popup de entrega'
);
$assert(
    $filteredDashboard['recentDeliveries'] !== [] && array_filter(
        $filteredDashboard['recentDeliveries'],
        static fn (array $delivery): bool => (string) $delivery['source'] !== 'pph'
    ) === [],
    'el historial filtra por origen'
);
$assert(
    $filteredDashboard['recentMovements'] !== [] && array_filter(
        $filteredDashboard['recentMovements'],
        static fn (array $movement): bool => (string) $movement['movement_type'] !== 'opening'
    ) === [],
    'los movimientos filtran por tipo'
);

$normalizeItems = new ReflectionMethod(InventoryService::class, 'normalizeItems');
$checkboxItems = $normalizeItems->invoke($service, [12, 15]);
$assert(
    $checkboxItems === [12 => 1, 15 => 1],
    'la API convierte checkboxes product_ids en entregas de una unidad'
);
$jsonCheckboxItems = $normalizeItems->invoke($service, '[12,15,12]');
$assert(
    $jsonCheckboxItems === [12 => 2, 15 => 1],
    'la API admite listas JSON de checkboxes y consolida productos repetidos'
);

exit($failures === 0 ? 0 : 1);
