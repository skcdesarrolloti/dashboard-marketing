<?php

declare(strict_types=1);

namespace App;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;

final class InventoryService
{
    private string $products;
    private string $deliveries;
    private string $items;
    private string $movements;
    private string $evidence;
    private string $pphProducts;
    private string $pphRewards;
    private InventoryCategoryStore $categories;

    public function __construct()
    {
        InventorySchema::ensure();
        $this->products = Database::table('marketing_inventory_products');
        $this->deliveries = Database::table('marketing_inventory_deliveries');
        $this->items = Database::table('marketing_inventory_delivery_items');
        $this->movements = Database::table('marketing_inventory_movements');
        $this->evidence = Database::table('marketing_inventory_evidence');
        $this->pphProducts = Database::table('jet_cct_inventario_pph');
        $this->pphRewards = Database::table('jet_cct_recompensas_pph');
        $this->categories = new InventoryCategoryStore();
    }

    public function syncFromPph(): array
    {
        $summary = ['products_created' => 0, 'products_updated' => 0, 'deliveries_imported' => 0];
        if (!Database::tableExists($this->pphProducts)) {
            return $summary;
        }

        Database::beginTransaction();
        try {
            $sourceProducts = Database::rows(
                "SELECT _ID, cct_status, cct_modified, cantidad, precio, valor_punto, titulo, img
                   FROM {$this->pphProducts}
                  ORDER BY _ID"
            );
            $existingProducts = Database::rows(
                "SELECT * FROM {$this->products} WHERE pph_product_id IS NOT NULL FOR UPDATE"
            );
            $productsByPphId = [];
            foreach ($existingProducts as $existingProduct) {
                $productsByPphId[(int) $existingProduct['pph_product_id']] = $existingProduct;
            }
            foreach ($sourceProducts as $source) {
                $pphId = (int) ($source['_ID'] ?? 0);
                if ($pphId <= 0) {
                    continue;
                }
                $quantity = max(0, (int) ($source['cantidad'] ?? 0));
                $hash = $this->pphHash($source);
                $existing = $productsByPphId[$pphId] ?? [];
                $now = date('Y-m-d H:i:s');
                if ($existing === []) {
                    $productId = $this->insert($this->products, [
                        'name' => trim((string) ($source['titulo'] ?? '')) ?: 'Producto PPH ' . $pphId,
                        'type' => 'PPH',
                        'sku' => null,
                        'description' => null,
                        'image_url' => trim((string) ($source['img'] ?? '')),
                        'quantity' => $quantity,
                        'unit' => 'unidad',
                        'minimum_stock' => 0,
                        'price' => $this->nullableDecimal($source['precio'] ?? null),
                        'points' => $this->nullableInt($source['valor_punto'] ?? null),
                        'active' => $this->pphActive($source) ? 1 : 0,
                        'sync_pph' => 1,
                        'pph_product_id' => $pphId,
                        'pph_quantity' => $quantity,
                        'pph_hash' => $hash,
                        'pph_modified_at' => $this->nullableDate($source['cct_modified'] ?? null),
                        'created_by' => null,
                        'updated_by' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $this->insertMovement($productId, null, 'opening', $quantity, 0, $quantity, 'Saldo inicial importado desde PPH.', 'pph', null);
                    $productsByPphId[$pphId] = ['id' => $productId, 'pph_product_id' => $pphId];
                    $summary['products_created']++;
                    continue;
                }

                if (!(int) ($existing['sync_pph'] ?? 0)) {
                    continue;
                }
                if ((string) ($existing['pph_hash'] ?? '') === $hash) {
                    continue;
                }
                $before = (int) ($existing['quantity'] ?? 0);
                $this->updateById($this->products, (int) $existing['id'], [
                    'name' => trim((string) ($source['titulo'] ?? '')) ?: (string) $existing['name'],
                    'image_url' => trim((string) ($source['img'] ?? '')),
                    'quantity' => $quantity,
                    'price' => $this->nullableDecimal($source['precio'] ?? null),
                    'points' => $this->nullableInt($source['valor_punto'] ?? null),
                    'active' => $this->pphActive($source) ? 1 : 0,
                    'pph_quantity' => $quantity,
                    'pph_hash' => $hash,
                    'pph_modified_at' => $this->nullableDate($source['cct_modified'] ?? null),
                    'updated_at' => $now,
                ]);
                if ($quantity !== $before) {
                    $this->insertMovement(
                        (int) $existing['id'],
                        null,
                        'pph_reconciliation',
                        $quantity - $before,
                        $before,
                        $quantity,
                        'Conciliación automática por cambio de existencias en PPH.',
                        'pph',
                        null
                    );
                }
                $summary['products_updated']++;
            }

            if (Database::tableExists($this->pphRewards)) {
                $rewards = Database::rows(
                    "SELECT _ID, cct_author_id, cct_created, fecha, cantidad, id_pph, nombre,
                            tarjeta_bienvenida, id_producto, producto, puntos
                       FROM {$this->pphRewards}
                      ORDER BY _ID"
                );
                $importedRewardRows = Database::rows(
                    "SELECT pph_reward_id FROM {$this->deliveries} WHERE pph_reward_id IS NOT NULL"
                );
                $importedRewardIds = [];
                foreach ($importedRewardRows as $importedRewardRow) {
                    $importedRewardIds[(int) $importedRewardRow['pph_reward_id']] = true;
                }
                foreach ($rewards as $reward) {
                    $rewardId = (int) ($reward['_ID'] ?? 0);
                    if ($rewardId > 0 && !isset($importedRewardIds[$rewardId]) && $this->importPphReward($reward)) {
                        $importedRewardIds[$rewardId] = true;
                        $summary['deliveries_imported']++;
                    }
                }
            }
            Database::commit();
        } catch (Throwable $e) {
            Database::rollBack();
            throw $e;
        }

        return $summary;
    }

    public function dashboardData(array $filters = []): array
    {
        $sync = $this->syncFromPph();
        $query = trim((string) ($filters['q'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $where = ['1=1'];
        $types = '';
        $params = [];
        if ($query !== '') {
            $where[] = '(name LIKE ? OR sku LIKE ? OR type LIKE ?)';
            $types .= 'sss';
            $term = '%' . $query . '%';
            array_push($params, $term, $term, $term);
        }
        if ($status === 'active') {
            $where[] = 'active = 1';
        } elseif ($status === 'archived') {
            $where[] = 'active = 0';
        } elseif ($status === 'out') {
            $where[] = 'active = 1 AND quantity = 0';
        } elseif ($status === 'low') {
            $where[] = 'active = 1 AND minimum_stock > 0 AND quantity <= minimum_stock';
        }

        $products = Database::rows(
            "SELECT * FROM {$this->products} WHERE " . implode(' AND ', $where) . "
             ORDER BY active DESC, quantity = 0 DESC, name ASC",
            $types,
            $params
        );
        $summary = Database::one(
            "SELECT COUNT(*) products_total,
                    SUM(active = 1) products_active,
                    COALESCE(SUM(CASE WHEN active = 1 THEN quantity ELSE 0 END), 0) units_available,
                    SUM(active = 1 AND quantity = 0) products_out,
                    SUM(active = 1 AND minimum_stock > 0 AND quantity <= minimum_stock) products_low
               FROM {$this->products}"
        );

        $historyWhere = ['1=1'];
        $historyTypes = '';
        $historyParams = [];
        $historyQuery = trim((string) ($filters['history_q'] ?? ''));
        if ($historyQuery !== '') {
            $historyWhere[] = "(d.recipient_name LIKE ?
                OR d.recipient_id LIKE ?
                OR d.recipient_document LIKE ?
                OR d.recipient_source LIKE ?
                OR d.recipient_metadata LIKE ?
                OR EXISTS (
                    SELECT 1
                      FROM {$this->items} history_item
                     WHERE history_item.delivery_id = d.id
                       AND history_item.product_name LIKE ?
                ))";
            $historyTypes .= 'ssssss';
            $term = '%' . $historyQuery . '%';
            array_push($historyParams, $term, $term, $term, $term, $term, $term);
        }
        $historyFrom = $this->filterDate($filters['history_from'] ?? null);
        if ($historyFrom !== '') {
            $historyWhere[] = 'd.delivered_at >= ?';
            $historyTypes .= 's';
            $historyParams[] = $historyFrom . ' 00:00:00';
        }
        $historyTo = $this->filterDate($filters['history_to'] ?? null);
        if ($historyTo !== '') {
            $historyWhere[] = 'd.delivered_at <= ?';
            $historyTypes .= 's';
            $historyParams[] = $historyTo . ' 23:59:59';
        }
        $historyProduct = max(0, (int) ($filters['history_product'] ?? 0));
        if ($historyProduct > 0) {
            $historyWhere[] = "EXISTS (
                SELECT 1
                  FROM {$this->items} filtered_item
                 WHERE filtered_item.delivery_id = d.id
                   AND filtered_item.product_id = ?
            )";
            $historyTypes .= 'i';
            $historyParams[] = $historyProduct;
        }
        $historySource = trim((string) ($filters['history_source'] ?? ''));
        if ($historySource !== '') {
            $historyWhere[] = 'd.source = ?';
            $historyTypes .= 's';
            $historyParams[] = $historySource;
        }
        $historyStatus = trim((string) ($filters['history_status'] ?? ''));
        if (in_array($historyStatus, ['delivered', 'cancelled'], true)) {
            $historyWhere[] = 'd.status = ?';
            $historyTypes .= 's';
            $historyParams[] = $historyStatus;
        }

        $recentDeliveries = Database::rows(
            "SELECT d.*,
                    GROUP_CONCAT(CONCAT(i.product_name, ' × ', i.quantity) ORDER BY i.id SEPARATOR ' · ') products,
                    SUM(i.quantity) total_items,
                    (SELECT COUNT(*) FROM {$this->evidence} e WHERE e.delivery_id = d.id) evidence_count
               FROM {$this->deliveries} d
               LEFT JOIN {$this->items} i ON i.delivery_id = d.id
              WHERE " . implode(' AND ', $historyWhere) . "
              GROUP BY d.id
              ORDER BY d.delivered_at DESC, d.id DESC
              LIMIT 150",
            $historyTypes,
            $historyParams
        );
        $evidenceByDelivery = [];
        if ($recentDeliveries !== []) {
            $deliveryIds = array_map(static fn (array $row): int => (int) $row['id'], $recentDeliveries);
            $evidenceRows = Database::rows(
                "SELECT * FROM {$this->evidence}
                  WHERE delivery_id IN (" . implode(',', array_fill(0, count($deliveryIds), '?')) . ")
                  ORDER BY id",
                str_repeat('i', count($deliveryIds)),
                $deliveryIds
            );
            foreach ($evidenceRows as $evidenceRow) {
                $evidenceByDelivery[(int) $evidenceRow['delivery_id']][] = $evidenceRow;
            }
            foreach ($recentDeliveries as &$deliveryRow) {
                $deliveryRow['evidence'] = $evidenceByDelivery[(int) $deliveryRow['id']] ?? [];
                $decodedMetadata = json_decode((string) ($deliveryRow['recipient_metadata'] ?? ''), true);
                $deliveryRow['recipient_metadata_data'] = is_array($decodedMetadata) ? $decodedMetadata : [];
            }
            unset($deliveryRow);
        }
        $movementWhere = ['1=1'];
        $movementTypes = '';
        $movementParams = [];
        $movementQuery = trim((string) ($filters['movement_q'] ?? ''));
        if ($movementQuery !== '') {
            $movementWhere[] = '(p.name LIKE ? OR m.reason LIKE ? OR d.recipient_name LIKE ?)';
            $movementTypes .= 'sss';
            $term = '%' . $movementQuery . '%';
            array_push($movementParams, $term, $term, $term);
        }
        $movementFrom = $this->filterDate($filters['movement_from'] ?? null);
        if ($movementFrom !== '') {
            $movementWhere[] = 'm.created_at >= ?';
            $movementTypes .= 's';
            $movementParams[] = $movementFrom . ' 00:00:00';
        }
        $movementTo = $this->filterDate($filters['movement_to'] ?? null);
        if ($movementTo !== '') {
            $movementWhere[] = 'm.created_at <= ?';
            $movementTypes .= 's';
            $movementParams[] = $movementTo . ' 23:59:59';
        }
        $movementProduct = max(0, (int) ($filters['movement_product'] ?? 0));
        if ($movementProduct > 0) {
            $movementWhere[] = 'm.product_id = ?';
            $movementTypes .= 'i';
            $movementParams[] = $movementProduct;
        }
        $movementSource = trim((string) ($filters['movement_source'] ?? ''));
        if ($movementSource !== '') {
            $movementWhere[] = 'm.source = ?';
            $movementTypes .= 's';
            $movementParams[] = $movementSource;
        }
        $movementType = trim((string) ($filters['movement_type'] ?? ''));
        if ($movementType !== '') {
            $movementWhere[] = 'm.movement_type = ?';
            $movementTypes .= 's';
            $movementParams[] = $movementType;
        }

        $recentMovements = Database::rows(
            "SELECT m.*, p.name product_name, d.recipient_name
               FROM {$this->movements} m
               JOIN {$this->products} p ON p.id = m.product_id
               LEFT JOIN {$this->deliveries} d ON d.id = m.delivery_id
              WHERE " . implode(' AND ', $movementWhere) . "
              ORDER BY m.created_at DESC, m.id DESC
              LIMIT 200",
            $movementTypes,
            $movementParams
        );
        if ($recentMovements !== []) {
            $movementIds = array_map(static fn (array $row): int => (int) $row['id'], $recentMovements);
            $movementEvidence = Database::rows(
                "SELECT * FROM {$this->evidence}
                  WHERE movement_id IN (" . implode(',', array_fill(0, count($movementIds), '?')) . ")
                  ORDER BY id",
                str_repeat('i', count($movementIds)),
                $movementIds
            );
            $evidenceByMovement = [];
            foreach ($movementEvidence as $evidenceRow) {
                $evidenceByMovement[(int) $evidenceRow['movement_id']][] = $evidenceRow;
            }
            foreach ($recentMovements as &$movementRow) {
                $movementRow['evidence'] = $evidenceByMovement[(int) $movementRow['id']] ?? [];
            }
            unset($movementRow);
        }

        $categoryRows = Database::rows(
            "SELECT DISTINCT type
               FROM {$this->products}
              WHERE type IS NOT NULL AND type <> ''
              ORDER BY type"
        );
        $categories = $this->categories->merge(array_column($categoryRows, 'type'));
        $filterProducts = Database::rows(
            "SELECT id, name, active
               FROM {$this->products}
              ORDER BY active DESC, name"
        );
        $activeProducts = Database::rows(
            "SELECT id, name, quantity, unit
               FROM {$this->products}
              WHERE active = 1
              ORDER BY name"
        );
        $deliverySources = array_column(
            Database::rows("SELECT DISTINCT source FROM {$this->deliveries} WHERE source <> '' ORDER BY source"),
            'source'
        );
        $movementSources = array_column(
            Database::rows("SELECT DISTINCT source FROM {$this->movements} WHERE source <> '' ORDER BY source"),
            'source'
        );
        $movementTypeOptions = array_column(
            Database::rows("SELECT DISTINCT movement_type FROM {$this->movements} WHERE movement_type <> '' ORDER BY movement_type"),
            'movement_type'
        );

        return compact(
            'products',
            'summary',
            'recentDeliveries',
            'recentMovements',
            'sync',
            'categories',
            'filterProducts',
            'activeProducts',
            'deliverySources',
            'movementSources',
            'movementTypeOptions'
        );
    }

    public function activeProducts(): array
    {
        $this->syncFromPph();
        return Database::rows(
            "SELECT id, name, type, sku, image_url, quantity, unit, minimum_stock, price, points,
                    CASE WHEN pph_product_id IS NULL THEN 'inventory' ELSE 'pph' END origin
               FROM {$this->products}
              WHERE active = 1
              ORDER BY name"
        );
    }

    public function saveProduct(array $input, array $files, int $userId): int
    {
        if (!PermissionService::canManageInventory()) {
            throw new InventoryException('No tienes permiso para modificar el inventario.', 403);
        }
        $this->syncFromPph();
        $id = max(0, (int) ($input['id'] ?? 0));
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new InventoryException('El nombre del producto es obligatorio.');
        }
        $type = trim((string) ($input['type'] ?? '')) ?: 'Souvenir';
        if (!$this->categories->contains($type)) {
            throw new InventoryException('Selecciona una categoría guardada o agrega una nueva.');
        }
        $sku = strtoupper(trim((string) ($input['sku'] ?? '')));
        $syncPph = !empty($input['sync_pph']);
        $now = date('Y-m-d H:i:s');
        $uploadedImage = $this->prepareProductImage($files['product_image'] ?? []);
        try {
            $storedFiles = $id === 0 ? $this->prepareEvidenceFiles($files['evidence'] ?? []) : [];
        } catch (Throwable $e) {
            $this->removeProductImageFile($uploadedImage['storage_path'] ?? null);
            throw $e;
        }
        $data = [
            'name' => $name,
            'type' => $type,
            'sku' => $sku !== '' ? $sku : null,
            'description' => trim((string) ($input['description'] ?? '')),
            'unit' => trim((string) ($input['unit'] ?? '')) ?: 'unidad',
            'minimum_stock' => max(0, (int) ($input['minimum_stock'] ?? 0)),
            'price' => $this->nullableDecimal($input['price'] ?? null),
            'points' => $this->nullableInt($input['points'] ?? null),
            'active' => 1,
            'sync_pph' => $syncPph ? 1 : 0,
            'updated_by' => $userId,
            'updated_at' => $now,
        ];
        if ($uploadedImage !== null) {
            $data['image_url'] = $uploadedImage['url'];
        }

        $previousImageUrl = '';
        Database::beginTransaction();
        try {
            if ($id > 0) {
                $product = Database::one("SELECT * FROM {$this->products} WHERE id = ? FOR UPDATE", 'i', [$id]);
                if ($product === []) {
                    throw new InventoryException('El producto no existe.', 404);
                }
                $previousImageUrl = (string) ($product['image_url'] ?? '');
                $this->updateById($this->products, $id, $data);
            } else {
                $initial = max(0, (int) ($input['initial_quantity'] ?? 0));
                $data += [
                    'quantity' => $initial,
                    'pph_product_id' => null,
                    'pph_quantity' => null,
                    'pph_hash' => null,
                    'pph_modified_at' => null,
                    'created_by' => $userId,
                    'created_at' => $now,
                ];
                $id = $this->insert($this->products, $data);
                $movementId = $this->insertMovement($id, null, 'opening', $initial, 0, $initial, 'Saldo inicial del producto.', 'dashboard', $userId);
                $this->attachMovementEvidence($storedFiles, $id, $movementId);
            }
            if ($syncPph) {
                $this->pushProductToPph($id, $userId);
            }
            Database::commit();
        } catch (Throwable $e) {
            Database::rollBack();
            $this->removeStoredFiles($storedFiles);
            $this->removeProductImageFile($uploadedImage['storage_path'] ?? null);
            if ($this->isDuplicateKey($e)) {
                throw new InventoryException('El SKU ya está asignado a otro producto.');
            }
            throw $e;
        }
        if ($uploadedImage !== null && $previousImageUrl !== '' && $previousImageUrl !== $uploadedImage['url']) {
            $this->removeProductImageByUrl($previousImageUrl);
        }
        return $id;
    }

    public function addCategory(string $name): string
    {
        if (!PermissionService::canManageInventory()) {
            throw new InventoryException('No tienes permiso para administrar categorías.', 403);
        }
        return $this->categories->add($name);
    }

    public function syncPphNow(): array
    {
        if (!PermissionService::canManageInventory()) {
            throw new InventoryException('No tienes permiso para sincronizar el inventario.', 403);
        }
        return $this->syncFromPph();
    }

    public function adjustStock(int $productId, int $delta, string $reason, array $files, int $userId): void
    {
        if (!PermissionService::canManageInventory()) {
            throw new InventoryException('No tienes permiso para modificar el inventario.', 403);
        }
        if ($productId <= 0 || $delta === 0) {
            throw new InventoryException('Indica un producto y una cantidad diferente de cero.');
        }
        $reason = trim($reason);
        if ($reason === '') {
            throw new InventoryException('El motivo del ajuste es obligatorio.');
        }
        $this->syncFromPph();
        $storedFiles = $this->prepareEvidenceFiles($files['evidence'] ?? []);

        Database::beginTransaction();
        try {
            $product = $this->lockAndReconcileProduct($productId, $userId);
            $before = (int) $product['quantity'];
            $after = $before + $delta;
            if ($after < 0) {
                throw new InventoryException('No hay existencias suficientes para realizar el ajuste.', 409);
            }
            $this->setProductQuantity($product, $after, $userId);
            $movementId = $this->insertMovement(
                $productId,
                null,
                $delta > 0 ? 'entry' : 'adjustment_out',
                $delta,
                $before,
                $after,
                $reason,
                'dashboard',
                $userId
            );
            $this->attachMovementEvidence($storedFiles, $productId, $movementId);
            Database::commit();
        } catch (Throwable $e) {
            Database::rollBack();
            $this->removeStoredFiles($storedFiles);
            throw $e;
        }
    }

    public function archiveProduct(int $productId, int $userId): void
    {
        if (!PermissionService::canManageInventory()) {
            throw new InventoryException('No tienes permiso para archivar productos.', 403);
        }
        Database::beginTransaction();
        try {
            $product = Database::one("SELECT * FROM {$this->products} WHERE id = ? FOR UPDATE", 'i', [$productId]);
            if ($product === []) {
                throw new InventoryException('El producto no existe.', 404);
            }
            $this->updateById($this->products, $productId, [
                'active' => 0,
                'updated_by' => $userId,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            if (!empty($product['sync_pph']) && (int) ($product['pph_product_id'] ?? 0) > 0) {
                Database::execute(
                    "UPDATE {$this->pphProducts} SET cct_status = 'draft', cct_modified = NOW() WHERE _ID = ?",
                    'i',
                    [(int) $product['pph_product_id']]
                );
            }
            Database::commit();
        } catch (Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    public function createDelivery(array $input, array $files, string $source, ?int $userId): array
    {
        if ($source === 'dashboard' && !PermissionService::canManageInventory()) {
            throw new InventoryException('No tienes permiso para registrar entregas.', 403);
        }
        if (!in_array($source, ['dashboard', 'api'], true)) {
            throw new InventoryException('Origen de entrega inválido.');
        }
        $this->syncFromPph();
        $recipientName = trim((string) ($input['recipient_name'] ?? ''));
        if ($recipientName === '') {
            throw new InventoryException('El nombre del destinatario es obligatorio.');
        }
        $externalId = trim((string) ($input['external_id'] ?? ''));
        if ($source === 'api' && $externalId === '') {
            throw new InventoryException('external_id es obligatorio para entregas por API.');
        }
        if ($externalId !== '') {
            $existing = Database::one(
                "SELECT id, status FROM {$this->deliveries} WHERE source = ? AND external_id = ? LIMIT 1",
                'ss',
                [$source, $externalId]
            );
            if ($existing !== []) {
                return ['id' => (int) $existing['id'], 'duplicate' => true, 'status' => (string) $existing['status']];
            }
        }
        $items = $this->normalizeItems(
            $input['items'] ?? ($input['product_ids'] ?? ($input['products'] ?? []))
        );
        if ($items === []) {
            throw new InventoryException('Agrega al menos un producto a la entrega.');
        }
        $deliveredAt = $this->deliveryDate((string) ($input['delivered_at'] ?? ''));
        $metadata = $input['recipient_metadata'] ?? null;
        if (is_string($metadata) && trim($metadata) !== '') {
            $decoded = json_decode($metadata, true);
            $metadata = json_last_error() === JSON_ERROR_NONE ? $decoded : ['value' => $metadata];
        }
        if ($metadata !== null && !is_array($metadata)) {
            $metadata = ['value' => (string) $metadata];
        }
        $metadataSnapshot = is_array($metadata) ? $metadata : [];
        $propertyLabel = $this->metadataText($metadataSnapshot, [
            'inmueble_direccion', 'property_address', 'inmueble', 'property_name',
        ]);
        $employeeName = $this->metadataText($metadataSnapshot, [
            'funcionario_nombre', 'delivered_by_name', 'employee_name',
        ]);
        $movementReason = 'Entrega a ' . $recipientName;
        if ($propertyLabel !== '') {
            $movementReason .= ' para el inmueble ' . $propertyLabel;
        }
        if ($employeeName !== '') {
            $movementReason .= ', registrada por ' . $employeeName;
        }
        $movementReason .= '.';
        $storedFiles = $this->prepareEvidenceFiles($files['evidence'] ?? []);
        $evidenceUrls = $this->normalizeEvidenceUrls($input['evidence_urls'] ?? ($input['evidence_url'] ?? []));
        if (count($storedFiles) + count($evidenceUrls) > 5) {
            $this->removeStoredFiles($storedFiles);
            throw new InventoryException('Puedes adjuntar máximo cinco evidencias.');
        }

        Database::beginTransaction();
        try {
            $deliveryId = $this->insert($this->deliveries, [
                'delivered_at' => $deliveredAt,
                'recipient_name' => $recipientName,
                'recipient_type' => $this->nullableText($input['recipient_type'] ?? null),
                'recipient_id' => $this->nullableText($input['recipient_id'] ?? null),
                'recipient_source' => $this->nullableText($input['recipient_source'] ?? null),
                'recipient_document' => $this->nullableText($input['recipient_document'] ?? null),
                'recipient_metadata' => $metadata === null ? null : json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'notes' => $this->nullableText($input['notes'] ?? null),
                'status' => 'delivered',
                'source' => $source,
                'external_id' => $externalId !== '' ? $externalId : null,
                'pph_reward_id' => null,
                'created_by' => $userId,
                'cancelled_by' => null,
                'cancelled_at' => null,
                'cancellation_reason' => null,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            foreach ($items as $productId => $quantity) {
                $product = $this->lockAndReconcileProduct($productId, $userId);
                if (!(int) $product['active']) {
                    throw new InventoryException('El producto "' . $product['name'] . '" está archivado.', 409);
                }
                $before = (int) $product['quantity'];
                if ($quantity > $before) {
                    throw new InventoryException(
                        'Existencias insuficientes para "' . $product['name'] . '". Disponibles: ' . $before . '.',
                        409
                    );
                }
                $after = $before - $quantity;
                $this->setProductQuantity($product, $after, $userId);
                $this->insert($this->items, [
                    'delivery_id' => $deliveryId,
                    'product_id' => $productId,
                    'product_name' => $product['name'],
                    'product_type' => $product['type'],
                    'quantity' => $quantity,
                    'unit_price' => $product['price'],
                    'points' => $product['points'],
                    'pph_product_id' => $product['pph_product_id'],
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                $this->insertMovement(
                    $productId,
                    $deliveryId,
                    'delivery',
                    -$quantity,
                    $before,
                    $after,
                    $movementReason,
                    $source,
                    $userId
                );
            }
            foreach ($storedFiles as $file) {
                $this->insert($this->evidence, ['delivery_id' => $deliveryId] + $file + ['created_at' => date('Y-m-d H:i:s')]);
            }
            foreach ($evidenceUrls as $url) {
                $this->insert($this->evidence, [
                    'delivery_id' => $deliveryId,
                    'evidence_type' => 'url',
                    'storage_path' => null,
                    'external_url' => $url,
                    'original_name' => null,
                    'mime_type' => null,
                    'file_size' => null,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
            Database::commit();
            return ['id' => $deliveryId, 'duplicate' => false, 'status' => 'delivered'];
        } catch (Throwable $e) {
            Database::rollBack();
            $this->removeStoredFiles($storedFiles);
            if ($this->isDuplicateKey($e) && $externalId !== '') {
                $existing = Database::one(
                    "SELECT id, status FROM {$this->deliveries} WHERE source = ? AND external_id = ? LIMIT 1",
                    'ss',
                    [$source, $externalId]
                );
                if ($existing !== []) {
                    return ['id' => (int) $existing['id'], 'duplicate' => true, 'status' => (string) $existing['status']];
                }
            }
            throw $e;
        }
    }

    public function cancelDelivery(int $deliveryId, string $reason, int $userId): void
    {
        if (!PermissionService::canManageInventory()) {
            throw new InventoryException('No tienes permiso para anular entregas.', 403);
        }
        $reason = trim($reason);
        if ($reason === '') {
            throw new InventoryException('El motivo de anulación es obligatorio.');
        }
        Database::beginTransaction();
        try {
            $delivery = Database::one("SELECT * FROM {$this->deliveries} WHERE id = ? FOR UPDATE", 'i', [$deliveryId]);
            if ($delivery === [] || (string) $delivery['status'] !== 'delivered') {
                throw new InventoryException('La entrega no existe o ya fue anulada.', 409);
            }
            if ((string) $delivery['source'] === 'pph') {
                throw new InventoryException('Las entregas históricas importadas desde PPH no se pueden anular desde este módulo.', 409);
            }
            $items = Database::rows("SELECT * FROM {$this->items} WHERE delivery_id = ? ORDER BY id FOR UPDATE", 'i', [$deliveryId]);
            foreach ($items as $item) {
                $productId = (int) ($item['product_id'] ?? 0);
                if ($productId <= 0) {
                    continue;
                }
                $product = $this->lockAndReconcileProduct($productId, $userId);
                $before = (int) $product['quantity'];
                $quantity = (int) $item['quantity'];
                $after = $before + $quantity;
                $this->setProductQuantity($product, $after, $userId);
                $this->insertMovement(
                    $productId,
                    $deliveryId,
                    'reversal',
                    $quantity,
                    $before,
                    $after,
                    'Anulación de entrega: ' . $reason,
                    'dashboard',
                    $userId
                );
            }
            $this->updateById($this->deliveries, $deliveryId, [
                'status' => 'cancelled',
                'cancelled_by' => $userId,
                'cancelled_at' => date('Y-m-d H:i:s'),
                'cancellation_reason' => $reason,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            Database::commit();
        } catch (Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    public function deliveryEvidence(int $deliveryId): array
    {
        return Database::rows("SELECT * FROM {$this->evidence} WHERE delivery_id = ? ORDER BY id", 'i', [$deliveryId]);
    }

    public function streamEvidence(int $evidenceId): never
    {
        $row = Database::one("SELECT * FROM {$this->evidence} WHERE id = ? LIMIT 1", 'i', [$evidenceId]);
        if ($row === [] || (string) $row['evidence_type'] !== 'file') {
            http_response_code(404);
            exit('Evidencia no encontrada.');
        }
        $storageRoot = realpath(dirname(__DIR__) . '/storage/inventory-evidence');
        $path = realpath(dirname(__DIR__) . '/' . ltrim((string) $row['storage_path'], '/\\'));
        if ($storageRoot === false || $path === false || !str_starts_with($path, $storageRoot) || !is_file($path)) {
            http_response_code(404);
            exit('Evidencia no encontrada.');
        }
        header('Content-Type: ' . ((string) ($row['mime_type'] ?? '') ?: 'application/octet-stream'));
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: inline; filename="' . addslashes((string) ($row['original_name'] ?? basename($path))) . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    private function importPphReward(array $reward): bool
    {
        $rewardId = (int) ($reward['_ID'] ?? 0);
        if ($rewardId <= 0) {
            return false;
        }
        $timestamp = (int) ($reward['fecha'] ?? 0);
        $deliveredAt = $timestamp > 0
            ? date('Y-m-d H:i:s', $timestamp)
            : ((string) ($reward['cct_created'] ?? '') ?: date('Y-m-d H:i:s'));
        $deliveryId = $this->insert($this->deliveries, [
            'delivered_at' => $deliveredAt,
            'recipient_name' => trim((string) ($reward['nombre'] ?? '')) ?: 'Destinatario PPH',
            'recipient_type' => 'Club PPH',
            'recipient_id' => $this->nullableText($reward['id_pph'] ?? null),
            'recipient_source' => 'pph',
            'recipient_document' => $this->nullableText($reward['tarjeta_bienvenida'] ?? null),
            'recipient_metadata' => null,
            'notes' => 'Entrega importada desde recompensas PPH.',
            'status' => 'delivered',
            'source' => 'pph',
            'external_id' => 'pph-reward-' . $rewardId,
            'pph_reward_id' => $rewardId,
            'created_by' => $this->nullableInt($reward['cct_author_id'] ?? null),
            'cancelled_by' => null,
            'cancelled_at' => null,
            'cancellation_reason' => null,
            'created_at' => (string) ($reward['cct_created'] ?? '') ?: date('Y-m-d H:i:s'),
            'updated_at' => (string) ($reward['cct_created'] ?? '') ?: date('Y-m-d H:i:s'),
        ]);
        $pphProductId = (int) ($reward['id_producto'] ?? 0);
        $product = $pphProductId > 0
            ? Database::one("SELECT id, name, type, price, points FROM {$this->products} WHERE pph_product_id = ? LIMIT 1", 'i', [$pphProductId])
            : [];
        $this->insert($this->items, [
            'delivery_id' => $deliveryId,
            'product_id' => $product['id'] ?? null,
            'product_name' => trim((string) ($reward['producto'] ?? '')) ?: (string) ($product['name'] ?? 'Producto PPH'),
            'product_type' => $product['type'] ?? 'PPH',
            'quantity' => max(1, (int) ($reward['cantidad'] ?? 1)),
            'unit_price' => $product['price'] ?? null,
            'points' => $this->nullableInt($reward['puntos'] ?? ($product['points'] ?? null)),
            'pph_product_id' => $pphProductId > 0 ? $pphProductId : null,
            'created_at' => (string) ($reward['cct_created'] ?? '') ?: date('Y-m-d H:i:s'),
        ]);
        return true;
    }

    private function lockAndReconcileProduct(int $productId, ?int $userId): array
    {
        $product = Database::one("SELECT * FROM {$this->products} WHERE id = ? FOR UPDATE", 'i', [$productId]);
        if ($product === []) {
            throw new InventoryException('Uno de los productos seleccionados no existe.', 404);
        }
        $pphId = (int) ($product['pph_product_id'] ?? 0);
        if (!empty($product['sync_pph']) && $pphId > 0 && Database::tableExists($this->pphProducts)) {
            $pph = Database::one("SELECT * FROM {$this->pphProducts} WHERE _ID = ? FOR UPDATE", 'i', [$pphId]);
            if ($pph !== []) {
                $pphQuantity = max(0, (int) ($pph['cantidad'] ?? 0));
                $before = (int) $product['quantity'];
                if ($pphQuantity !== $before) {
                    $this->updateById($this->products, $productId, [
                        'quantity' => $pphQuantity,
                        'pph_quantity' => $pphQuantity,
                        'pph_hash' => $this->pphHash($pph),
                        'pph_modified_at' => $this->nullableDate($pph['cct_modified'] ?? null),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                    $this->insertMovement(
                        $productId,
                        null,
                        'pph_reconciliation',
                        $pphQuantity - $before,
                        $before,
                        $pphQuantity,
                        'Conciliación previa a la operación.',
                        'pph',
                        $userId
                    );
                    $product['quantity'] = $pphQuantity;
                }
            }
        }
        return $product;
    }

    private function setProductQuantity(array $product, int $quantity, ?int $userId): void
    {
        $productId = (int) $product['id'];
        $updates = [
            'quantity' => $quantity,
            'updated_by' => $userId,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $pphId = (int) ($product['pph_product_id'] ?? 0);
        if (!empty($product['sync_pph']) && $pphId > 0) {
            Database::execute(
                "UPDATE {$this->pphProducts}
                    SET cantidad = ?, cct_modified = NOW()
                  WHERE _ID = ?",
                'ii',
                [$quantity, $pphId]
            );
            $source = Database::one("SELECT * FROM {$this->pphProducts} WHERE _ID = ? LIMIT 1", 'i', [$pphId]);
            $updates['pph_quantity'] = $quantity;
            $updates['pph_hash'] = $source !== [] ? $this->pphHash($source) : null;
            $updates['pph_modified_at'] = date('Y-m-d H:i:s');
        }
        $this->updateById($this->products, $productId, $updates);
    }

    private function pushProductToPph(int $productId, int $userId): void
    {
        if (!Database::tableExists($this->pphProducts)) {
            throw new InventoryException('La tabla de productos PPH no está disponible.', 500);
        }
        $product = Database::one("SELECT * FROM {$this->products} WHERE id = ? FOR UPDATE", 'i', [$productId]);
        if ($product === []) {
            throw new InventoryException('El producto no existe.', 404);
        }
        $pphId = (int) ($product['pph_product_id'] ?? 0);
        $sourceData = [
            'cct_status' => (int) $product['active'] === 1 ? 'publish' : 'draft',
            'cct_author_id' => $userId,
            'cct_modified' => date('Y-m-d H:i:s'),
            'cantidad' => (int) $product['quantity'],
            'fecha' => time(),
            'precio' => $product['price'],
            'valor_punto' => $product['points'],
            'titulo' => $product['name'],
            'img' => $product['image_url'],
        ];
        if ($pphId > 0) {
            $sets = $sourceData;
            unset($sets['cct_author_id'], $sets['fecha']);
            $this->updatePph($pphId, $sets);
        } else {
            $sourceData['cct_created'] = date('Y-m-d H:i:s');
            $pphId = $this->insert($this->pphProducts, $sourceData);
        }
        $source = Database::one("SELECT * FROM {$this->pphProducts} WHERE _ID = ? LIMIT 1", 'i', [$pphId]);
        $this->updateById($this->products, $productId, [
            'sync_pph' => 1,
            'pph_product_id' => $pphId,
            'pph_quantity' => (int) $product['quantity'],
            'pph_hash' => $this->pphHash($source),
            'pph_modified_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function updatePph(int $id, array $data): void
    {
        $sets = [];
        foreach (array_keys($data) as $column) {
            $sets[] = "`{$column}` = ?";
        }
        $params = array_values($data);
        $params[] = $id;
        Database::execute(
            "UPDATE {$this->pphProducts} SET " . implode(', ', $sets) . ' WHERE _ID = ? LIMIT 1',
            str_repeat('s', count($data)) . 'i',
            $params
        );
    }

    private function insertMovement(
        int $productId,
        ?int $deliveryId,
        string $type,
        int $delta,
        int $before,
        int $after,
        string $reason,
        string $source,
        ?int $userId
    ): int {
        return $this->insert($this->movements, [
            'product_id' => $productId,
            'delivery_id' => $deliveryId,
            'movement_type' => $type,
            'quantity_delta' => $delta,
            'stock_before' => $before,
            'stock_after' => $after,
            'reason' => $reason,
            'source' => $source,
            'created_by' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function attachMovementEvidence(array $storedFiles, int $productId, int $movementId): void
    {
        foreach ($storedFiles as $file) {
            $this->insert($this->evidence, [
                'delivery_id' => null,
                'movement_id' => $movementId,
                'product_id' => $productId,
            ] + $file + ['created_at' => date('Y-m-d H:i:s')]);
        }
    }

    private function normalizeItems(mixed $raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $raw = $decoded;
            } elseif (preg_match('/^\d+$/', trim($raw))) {
                $raw = [(int) trim($raw)];
            } else {
                $raw = [];
            }
        }
        if (!is_array($raw)) {
            return [];
        }
        $isList = array_is_list($raw);
        $items = [];
        foreach ($raw as $key => $row) {
            if (is_array($row)) {
                $productId = (int) ($row['product_id'] ?? $row['id'] ?? 0);
                $quantity = (int) ($row['quantity'] ?? 1);
            } elseif ($isList) {
                $productId = (int) $row;
                $quantity = 1;
            } else {
                $productId = (int) $key;
                $quantity = (int) $row;
            }
            if ($productId <= 0 || $quantity <= 0) {
                continue;
            }
            $items[$productId] = ($items[$productId] ?? 0) + $quantity;
        }
        return $items;
    }

    private function metadataText(array $metadata, array $keys): string
    {
        foreach ($keys as $key) {
            $value = $metadata[$key] ?? null;
            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }
        return '';
    }

    private function deliveryDate(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            throw new InventoryException('La fecha y hora de entrega son obligatorias.');
        }
        try {
            $timezone = new DateTimeZone((string) app_config('app.timezone', 'America/Bogota'));
            return (new DateTimeImmutable($value, $timezone))->setTimezone($timezone)->format('Y-m-d H:i:s');
        } catch (Throwable) {
            throw new InventoryException('La fecha y hora de entrega no tienen un formato válido.');
        }
    }

    private function prepareProductImage(array $file): ?array
    {
        if ($file === [] || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ((int) ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new InventoryException('La imagen del producto no pudo cargarse correctamente.');
        }
        if ((int) ($file['size'] ?? 0) <= 0 || (int) ($file['size'] ?? 0) > 5 * 1024 * 1024) {
            throw new InventoryException('La imagen del producto debe pesar máximo 5 MB.');
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $mime = is_file($tmp) ? ((new \finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: '') : '';
        if (!isset($allowed[$mime]) || @getimagesize($tmp) === false) {
            throw new InventoryException('La imagen del producto debe ser JPG, PNG o WebP.');
        }

        $relativeDirectory = '/public/uploads/inventory-products/' . date('Y/m');
        $directory = dirname(__DIR__) . str_replace('/', DIRECTORY_SEPARATOR, $relativeDirectory);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new InventoryException('No fue posible preparar el almacenamiento de imágenes.', 500);
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
        $destination = $directory . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file($tmp, $destination)) {
            throw new InventoryException('No fue posible guardar la imagen del producto.', 500);
        }

        $relativePath = ltrim($relativeDirectory . '/' . $filename, '/');
        return [
            'storage_path' => $relativePath,
            'url' => $this->publicUploadUrl('/' . $relativePath),
        ];
    }

    private function publicUploadUrl(string $path): string
    {
        $public = rtrim((string) app_config('app.public_url', ''), '/');
        if ($public !== '') {
            return $public . $path;
        }

        $host = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', (string) ($_SERVER['HTTP_HOST'] ?? '127.0.0.1:8080'));
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return $scheme . '://' . $host . clean_url_base() . $path;
    }

    private function removeProductImageByUrl(string $url): void
    {
        $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
        $marker = '/public/uploads/inventory-products/';
        $position = strpos($path, $marker);
        if ($position === false) {
            return;
        }
        $this->removeProductImageFile(ltrim(substr($path, $position), '/'));
    }

    private function removeProductImageFile(?string $storagePath): void
    {
        if ($storagePath === null || $storagePath === '') {
            return;
        }
        $root = realpath(dirname(__DIR__) . '/public/uploads/inventory-products');
        $path = realpath(dirname(__DIR__) . '/' . ltrim($storagePath, '/\\'));
        if (
            $root !== false
            && $path !== false
            && str_starts_with($path, $root . DIRECTORY_SEPARATOR)
            && is_file($path)
        ) {
            @unlink($path);
        }
    }

    private function prepareEvidenceFiles(array $fileInput): array
    {
        if ($fileInput === [] || empty($fileInput['name'])) {
            return [];
        }
        $files = [];
        if (is_array($fileInput['name'])) {
            foreach (array_keys($fileInput['name']) as $index) {
                $files[] = [
                    'name' => $fileInput['name'][$index] ?? '',
                    'tmp_name' => $fileInput['tmp_name'][$index] ?? '',
                    'error' => $fileInput['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                    'size' => $fileInput['size'][$index] ?? 0,
                ];
            }
        } else {
            $files[] = $fileInput;
        }
        $files = array_values(array_filter($files, static fn (array $file): bool => (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE));
        if (count($files) > 5) {
            throw new InventoryException('Puedes adjuntar máximo cinco evidencias.');
        }
        $stored = [];
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $base = dirname(__DIR__) . '/storage/inventory-evidence/' . date('Y/m');
        if (!is_dir($base) && !mkdir($base, 0775, true) && !is_dir($base)) {
            throw new InventoryException('No fue posible preparar el almacenamiento de evidencias.', 500);
        }
        foreach ($files as $file) {
            if ((int) ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                $this->removeStoredFiles($stored);
                throw new InventoryException('Una evidencia no pudo cargarse correctamente.');
            }
            if ((int) ($file['size'] ?? 0) > 8 * 1024 * 1024) {
                $this->removeStoredFiles($stored);
                throw new InventoryException('Cada evidencia debe pesar máximo 8 MB.');
            }
            $tmp = (string) ($file['tmp_name'] ?? '');
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: '';
            if (!isset($allowed[$mime])) {
                $this->removeStoredFiles($stored);
                throw new InventoryException('Las evidencias deben ser imágenes JPG, PNG o WebP.');
            }
            $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
            $destination = $base . '/' . $filename;
            if (!move_uploaded_file($tmp, $destination)) {
                $this->removeStoredFiles($stored);
                throw new InventoryException('No fue posible guardar una evidencia.', 500);
            }
            $stored[] = [
                'evidence_type' => 'file',
                'storage_path' => str_replace('\\', '/', substr($destination, strlen(dirname(__DIR__)) + 1)),
                'external_url' => null,
                'original_name' => basename((string) ($file['name'] ?? 'evidencia.' . $allowed[$mime])),
                'mime_type' => $mime,
                'file_size' => (int) ($file['size'] ?? 0),
            ];
        }
        return $stored;
    }

    private function normalizeEvidenceUrls(mixed $raw): array
    {
        if (is_string($raw)) {
            $raw = trim($raw) === '' ? [] : [$raw];
        }
        if (!is_array($raw)) {
            return [];
        }
        $urls = [];
        foreach ($raw as $url) {
            $url = trim((string) $url);
            if ($url === '') {
                continue;
            }
            if (!filter_var($url, FILTER_VALIDATE_URL) || !str_starts_with(strtolower($url), 'https://')) {
                throw new InventoryException('Las URLs de evidencia deben usar HTTPS.');
            }
            $urls[] = $url;
        }
        return array_slice(array_values(array_unique($urls)), 0, 5);
    }

    private function removeStoredFiles(array $stored): void
    {
        foreach ($stored as $file) {
            $path = dirname(__DIR__) . '/' . ltrim((string) ($file['storage_path'] ?? ''), '/\\');
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    private function pphHash(array $source): string
    {
        return hash('sha256', json_encode([
            trim((string) ($source['titulo'] ?? '')),
            max(0, (int) ($source['cantidad'] ?? 0)),
            trim((string) ($source['precio'] ?? '')),
            trim((string) ($source['valor_punto'] ?? '')),
            trim((string) ($source['img'] ?? '')),
            strtolower(trim((string) ($source['cct_status'] ?? 'publish'))),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function pphActive(array $source): bool
    {
        return !in_array(strtolower(trim((string) ($source['cct_status'] ?? 'publish'))), ['draft', 'trash', 'inactive'], true);
    }

    private function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $sql = "INSERT INTO {$table} (`" . implode('`,`', $columns) . '`) VALUES (' . implode(',', array_fill(0, count($columns), '?')) . ')';
        if (!Database::execute($sql, str_repeat('s', count($columns)), array_values($data))) {
            throw new InventoryException('No fue posible guardar el registro de inventario: ' . Database::connection()->error, 500);
        }
        return (int) Database::connection()->insert_id;
    }

    private function updateById(string $table, int $id, array $data): void
    {
        $sets = array_map(static fn (string $column): string => "`{$column}` = ?", array_keys($data));
        $params = array_values($data);
        $params[] = $id;
        if (!Database::execute(
            "UPDATE {$table} SET " . implode(', ', $sets) . ' WHERE id = ? LIMIT 1',
            str_repeat('s', count($data)) . 'i',
            $params
        )) {
            throw new InventoryException('No fue posible actualizar el registro de inventario.', 500);
        }
    }

    private function nullableText(mixed $value): ?string
    {
        $text = trim((string) $value);
        return $text === '' ? null : $text;
    }

    private function filterDate(mixed $value): string
    {
        $text = trim((string) $value);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $text)) {
            return '';
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $text);
        return $date instanceof DateTimeImmutable && $date->format('Y-m-d') === $text ? $text : '';
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null || trim((string) $value) === '' ? null : (int) $value;
    }

    private function nullableDecimal(mixed $value): ?string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }
        $normalized = str_replace(',', '.', preg_replace('/[^\d,.-]/', '', $text) ?? '');
        return is_numeric($normalized) ? number_format((float) $normalized, 2, '.', '') : null;
    }

    private function nullableDate(mixed $value): ?string
    {
        $text = trim((string) $value);
        return $text === '' || $text === '0000-00-00 00:00:00' ? null : $text;
    }

    private function isDuplicateKey(Throwable $e): bool
    {
        return str_contains(strtolower($e->getMessage()), 'duplicate')
            || (property_exists($e, 'getCode') && (int) $e->getCode() === 1062);
    }
}
