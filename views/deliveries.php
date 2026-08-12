<?php
$hasQueueFilters = false;
foreach (['channel', 'status', 'gda_tipo_actor', 'destination_name', 'destination'] as $filterKey) {
    if (trim((string) ($filters[$filterKey] ?? '')) !== '') {
        $hasQueueFilters = true;
        break;
    }
}
$summarySuffix = $hasQueueFilters ? ' filtrados' : ' en cola';
?>
<header class="page-head hero-head">
  <div>
    <span class="eyebrow">Operación de campañas</span>
    <h1>Envíos y colas</h1>
    <p>Seguimiento de mensajes pendientes, enviados, fallidos y trazabilidad histórica.</p>
  </div>
</header>

<section class="metrics deliveries-metrics">
  <article><span>Pendientes<?= e($summarySuffix) ?></span><strong><?= e((int) $summary['queue_pending']) ?></strong></article>
  <article><span>Enviados<?= e($summarySuffix) ?></span><strong><?= e((int) $summary['queue_sent']) ?></strong></article>
  <article><span>Fallidos<?= e($summarySuffix) ?></span><strong><?= e((int) $summary['queue_failed']) ?></strong></article>
  <article><span>Registros<?= e($hasQueueFilters ? ' filtrados' : ' totales') ?></span><strong><?= e((int) ($summary['queue_total'] ?? $queue['total'] ?? 0)) ?></strong></article>
  <article><span>Canales<?= e($hasQueueFilters ? ' filtrados' : ' activos') ?></span><strong><?= e(count($summary['channels'] ?? [])) ?></strong></article>
</section>

<?php if (isset($_GET['queue_remove'])) : ?>
  <?php
    $removeMessages = [
      'removed' => 'Registro quitado de la cola.',
      'sent' => 'Ese envío ya fue enviado y no se puede quitar de la cola.',
      'processing' => 'Ese envío se está procesando y no se puede quitar en este momento.',
      'blocked' => 'No fue posible quitar ese registro de la cola.',
      'not_found' => 'No se encontró el registro solicitado.',
    ];
    $removeStatus = (string) $_GET['queue_remove'];
  ?>
  <div class="<?= $removeStatus === 'removed' ? 'notice' : 'alert' ?>"><?= e($removeMessages[$removeStatus] ?? 'No fue posible actualizar la cola.') ?></div>
<?php endif; ?>

<section class="panel deliveries-queue-panel">
  <div class="panel-head"><h2>Cola de envíos</h2><span><?= e((int) $queue['total']) ?> registros</span></div>
  <form class="filter-panel compact-filters" method="get" data-autosubmit>
    <input type="hidden" name="page" value="envios">
    <input type="hidden" name="tab" value="queue">
    <div>
      <label>Canal</label>
      <select name="channel">
        <option value="">Todos</option>
        <?php foreach (($filterOptions['channels'] ?? []) as $option) : ?>
          <option value="<?= e($option) ?>" <?= ($filters['channel'] ?? '') === $option ? 'selected' : '' ?>><?= e(strtoupper($option)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label>Estado</label>
      <select name="status">
        <option value="">Todos</option>
        <?php foreach (($filterOptions['statuses'] ?? []) as $option) : ?>
          <option value="<?= e($option) ?>" <?= ($filters['status'] ?? '') === $option ? 'selected' : '' ?>><?= e($option) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label>Actor</label>
      <select name="gda_tipo_actor">
        <option value="">Todos</option>
        <?php foreach (($filterOptions['actors'] ?? []) as $option) : ?>
          <option value="<?= e($option) ?>" <?= ($filters['gda_tipo_actor'] ?? '') === $option ? 'selected' : '' ?>><?= e($option) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div><label>Nombre</label><input name="destination_name" value="<?= e($filters['destination_name'] ?? '') ?>" placeholder="Destinatario"></div>
    <div><label>Destino</label><input name="destination" value="<?= e($filters['destination'] ?? '') ?>" placeholder="Correo o celular"></div>
    <div class="filter-actions"><button>Aplicar</button><a class="button ghost" href="<?= e(url(['page' => 'envios', 'tab' => 'queue'])) ?>">Limpiar</a></div>
  </form>
  <div class="table-wrap">
    <table>
      <thead><tr><th>ID</th><th>Canal</th><th>Estado</th><th>Actor</th><th>Destinatario</th><th>Destino</th><th>Asunto</th><th>Intentos</th><th>Creado</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($queue['items'] as $row) : ?>
        <?php $rowStatus = strtolower((string) ($row['status'] ?? '')); ?>
        <tr>
          <td><?= e($row['id'] ?? '') ?></td>
          <td><span class="badge"><?= e($row['channel'] ?? '') ?></span></td>
          <td><span class="status <?= e($rowStatus) ?>"><?= e($row['status'] ?? '') ?></span></td>
          <td><?= e($row['gda_tipo_actor'] ?? '') ?></td>
          <td><?= e($row['destination_name'] ?? '') ?></td>
          <td><?= e($row['destination'] ?? '') ?></td>
          <td><?= e($row['subject'] ?? '') ?></td>
          <td><?= e($row['attempts'] ?? '0') ?></td>
          <td><?= e($row['created_at'] ?? '') ?></td>
          <td class="queue-actions-cell">
            <?php if (in_array($rowStatus, ['pending', 'paused', 'failed'], true)) : ?>
              <form method="post" class="queue-remove-form" onsubmit="return confirm('¿Quitar este envío de la cola?');">
                <input type="hidden" name="action" value="remove_queue_item">
                <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= e((int) ($row['id'] ?? 0)) ?>">
                <input type="hidden" name="p" value="<?= e($pageNumber) ?>">
                <?php foreach (['channel', 'status', 'gda_tipo_actor', 'destination_name', 'destination'] as $filterKey) : ?>
                  <input type="hidden" name="<?= e($filterKey) ?>" value="<?= e($filters[$filterKey] ?? '') ?>">
                <?php endforeach; ?>
                <button type="submit" class="button ghost danger compact">Quitar</button>
              </form>
            <?php else : ?>
              <span class="muted-action">-</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<?php $current = $queue; ?>
<div class="pagination">
  <?php
    $paginationBase = ['page' => 'envios', 'tab' => 'queue'];
    foreach (['channel', 'status', 'gda_tipo_actor', 'destination_name', 'destination'] as $filterKey) {
        if (trim((string) ($filters[$filterKey] ?? '')) !== '') {
            $paginationBase[$filterKey] = (string) $filters[$filterKey];
        }
    }
  ?>
  <?php if ($pageNumber > 1) : ?><a href="<?= e(url(array_merge($paginationBase, ['p' => $pageNumber - 1]))) ?>">Anterior</a><?php endif; ?>
  <span>Página <?= e($pageNumber) ?> de <?= e((int) $current['pages']) ?></span>
  <?php if ($pageNumber < $current['pages']) : ?><a href="<?= e(url(array_merge($paginationBase, ['p' => $pageNumber + 1]))) ?>">Siguiente</a><?php endif; ?>
</div>
