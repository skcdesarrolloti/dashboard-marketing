<?php

use App\Auth;

$inventory = $data;
$products = $inventory['products'] ?? [];
$activeProducts = $inventory['activeProducts'] ?? array_values(array_filter(
    $products,
    static fn (array $product): bool => (int) ($product['active'] ?? 0) === 1
));
$hasDeliverableProducts = array_filter(
    $activeProducts,
    static fn (array $product): bool => (int) ($product['quantity'] ?? 0) > 0
) !== [];
$summary = $inventory['summary'] ?? [];
$deliveries = $inventory['recentDeliveries'] ?? [];
$movements = $inventory['recentMovements'] ?? [];
$categories = $inventory['categories'] ?? ['PPH', 'Souvenir'];
$filterProducts = $inventory['filterProducts'] ?? $products;
$deliverySources = $inventory['deliverySources'] ?? [];
$movementSources = $inventory['movementSources'] ?? [];
$movementTypeOptions = $inventory['movementTypeOptions'] ?? [];
$movementTypeLabels = [
    'opening' => 'Saldo inicial',
    'entry' => 'Entrada',
    'adjustment_out' => 'Salida por ajuste',
    'delivery' => 'Entrega',
    'delivery_cancelled' => 'Anulación de entrega',
    'pph_reconciliation' => 'Conciliación PPH',
];
$allowedTabs = ['stock', 'history', 'movements'];
$tab = in_array($tab, $allowedTabs, true) ? $tab : 'stock';
$sync = $inventory['sync'] ?? [];
$apiBase = rtrim(clean_url_base(), '/') . '/api/inventory';
?>

<section class="inventory-hero">
  <div>
    <p class="inventory-kicker">Control operativo</p>
    <h1>Inventario de souvenirs</h1>
    <p>Administra existencias, entregas y productos sincronizados con Club PPH desde un solo lugar.</p>
  </div>
  <div class="inventory-sync" aria-label="Resultado de sincronización PPH">
    <span class="inventory-sync-dot" aria-hidden="true"></span>
    <div class="inventory-sync-copy">
      <strong>PPH al día</strong>
      <small>
        <?= e((int) ($sync['products_created'] ?? 0)) ?> nuevos ·
        <?= e((int) ($sync['products_updated'] ?? 0)) ?> actualizados ·
        <?= e((int) ($sync['deliveries_imported'] ?? 0)) ?> entregas importadas
      </small>
      <small class="inventory-sync-help">Se revisa automáticamente al abrir el inventario y antes de cada modificación. Los ceros indican que no había cambios pendientes.</small>
    </div>
    <?php if ($canManage) : ?>
      <form method="post" class="inventory-sync-action">
        <input type="hidden" name="action" value="inventory_sync_pph">
        <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="return_tab" value="<?= e($tab) ?>">
        <button type="submit" class="button ghost compact">Sincronizar ahora</button>
      </form>
    <?php endif; ?>
  </div>
</section>

<?php if ($error) : ?>
  <div class="inventory-alert is-error" role="alert"><strong>No se pudo completar la operación.</strong><span><?= e($error) ?></span></div>
<?php elseif ($success ?? null) : ?>
  <div class="inventory-alert is-success" role="status"><strong>PPH sincronizado.</strong><span><?= e($success) ?></span></div>
<?php elseif (isset($_GET['saved'])) : ?>
  <div class="inventory-alert is-success" role="status"><strong>Operación guardada.</strong><span>Las existencias y el historial quedaron actualizados.</span></div>
<?php endif; ?>

<section class="inventory-metrics" aria-label="Resumen de inventario">
  <article><span>Unidades disponibles</span><strong><?= e((int) ($summary['units_available'] ?? 0)) ?></strong><small>En productos activos</small></article>
  <article><span>Productos activos</span><strong><?= e((int) ($summary['products_active'] ?? 0)) ?></strong><small>de <?= e((int) ($summary['products_total'] ?? 0)) ?> registrados</small></article>
  <article class="is-warning"><span>Stock bajo</span><strong><?= e((int) ($summary['products_low'] ?? 0)) ?></strong><small>Requieren seguimiento</small></article>
  <article class="is-danger"><span>Agotados</span><strong><?= e((int) ($summary['products_out'] ?? 0)) ?></strong><small>Sin unidades disponibles</small></article>
</section>

<div class="inventory-navigation">
  <nav class="inventory-tabs" aria-label="Secciones del inventario">
    <?php foreach ([
      'stock' => 'Existencias',
      'history' => 'Historial',
      'movements' => 'Movimientos',
    ] as $tabKey => $tabLabel) : ?>
      <a href="<?= e(url_page('inventario', ['tab' => $tabKey])) ?>"
         class="<?= $tab === $tabKey ? 'active' : '' ?>"
         <?= $tab === $tabKey ? 'aria-current="page"' : '' ?>><?= e($tabLabel) ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="inventory-quick-actions">
    <button type="button" class="button ghost" data-open-api-modal>Integración API</button>
    <?php if ($canManage) : ?>
      <button type="button" data-open-delivery-modal <?= !$hasDeliverableProducts ? 'disabled' : '' ?>>Nueva entrega</button>
    <?php endif; ?>
  </div>
</div>

<?php if ($tab === 'stock') : ?>
  <section class="inventory-panel">
    <div class="inventory-panel-head">
      <div><p>Catálogo</p><h2>Existencias actuales</h2></div>
      <div class="inventory-panel-actions">
        <span><?= e(count($products)) ?> productos</span>
        <?php if ($canManage) : ?>
          <button type="button" data-open-product-modal>Agregar producto</button>
        <?php endif; ?>
      </div>
    </div>

    <form method="get" class="inventory-filters">
      <input type="hidden" name="page" value="inventario">
      <input type="hidden" name="tab" value="stock">
      <div>
        <label for="inventory-q">Buscar producto</label>
        <input id="inventory-q" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Nombre, tipo o SKU">
      </div>
      <div>
        <label for="inventory-status">Estado</label>
        <select id="inventory-status" name="status">
          <option value="">Todos</option>
          <option value="active" <?= ($filters['status'] ?? '') === 'active' ? 'selected' : '' ?>>Activos</option>
          <option value="low" <?= ($filters['status'] ?? '') === 'low' ? 'selected' : '' ?>>Stock bajo</option>
          <option value="out" <?= ($filters['status'] ?? '') === 'out' ? 'selected' : '' ?>>Agotados</option>
          <option value="archived" <?= ($filters['status'] ?? '') === 'archived' ? 'selected' : '' ?>>Archivados</option>
        </select>
      </div>
      <div class="inventory-filter-actions">
        <button type="submit">Aplicar filtros</button>
        <a class="button ghost" href="<?= e(url_page('inventario', ['tab' => 'stock'])) ?>">Limpiar</a>
      </div>
    </form>

    <div class="inventory-product-grid">
      <?php foreach ($products as $product) :
        $quantity = (int) ($product['quantity'] ?? 0);
        $minimum = (int) ($product['minimum_stock'] ?? 0);
        $isArchived = !(int) ($product['active'] ?? 0);
        $stockClass = $quantity === 0 ? 'out' : ($minimum > 0 && $quantity <= $minimum ? 'low' : 'ok');
        $productEditorData = [
          'id' => (int) ($product['id'] ?? 0),
          'name' => (string) ($product['name'] ?? ''),
          'type' => (string) ($product['type'] ?? ''),
          'sku' => (string) ($product['sku'] ?? ''),
          'unit' => (string) ($product['unit'] ?? ''),
          'minimum_stock' => (int) ($product['minimum_stock'] ?? 0),
          'price' => $product['price'] ?? '',
          'points' => $product['points'] ?? '',
          'image_url' => (string) ($product['image_url'] ?? ''),
          'description' => (string) ($product['description'] ?? ''),
          'sync_pph' => !empty($product['sync_pph']),
        ];
      ?>
        <article class="inventory-product-card <?= $isArchived ? 'is-archived' : '' ?>">
          <div class="inventory-product-image">
            <?php if (!empty($product['image_url'])) : ?>
              <img src="<?= e($product['image_url']) ?>" alt="<?= e('Producto ' . $product['name']) ?>" loading="lazy" width="112" height="112">
            <?php else : ?>
              <span aria-hidden="true"><?= e(strtoupper(substr((string) $product['name'], 0, 2))) ?></span>
            <?php endif; ?>
          </div>
          <div class="inventory-product-copy">
            <div class="inventory-product-title">
              <div><span><?= e($product['type']) ?></span><h3><?= e($product['name']) ?></h3></div>
              <span class="inventory-stock-badge is-<?= e($stockClass) ?>">
                <?= $isArchived ? 'Archivado' : ($quantity === 0 ? 'Agotado' : ($stockClass === 'low' ? 'Stock bajo' : 'Disponible')) ?>
              </span>
            </div>
            <div class="inventory-product-meta">
              <span><small>Existencias</small><strong><?= e($quantity) ?> <?= e($product['unit']) ?></strong></span>
              <span><small>SKU</small><strong><?= e($product['sku'] ?: 'Sin SKU') ?></strong></span>
              <span><small>Origen</small><strong><?= !empty($product['pph_product_id']) ? 'PPH sincronizado' : 'Solo inventario' ?></strong></span>
              <span><small>Valor</small><strong><?= $product['price'] !== null ? '$' . e(number_format((float) $product['price'], 0, ',', '.')) : 'Sin precio' ?></strong></span>
            </div>
            <?php if ($canManage && !$isArchived) : ?>
              <div class="inventory-product-actions">
                <button
                  type="button"
                  class="button ghost compact"
                  data-edit-product
                  data-product="<?= e(json_encode($productEditorData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"
                >Editar</button>
                <details class="inventory-adjust">
                  <summary>Ajustar cantidad</summary>
                  <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="inventory_adjust_stock">
                    <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="return_tab" value="stock">
                    <input type="hidden" name="product_id" value="<?= e((int) $product['id']) ?>">
                    <label>Cantidad con signo
                      <input name="delta" type="number" required placeholder="+10 o -2">
                    </label>
                    <label>Motivo
                      <input name="reason" required maxlength="500" placeholder="Compra, corrección, daño...">
                    </label>
                    <label>Evidencia opcional
                      <input name="evidence[]" type="file" accept="image/jpeg,image/png,image/webp" multiple data-evidence-input>
                    </label>
                    <div data-evidence-preview aria-live="polite"></div>
                    <button type="submit">Guardar ajuste</button>
                  </form>
                </details>
                <form method="post" onsubmit="return confirm('¿Archivar este producto? Se conservará todo su historial.');">
                  <input type="hidden" name="action" value="inventory_archive_product">
                  <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="return_tab" value="stock">
                  <input type="hidden" name="product_id" value="<?= e((int) $product['id']) ?>">
                  <button type="submit" class="button ghost danger compact">Archivar</button>
                </form>
              </div>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
      <?php if ($products === []) : ?>
        <div class="inventory-empty"><strong>No encontramos productos.</strong><span>Cambia los filtros o agrega el primer producto del inventario.</span></div>
      <?php endif; ?>
    </div>

    <?php if ($canManage) : ?>
      <div class="inventory-modal" data-product-modal hidden>
        <button class="inventory-modal-backdrop" type="button" data-close-product-modal tabindex="-1" aria-label="Cerrar ventana"></button>
        <section class="inventory-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="inventory-product-modal-title">
          <header class="inventory-modal-head">
            <div>
              <p data-product-modal-kicker>Nuevo registro</p>
              <h2 id="inventory-product-modal-title" data-product-modal-title>Agregar producto</h2>
              <span data-product-modal-description>Completa los datos y las existencias iniciales.</span>
            </div>
            <button class="inventory-modal-close" type="button" data-close-product-modal aria-label="Cerrar ventana"></button>
          </header>
          <form method="post" enctype="multipart/form-data" class="inventory-product-form" data-product-form>
            <input type="hidden" name="action" value="inventory_save_product">
            <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="return_tab" value="stock">
            <input type="hidden" name="id" value="0">
            <div class="inventory-field-wide inventory-product-name-field">
              <label for="product-name" class="inventory-required-label">Nombre <span aria-hidden="true">*</span></label>
              <input id="product-name" name="name" required autocomplete="off">
            </div>
            <div class="inventory-category-field">
              <label for="product-type">Tipo o categoría</label>
              <select id="product-type" name="type" required data-category-select>
                <option value="">Selecciona una categoría</option>
                <?php foreach ($categories as $category) : ?>
                  <option value="<?= e($category) ?>" <?= $category === 'Souvenir' ? 'selected' : '' ?>><?= e($category) ?></option>
                <?php endforeach; ?>
                <option value="__add_category__" data-add-category-option>+ Agregar nueva categoría</option>
              </select>
              <div class="inventory-category-create" data-category-create hidden>
                <label for="product-new-category">Nueva categoría</label>
                <div>
                  <input id="product-new-category" type="text" maxlength="60" data-new-category autocomplete="off" placeholder="Ej. Material promocional">
                  <button type="button" data-save-category>Guardar</button>
                  <button type="button" class="button ghost" data-cancel-category>Cancelar</button>
                </div>
              </div>
              <small class="inventory-category-message" data-category-message aria-live="polite"></small>
            </div>
            <div>
              <label for="product-sku">SKU</label>
              <input id="product-sku" name="sku">
            </div>
            <div>
              <label for="product-unit">Unidad</label>
              <input id="product-unit" name="unit" value="unidad">
            </div>
            <div data-create-only>
              <label for="product-initial">Cantidad inicial</label>
              <input id="product-initial" name="initial_quantity" type="number" min="0" value="0">
            </div>
            <div>
              <label for="product-minimum">Alerta de stock mínimo</label>
              <input id="product-minimum" name="minimum_stock" type="number" min="0" value="0">
            </div>
            <div>
              <label for="product-price">Precio</label>
              <input id="product-price" name="price" inputmode="decimal">
            </div>
            <div>
              <label for="product-points">Puntos PPH</label>
              <input id="product-points" name="points" type="number" min="0">
            </div>
            <div class="inventory-field-wide inventory-product-image-field">
              <label for="product-image">Imagen del producto</label>
              <div class="inventory-product-image-control">
                <label class="inventory-image-picker">
                  <input id="product-image" name="product_image" type="file" accept="image/jpeg,image/png,image/webp" data-product-image-input>
                  <span><strong>Seleccionar imagen</strong><small>JPG, PNG o WebP; máximo 5 MB.</small></span>
                </label>
                <div class="inventory-product-image-preview" data-product-image-preview hidden>
                  <img src="" alt="" width="96" height="96" data-product-image-preview-img>
                  <div>
                    <strong data-product-image-preview-title>Vista previa</strong>
                    <small data-product-image-preview-name></small>
                    <button type="button" class="button ghost compact" data-clear-product-image hidden>Quitar selección</button>
                  </div>
                </div>
              </div>
              <small class="inventory-product-image-message" data-product-image-message aria-live="polite"></small>
            </div>
            <div class="inventory-field-wide">
              <label for="product-description">Descripción</label>
              <textarea id="product-description" name="description" rows="3"></textarea>
            </div>
            <div class="inventory-field-wide" data-create-only>
              <label class="inventory-upload">
                <input name="evidence[]" type="file" accept="image/jpeg,image/png,image/webp" multiple data-evidence-input>
                <span><strong>Evidencia del ingreso inicial</strong><small>Opcional. Hasta 5 fotografías JPG, PNG o WebP; máximo 8 MB cada una.</small></span>
              </label>
              <div data-evidence-preview aria-live="polite"></div>
            </div>
            <label class="inventory-check inventory-field-wide">
              <input type="checkbox" name="sync_pph" value="1">
              <span><strong>Publicar y sincronizar con PPH</strong><small>Nombre, imagen, precio, puntos y cantidad se mantendrán en ambos inventarios.</small></span>
            </label>
            <div class="inventory-modal-actions inventory-field-wide">
              <button class="button ghost" type="button" data-close-product-modal>Cancelar</button>
              <button type="submit" data-product-submit>Crear producto</button>
            </div>
          </form>
        </section>
      </div>
    <?php endif; ?>
  </section>
<?php endif; ?>

<?php if ($tab === 'history') : ?>
  <section class="inventory-panel">
    <div class="inventory-panel-head">
      <div><p>Trazabilidad</p><h2>Historial de entregas</h2></div>
      <span><?= e(count($deliveries)) ?> resultados</span>
    </div>
    <form method="get" class="inventory-record-filters">
      <input type="hidden" name="page" value="inventario">
      <input type="hidden" name="tab" value="history">
      <div class="inventory-filter-search">
        <label for="history-search">Buscar</label>
        <input id="history-search" name="history_q" value="<?= e($filters['history_q'] ?? '') ?>" placeholder="Persona, inmueble, funcionario o producto">
      </div>
      <div>
        <label for="history-from">Desde</label>
        <input id="history-from" name="history_from" type="date" value="<?= e($filters['history_from'] ?? '') ?>">
      </div>
      <div>
        <label for="history-to">Hasta</label>
        <input id="history-to" name="history_to" type="date" value="<?= e($filters['history_to'] ?? '') ?>">
      </div>
      <div>
        <label for="history-product">Producto</label>
        <select id="history-product" name="history_product">
          <option value="">Todos</option>
          <?php foreach ($filterProducts as $product) : ?>
            <option value="<?= e((int) $product['id']) ?>" <?= (int) ($filters['history_product'] ?? 0) === (int) $product['id'] ? 'selected' : '' ?>>
              <?= e($product['name']) ?><?= empty($product['active']) ? ' (archivado)' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="history-source">Origen</label>
        <select id="history-source" name="history_source">
          <option value="">Todos</option>
          <?php foreach ($deliverySources as $source) : ?>
            <option value="<?= e($source) ?>" <?= ($filters['history_source'] ?? '') === $source ? 'selected' : '' ?>><?= e(strtoupper($source)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="history-status">Estado</label>
        <select id="history-status" name="history_status">
          <option value="">Todos</option>
          <option value="delivered" <?= ($filters['history_status'] ?? '') === 'delivered' ? 'selected' : '' ?>>Entregada</option>
          <option value="cancelled" <?= ($filters['history_status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>Anulada</option>
        </select>
      </div>
      <div class="inventory-filter-actions inventory-record-filter-actions">
        <button type="submit">Filtrar</button>
        <a class="button ghost" href="<?= e(url_page('inventario', ['tab' => 'history'])) ?>">Limpiar</a>
      </div>
    </form>
    <div class="inventory-table-wrap">
      <table class="inventory-table">
        <thead><tr><th>Fecha</th><th>Destinatario e inmueble</th><th>Productos</th><th>Origen</th><th>Evidencias</th><th>Estado</th><th>Acción</th></tr></thead>
        <tbody data-history-body>
          <?php foreach ($deliveries as $delivery) :
            $deliveryMetadata = is_array($delivery['recipient_metadata_data'] ?? null) ? $delivery['recipient_metadata_data'] : [];
            $propertyValue = (
              $deliveryMetadata['inmueble_direccion']
              ?? $deliveryMetadata['property_address']
              ?? $deliveryMetadata['inmueble']
              ?? $deliveryMetadata['property_name']
              ?? ''
            );
            $employeeValue = (
              $deliveryMetadata['funcionario_nombre']
              ?? $deliveryMetadata['delivered_by_name']
              ?? $deliveryMetadata['employee_name']
              ?? ''
            );
            $propertyLabel = is_scalar($propertyValue) ? trim((string) $propertyValue) : '';
            $employeeName = is_scalar($employeeValue) ? trim((string) $employeeValue) : '';
          ?>
            <tr data-history-row>
              <td data-label="Fecha"><?= e(date('d/m/Y', strtotime((string) $delivery['delivered_at']))) ?><small><?= e(date('h:i a', strtotime((string) $delivery['delivered_at']))) ?></small></td>
              <td data-label="Destinatario e inmueble">
                <strong><?= e($delivery['recipient_name']) ?></strong>
                <small><?= e($delivery['recipient_type'] ?: $delivery['recipient_source'] ?: 'Sin tipo') ?></small>
                <?php if ($propertyLabel !== '') : ?><small><b>Inmueble:</b> <?= e($propertyLabel) ?></small><?php endif; ?>
                <?php if ($employeeName !== '') : ?><small><b>Entregó:</b> <?= e($employeeName) ?></small><?php endif; ?>
              </td>
              <td data-label="Productos"><?= e($delivery['products'] ?: 'Sin detalle') ?></td>
              <td data-label="Origen"><span class="inventory-origin"><?= e(strtoupper((string) $delivery['source'])) ?></span></td>
              <td data-label="Evidencias">
                <?php if (!empty($delivery['evidence'])) : ?>
                  <div class="inventory-evidence-links">
                    <?php foreach ($delivery['evidence'] as $index => $evidence) :
                      $evidenceUrl = ($evidence['evidence_type'] ?? '') === 'url'
                        ? (string) $evidence['external_url']
                        : url(['action' => 'inventory-evidence', 'id' => (int) $evidence['id']]);
                    ?>
                      <a href="<?= e($evidenceUrl) ?>" target="_blank" rel="noopener">Foto <?= e($index + 1) ?></a>
                    <?php endforeach; ?>
                  </div>
                <?php else : ?><span class="inventory-muted">Sin evidencia</span><?php endif; ?>
              </td>
              <td data-label="Estado"><span class="inventory-status is-<?= e($delivery['status']) ?>"><?= $delivery['status'] === 'cancelled' ? 'Anulada' : 'Entregada' ?></span></td>
              <td data-label="Acción">
                <?php if ($canManage && $delivery['status'] === 'delivered' && $delivery['source'] !== 'pph') : ?>
                  <details class="inventory-cancel">
                    <summary>Anular</summary>
                    <form method="post" onsubmit="return confirm('¿Anular esta entrega y reponer sus productos?');">
                      <input type="hidden" name="action" value="inventory_cancel_delivery">
                      <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
                      <input type="hidden" name="return_tab" value="history">
                      <input type="hidden" name="delivery_id" value="<?= e((int) $delivery['id']) ?>">
                      <label>Motivo<input name="reason" required maxlength="500"></label>
                      <button type="submit" class="danger">Anular y reponer</button>
                    </form>
                  </details>
                <?php else : ?><span class="inventory-muted">—</span><?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if ($deliveries === []) : ?><tr><td colspan="7">No encontramos entregas con los filtros seleccionados.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
<?php endif; ?>

<?php if ($tab === 'movements') : ?>
  <section class="inventory-panel">
    <div class="inventory-panel-head">
      <div><p>Auditoría</p><h2>Movimientos de existencias</h2></div>
      <span><?= e(count($movements)) ?> resultados</span>
    </div>
    <form method="get" class="inventory-record-filters">
      <input type="hidden" name="page" value="inventario">
      <input type="hidden" name="tab" value="movements">
      <div class="inventory-filter-search">
        <label for="movement-search">Buscar</label>
        <input id="movement-search" name="movement_q" value="<?= e($filters['movement_q'] ?? '') ?>" placeholder="Producto, destinatario o motivo">
      </div>
      <div>
        <label for="movement-from">Desde</label>
        <input id="movement-from" name="movement_from" type="date" value="<?= e($filters['movement_from'] ?? '') ?>">
      </div>
      <div>
        <label for="movement-to">Hasta</label>
        <input id="movement-to" name="movement_to" type="date" value="<?= e($filters['movement_to'] ?? '') ?>">
      </div>
      <div>
        <label for="movement-product">Producto</label>
        <select id="movement-product" name="movement_product">
          <option value="">Todos</option>
          <?php foreach ($filterProducts as $product) : ?>
            <option value="<?= e((int) $product['id']) ?>" <?= (int) ($filters['movement_product'] ?? 0) === (int) $product['id'] ? 'selected' : '' ?>>
              <?= e($product['name']) ?><?= empty($product['active']) ? ' (archivado)' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="movement-source">Origen</label>
        <select id="movement-source" name="movement_source">
          <option value="">Todos</option>
          <?php foreach ($movementSources as $source) : ?>
            <option value="<?= e($source) ?>" <?= ($filters['movement_source'] ?? '') === $source ? 'selected' : '' ?>><?= e(strtoupper($source)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="movement-type">Tipo de movimiento</label>
        <select id="movement-type" name="movement_type">
          <option value="">Todos</option>
          <?php foreach ($movementTypeOptions as $movementType) : ?>
            <option value="<?= e($movementType) ?>" <?= ($filters['movement_type'] ?? '') === $movementType ? 'selected' : '' ?>>
              <?= e($movementTypeLabels[$movementType] ?? ucwords(str_replace('_', ' ', $movementType))) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="inventory-filter-actions inventory-record-filter-actions">
        <button type="submit">Filtrar</button>
        <a class="button ghost" href="<?= e(url_page('inventario', ['tab' => 'movements'])) ?>">Limpiar</a>
      </div>
    </form>
    <div class="inventory-table-wrap">
      <table class="inventory-table">
        <thead><tr><th>Fecha</th><th>Producto</th><th>Movimiento</th><th>Cambio</th><th>Saldo</th><th>Origen</th><th>Motivo</th><th>Evidencia</th></tr></thead>
        <tbody>
          <?php foreach ($movements as $movement) : ?>
            <tr>
              <td data-label="Fecha"><?= e(date('d/m/Y h:i a', strtotime((string) $movement['created_at']))) ?></td>
              <td data-label="Producto"><strong><?= e($movement['product_name']) ?></strong></td>
              <td data-label="Movimiento"><?= e($movementTypeLabels[$movement['movement_type']] ?? ucwords(str_replace('_', ' ', (string) $movement['movement_type']))) ?></td>
              <td data-label="Cambio"><strong class="<?= (int) $movement['quantity_delta'] >= 0 ? 'inventory-positive' : 'inventory-negative' ?>"><?= (int) $movement['quantity_delta'] > 0 ? '+' : '' ?><?= e((int) $movement['quantity_delta']) ?></strong></td>
              <td data-label="Saldo"><?= e((int) $movement['stock_before']) ?> → <strong><?= e((int) $movement['stock_after']) ?></strong></td>
              <td data-label="Origen"><span class="inventory-origin"><?= e(strtoupper((string) $movement['source'])) ?></span></td>
              <td data-label="Motivo"><?= e($movement['reason']) ?></td>
              <td data-label="Evidencia">
                <?php if (!empty($movement['evidence'])) : ?>
                  <div class="inventory-evidence-links">
                    <?php foreach ($movement['evidence'] as $index => $evidence) : ?>
                      <a href="<?= e(url(['action' => 'inventory-evidence', 'id' => (int) $evidence['id']])) ?>" target="_blank" rel="noopener">Foto <?= e($index + 1) ?></a>
                    <?php endforeach; ?>
                  </div>
                <?php else : ?><span class="inventory-muted">Sin evidencia</span><?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if ($movements === []) : ?><tr><td colspan="8">No encontramos movimientos con los filtros seleccionados.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
<?php endif; ?>

<?php if ($canManage) : ?>
  <div class="inventory-modal" data-delivery-modal hidden>
    <button class="inventory-modal-backdrop" type="button" data-close-inventory-modal tabindex="-1" aria-label="Cerrar ventana"></button>
    <section class="inventory-modal-dialog is-wide" role="dialog" aria-modal="true" aria-labelledby="inventory-delivery-modal-title">
      <header class="inventory-modal-head">
        <div>
          <p>Salida de inventario</p>
          <h2 id="inventory-delivery-modal-title">Registrar una entrega</h2>
          <span>Descuenta uno o varios productos y conserva la evidencia de la entrega.</span>
        </div>
        <button class="inventory-modal-close" type="button" data-close-inventory-modal aria-label="Cerrar ventana"></button>
      </header>
      <form method="post" enctype="multipart/form-data" class="inventory-delivery-form inventory-modal-form" data-inventory-delivery-form>
        <input type="hidden" name="action" value="inventory_create_delivery">
        <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="return_tab" value="history">
        <fieldset>
          <legend>Datos de la entrega</legend>
          <div class="inventory-form-grid">
            <div>
              <label for="delivery-date">Fecha y hora <span aria-hidden="true">*</span></label>
              <input id="delivery-date" name="delivered_at" type="datetime-local" required value="<?= e(date('Y-m-d\TH:i')) ?>">
            </div>
            <div>
              <label for="recipient-name">Nombre de quien recibe <span aria-hidden="true">*</span></label>
              <input id="recipient-name" name="recipient_name" required autocomplete="name" data-modal-initial-focus>
            </div>
            <div>
              <label for="recipient-type">Tipo de destinatario</label>
              <input id="recipient-type" name="recipient_type" placeholder="Funcionario, cliente, proveedor...">
            </div>
            <div>
              <label for="recipient-document">Documento o tarjeta</label>
              <input id="recipient-document" name="recipient_document">
            </div>
            <div>
              <label for="recipient-source">Origen del dato</label>
              <input id="recipient-source" name="recipient_source" placeholder="Manual, PPH, formulario, CRM...">
            </div>
            <div>
              <label for="recipient-id">ID en el sistema de origen</label>
              <input id="recipient-id" name="recipient_id">
            </div>
          </div>
        </fieldset>

        <fieldset>
          <legend>Productos entregados</legend>
          <div data-delivery-items class="inventory-delivery-items">
            <div class="inventory-delivery-item" data-delivery-item>
              <div>
                <label for="delivery-product-0">Producto <span aria-hidden="true">*</span></label>
                <select id="delivery-product-0" name="items[0][product_id]" required>
                  <option value="">Selecciona un producto</option>
                  <?php foreach ($activeProducts as $product) : ?>
                    <option value="<?= e((int) $product['id']) ?>" data-stock="<?= e((int) $product['quantity']) ?>" <?= (int) $product['quantity'] <= 0 ? 'disabled' : '' ?>>
                      <?= e($product['name']) ?> — <?= e((int) $product['quantity']) ?> disponibles
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div>
                <label for="delivery-quantity-0">Cantidad</label>
                <input id="delivery-quantity-0" name="items[0][quantity]" type="number" min="1" value="1" required>
                <small data-stock-hint>Selecciona un producto para ver existencias.</small>
              </div>
              <button type="button" class="button ghost danger inventory-remove-item" data-remove-delivery-item aria-label="Quitar producto">Quitar</button>
            </div>
          </div>
          <button type="button" class="button ghost" data-add-delivery-item>Agregar otro producto</button>
        </fieldset>

        <fieldset>
          <legend>Evidencias y observaciones</legend>
          <div class="inventory-form-grid">
            <label class="inventory-upload inventory-field-wide">
              <input name="evidence[]" type="file" accept="image/jpeg,image/png,image/webp" multiple data-evidence-input>
              <span><strong>Adjuntar fotografías opcionales</strong><small>Hasta 5 imágenes JPG, PNG o WebP; máximo 8 MB por imagen.</small></span>
            </label>
            <div class="inventory-field-wide" data-evidence-preview aria-live="polite"></div>
            <div class="inventory-field-wide">
              <label for="delivery-notes">Observaciones</label>
              <textarea id="delivery-notes" name="notes" rows="4"></textarea>
            </div>
          </div>
        </fieldset>
        <div class="inventory-modal-actions">
          <button class="button ghost" type="button" data-close-inventory-modal>Cancelar</button>
          <button type="submit">Registrar entrega y descontar</button>
        </div>
      </form>
    </section>
  </div>
<?php endif; ?>

<div class="inventory-modal" data-api-modal hidden>
  <button class="inventory-modal-backdrop" type="button" data-close-inventory-modal tabindex="-1" aria-label="Cerrar ventana"></button>
  <section class="inventory-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="inventory-api-modal-title">
    <header class="inventory-modal-head">
      <div>
        <p>Formularios y sitios externos</p>
        <h2 id="inventory-api-modal-title">Integración API</h2>
        <span>Consulta existencias y registra entregas desde otros sistemas.</span>
      </div>
      <button class="inventory-modal-close" type="button" data-close-inventory-modal aria-label="Cerrar ventana"></button>
    </header>
    <div class="inventory-api-modal-body inventory-api-panel">
      <div class="inventory-api-grid">
        <article>
          <span class="inventory-method is-get">GET</span>
          <h3><?= e($apiBase) ?>/products</h3>
          <p>Devuelve productos activos y sus existencias disponibles.</p>
        </article>
        <article>
          <span class="inventory-method is-post">POST</span>
          <h3><?= e($apiBase) ?>/deliveries</h3>
          <p>Registra una entrega transaccional y descuenta los productos.</p>
        </article>
      </div>
      <div class="inventory-code-block">
        <div><strong>Ejemplo JSON</strong><button type="button" class="button ghost compact" data-copy-api>Copiar</button></div>
        <pre data-api-example tabindex="0">{
  "external_id": "entrega-inmueble-contrato-4582",
  "delivered_at": "<?= e(date('c')) ?>",
  "recipient_name": "María Pérez",
  "recipient_type": "Arrendatario",
  "recipient_document": "1020304050",
  "recipient_source": "Formulario entrega de inmueble",
  "recipient_id": "ARR-1508",
  "recipient_metadata": {
    "inmueble_id": "INM-908",
    "inmueble_direccion": "Calle 10 # 20-30 Apto 401",
    "contrato_id": "CTR-4582",
    "funcionario_id": "EMP-27",
    "funcionario_nombre": "Carlos Gómez"
  },
  "product_ids": [1, 2],
  "evidence_urls": ["https://sitio.example/evidencia.jpg"],
  "notes": "Entrega inicial del inmueble"
}</pre>
      </div>
      <div class="inventory-api-note">
        <strong>Configuración segura</strong>
        <p>Define <code>SKC_INVENTORY_API_TOKEN</code> en el servidor y envíalo como <code>Authorization: Bearer &lt;token&gt;</code> o <code>X-Inventory-Token</code>. El token nunca se muestra en esta pantalla.</p>
        <p>Para checkboxes, usa como valor el <code>id</code> devuelto por <code>GET /products</code> y envía los seleccionados en <code>product_ids</code>. Cada ID descuenta una unidad.</p>
      </div>
      <div class="inventory-modal-actions">
        <button class="button ghost" type="button" data-close-inventory-modal>Cerrar</button>
      </div>
    </div>
  </section>
</div>
