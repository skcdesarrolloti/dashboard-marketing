<?php
$status = $detail['status'] ?? [];
$config = $detail['config'] ?? [];
$tracking = $detail['tracking'] ?? [];
$templatesUsed = $detail['templates'] ?? [];
$sum = static function (array $status, string $key): int {
    $total = 0;
    foreach ($status as $row) {
        $total += (int) ($row[$key] ?? 0);
    }
    return $total;
};
$isAll = $selected === '';
$selectedStatus = (string) ($config['status'] ?? 'active');
$chartTotalRaw = $sum($status, 'pending') + $sum($status, 'sent') + $sum($status, 'failed');
$chartTotal = max(1, $chartTotalRaw);
$sentPercent = (int) round(($sum($status, 'sent') / $chartTotal) * 100);
$pendingPercent = (int) round(($sum($status, 'pending') / $chartTotal) * 100);
$failedPercent = max(0, 100 - $sentPercent - $pendingPercent);
$trackedTotal = (int) ($tracking['tracked'] ?? 0);
$openedTotal = (int) ($tracking['opened'] ?? 0);
$totalOpens = (int) ($tracking['total_opens'] ?? 0);
$openRate = $trackedTotal > 0 ? (int) round(($openedTotal / $trackedTotal) * 100) : 0;
$unopenedTotal = max(0, $trackedTotal - $openedTotal);
$unopenedRate = max(0, 100 - $openRate);
$analyticsOptions = $analyticsOptions ?? [];
$analyticsCampaignOptions = is_array($analyticsOptions['campaigns'] ?? null) ? $analyticsOptions['campaigns'] : [];
$analyticsSourceOptions = is_array($analyticsOptions['sources'] ?? null) ? $analyticsOptions['sources'] : [];
$analyticsMediumOptions = is_array($analyticsOptions['mediums'] ?? null) ? $analyticsOptions['mediums'] : [];
$renderAnalyticsOptions = static function (array $rows, string $selected): void {
    foreach ($rows as $row) {
        $value = trim(is_array($row) ? (string) ($row['value'] ?? '') : (string) $row);
        if ($value === '') {
            continue;
        }
        echo '<option value="' . e($value) . '"' . ($selected === $value ? ' selected' : '') . '>' . e($value) . '</option>';
    }
};
$actorTypes = $detail['actor_types'] ?? [];
$actorTypeOptions = $actorTypeOptions ?? [];
$showTrackingPanel = trim((string) ($filters['opened'] ?? '')) === '';
if (($filters['tipo_actor'] ?? '') !== '' && !in_array((string) $filters['tipo_actor'], $actorTypeOptions, true)) {
    $actorTypeOptions[] = (string) $filters['tipo_actor'];
}
$isLightDetail = !$isAll && !isset($_GET['history']) && (int) ($detail['history']['total'] ?? 0) === 0;
$historyParams = ['page' => 'campanas'];
if (!$isAll) {
    $historyParams['campaign'] = $selected;
}
foreach (['from', 'to', 'canal', 'estado', 'opened', 'tipo_actor', 'q'] as $filterKey) {
    $filterValue = trim((string) ($filters[$filterKey] ?? ''));
    if ($filterValue !== '') {
        $historyParams[$filterKey] = $filterValue;
    }
}
?>

<header class="page-head hero-head">
  <div>
    <span class="eyebrow">Gestor Actores</span>
    <h1>Campañas</h1>
    <p>Catálogo de campañas, estado operativo y detalle bajo demanda.</p>
  </div>
  <form class="inline-filters" method="post">
    <input type="hidden" name="action" value="create_campaign">
    <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
    <input name="campaign_tag" placeholder="Nueva campaña" required>
    <button class="primary">Crear</button>
  </form>
</header>

<?php foreach (['created' => 'Campaña creada.', 'saved' => 'Configuración guardada.', 'paused' => 'Campaña pausada.', 'resumed' => 'Campaña reanudada.'] as $key => $message) : ?>
  <?php if (isset($_GET[$key])) : ?><div class="notice"><?= e($message) ?></div><?php endif; ?>
<?php endforeach; ?>
<?php if (isset($_GET['retried'])) : ?><div class="notice">Fallidos marcados para reintento: <?= e((int) $_GET['retried']) ?>.</div><?php endif; ?>
<?php if (isset($_GET['renamed'])) : ?><div class="notice">Campaña renombrada.</div><?php endif; ?>
<?php if (isset($_GET['deleted'])) : ?><div class="notice">Campaña eliminada.</div><?php endif; ?>
<?php if (isset($_GET['excluded_actor'])) : ?><div class="notice">Actor excluido de la campaña.</div><?php endif; ?>

<section class="campaign-console campaign-console-catalog">
  <aside class="panel campaign-list">
    <div class="panel-head"><h2>Catálogo</h2><span><?= e(count($campaigns)) ?> campañas</span></div>
    <div class="campaign-list-body">
      <?php foreach ($campaigns as $campaign) : ?>
        <?php
          $active = ($campaign['tag'] ?? '') === $selected;
        ?>
        <a class="campaign-item <?= $active ? 'active' : '' ?>" href="<?= e(url(['page' => 'campanas', 'campaign' => $campaign['tag']])) ?>">
          <strong><?= e($campaign['tag']) ?></strong>
          <span><?= e((int) ($campaign['pending_total'] ?? 0)) ?> pendientes · <?= e((int) ($campaign['sent_total'] ?? 0)) ?> enviados · <?= e((int) ($campaign['failed_total'] ?? 0)) ?> fallidos</span>
          <small>Ver detalle</small>
        </a>
      <?php endforeach; ?>
      <?php if (!$campaigns) : ?><div class="empty-state">No hay campañas registradas todavía.</div><?php endif; ?>
    </div>
  </aside>

  <?php if ($isAll) : ?>
    <section class="panel campaign-catalog-empty">
      <div class="empty-state"><h3>Selecciona una campaña</h3><p>El detalle, acciones e historial se abrirán en una ventana limpia sin sacar el catálogo de foco.</p></div>
    </section>
  <?php endif; ?>
</section>

<?php if (!$isAll) : ?>
<div class="modal campaign-detail-modal" id="campaign-detail-modal"><div class="modal-backdrop" data-modal-close></div><section class="modal-panel campaign-detail-panel" role="dialog" aria-modal="true" aria-label="Detalle de campaña"><button type="button" class="modal-close" data-modal-close aria-label="Cerrar">&times;</button>
  <div class="grid campaign-detail" data-campaign-detail>
      <section class="panel">
        <div class="panel-head">
          <h2><?= e($selected) ?></h2>
          <span class="status <?= e($selectedStatus === 'paused' ? 'pending' : 'sent') ?>"><?= e($selectedStatus === 'paused' ? 'Pausada' : 'Activa') ?></span>
        </div>
        <div class="campaign-actions">
          <form method="post" onsubmit="return confirm('¿Eliminar la campaña <?= e($selected) ?> y todos sus envíos?');">
            <input type="hidden" name="action" value="delete_campaign">
            <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="campaign_tag" value="<?= e($selected) ?>">
            <button class="button danger">Eliminar campaña</button>
          </form>
          <form method="post" class="inline-filters"><input type="hidden" name="action" value="rename_campaign"><input type="hidden" name="_token" value="<?=e(csrf_token())?>"><input type="hidden" name="campaign_tag" value="<?=e($selected)?>"><input name="new_tag" value="<?=e($selected)?>" aria-label="Nuevo nombre de campaña" required><button>Renombrar</button></form>
        </div>
      </section>

      <section class="panel campaign-filter-shell">
        <div class="panel-head"><h2>Filtros de campaña</h2><span>Actualizan métricas, gráficos e historial</span></div>
        <form class="filter-panel compact-filters" method="get" data-autosubmit data-ajax-form data-ajax-target="[data-campaign-detail]">
          <input type="hidden" name="page" value="campanas">
          <input type="hidden" name="campaign" value="<?= e($selected) ?>">
          <div>
            <label>Desde</label>
            <input type="date" name="from" value="<?= e($filters['from'] ?? '') ?>">
          </div>
          <div>
            <label>Hasta</label>
            <input type="date" name="to" value="<?= e($filters['to'] ?? '') ?>">
          </div>
          <div>
            <label>Canal</label>
            <select name="canal">
              <option value="">Todos</option>
              <?php foreach (['email', 'sms', 'whatsapp'] as $option) : ?><option value="<?= e($option) ?>" <?= ($filters['canal'] ?? '') === $option ? 'selected' : '' ?>><?= e(strtoupper($option)) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div>
            <label>Estado</label>
            <select name="estado">
              <option value="">Todos</option>
              <?php foreach (['pending' => 'Pendiente', 'sent' => 'Enviado', 'failed' => 'Fallido'] as $option => $label) : ?><option value="<?= e($option) ?>" <?= ($filters['estado'] ?? '') === $option ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div>
            <label>Abrió</label>
            <select name="opened">
              <option value="">Todos</option>
              <option value="yes" <?= ($filters['opened'] ?? '') === 'yes' ? 'selected' : '' ?>>Sí</option>
              <option value="no" <?= ($filters['opened'] ?? '') === 'no' ? 'selected' : '' ?>>No</option>
            </select>
          </div>
          <div>
            <label>Actor</label>
            <select name="tipo_actor">
              <option value="">Todos</option>
              <?php foreach ($actorTypeOptions as $option) : ?>
                <option value="<?= e($option) ?>" <?= ($filters['tipo_actor'] ?? '') === $option ? 'selected' : '' ?>><?= e($option) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label>Buscar</label>
            <input name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="destinatario, asunto, error">
          </div>
          <div class="filter-actions">
            <button>Filtrar</button>
            <a class="button ghost" href="<?= e(url(['page' => 'campanas', 'campaign' => $selected])) ?>">Limpiar</a>
          </div>
        </form>
      </section>

      <section class="metrics campaign-kpis">
        <article><span>Pendientes</span><strong><?= e($sum($status, 'pending')) ?></strong></article>
        <article><span>Enviados</span><strong><?= e($sum($status, 'sent')) ?></strong></article>
        <article><span>Personas que abrieron <i class="campaign-help" tabindex="0" aria-label="Personas que abrieron" data-campaign-help="Destinatarios únicos que abrieron el correo al menos una vez. Si una persona abre varias veces, aquí cuenta una sola vez.">i</i></span><strong><?= e($openedTotal) ?></strong></article>
        <article><span>Veces abierto <i class="campaign-help" tabindex="0" aria-label="Veces abierto" data-campaign-help="Total de aperturas registradas. Si una persona abre el correo varias veces, cada apertura suma en este número.">i</i></span><strong><?= e($totalOpens) ?></strong></article>
      </section>

      <section class="grid two campaign-charts">
        <div class="panel">
          <div class="panel-head"><h2>Distribución general</h2><span><?= e($chartTotalRaw) ?> registros</span></div>
          <div class="stacked-chart" aria-label="Distribución de estados">
            <span class="chart-sent" style="width: <?= e($sentPercent) ?>%"></span>
            <span class="chart-pending" style="width: <?= e($pendingPercent) ?>%"></span>
            <span class="chart-failed" style="width: <?= e($failedPercent) ?>%"></span>
          </div>
          <div class="chart-legend">
            <span><i class="chart-sent"></i> Enviados <?= e($sentPercent) ?>%</span>
            <span><i class="chart-pending"></i> Pendientes <?= e($pendingPercent) ?>%</span>
            <span><i class="chart-failed"></i> Fallidos <?= e($failedPercent) ?>%</span>
          </div>
        </div>
        <div class="panel">
          <div class="panel-head"><h2>Canales</h2><span>Email / SMS / WhatsApp</span></div>
          <div class="channel-bars">
            <?php foreach ($status as $channel => $row) : ?>
              <?php $maxChannel = max(1, (int) ($row['total'] ?? 0), $chartTotal); ?>
              <div>
                <strong><?= e($channel) ?></strong>
                <span><i style="width: <?= e((int) round(((int) ($row['total'] ?? 0) / $maxChannel) * 100)) ?>%"></i></span>
                <em><?= e((int) ($row['total'] ?? 0)) ?></em>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </section>

      <section class="grid two">
        <div class="panel">
          <div class="panel-head"><h2>Analítica</h2><span>Bajo demanda</span></div>
          <div class="analytics-launch">
            <strong>Consulta en popup</strong>
            <p>Elige campaña, canal y medio; los resultados se cargan aquí sin salir de Campañas.</p>
            <button type="button" class="primary" data-modal-open="campaign-analytics-modal">Abrir panel</button>
          </div>
        </div>

        <div class="panel">
          <div class="panel-head"><h2>Estado por canal</h2></div>
          <div class="table-wrap">
            <table>
              <thead><tr><th>Canal</th><th>Pendientes</th><th>Enviados</th><th>Fallidos</th><th>Total</th></tr></thead>
              <tbody>
              <?php foreach ($status as $channel => $row) : ?>
                <tr>
                  <td><span class="badge"><?= e($channel) ?></span></td>
                  <td><?= e((int) ($row['pending'] ?? 0)) ?></td>
                  <td><?= e((int) ($row['sent'] ?? 0)) ?></td>
                  <td><?= e((int) ($row['failed'] ?? 0)) ?></td>
                  <td><?= e((int) ($row['total'] ?? 0)) ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <?php if ($showTrackingPanel) : ?>
        <div class="panel">
          <div class="panel-head"><h2>Tracking email</h2><span><?= e($openRate) ?>% apertura</span></div>
          <div class="tracking-guide">Guía: personas que abrieron cuenta destinatarios únicos; veces abierto suma todas las aperturas registradas.</div>
          <div class="metrics mini-metrics">
            <article><span>Rastreados</span><strong><?= e($trackedTotal) ?></strong></article>
            <article><span>Personas que abrieron <i class="campaign-help" tabindex="0" aria-label="Personas que abrieron" data-campaign-help="Destinatarios únicos que abrieron el correo al menos una vez. Si una persona abre varias veces, aquí cuenta una sola vez.">i</i></span><strong><?= e($openedTotal) ?></strong></article>
            <article><span>Veces abierto <i class="campaign-help" tabindex="0" aria-label="Veces abierto" data-campaign-help="Total de aperturas registradas. Si una persona abre el correo varias veces, cada apertura suma en este número.">i</i></span><strong><?= e($totalOpens) ?></strong></article>
            <article><span>Tasa apertura</span><strong><?= e($openRate) ?>%</strong></article>
          </div>
          <div class="tracking-visuals">
            <div class="tracking-donut" style="--rate: <?= e($openRate) ?>;">
              <strong><?= e($openRate) ?>%</strong>
              <span>apertura</span>
            </div>
            <div class="tracking-bars">
              <div>
                <div><strong>Personas que abrieron</strong><em><?= e($openedTotal) ?></em></div>
                <span><i class="tracking-opened" style="width: <?= e($openRate) ?>%"></i></span>
              </div>
              <div>
                <div><strong>Sin abrir</strong><em><?= e($unopenedTotal) ?></em></div>
                <span><i class="tracking-unopened" style="width: <?= e($unopenedRate) ?>%"></i></span>
              </div>
            </div>
          </div>
          <div class="chart-legend tracking-legend">
            <span><i class="chart-sent"></i> Personas que abrieron: <?= e($openedTotal) ?></span>
            <span><i class="chart-pending"></i> Rastreados sin abrir: <?= e($unopenedTotal) ?></span>
          </div>
          <div class="template-usage">
            <strong>Plantillas usadas</strong>
            <?php if ($templatesUsed) : ?>
              <?php foreach ($templatesUsed as $templateRow) : ?>
                <span><b><?= e($templateRow['template_name'] ?? 'Manual') ?></b><em><?= e((int) ($templateRow['total'] ?? 0)) ?></em></span>
              <?php endforeach; ?>
            <?php else : ?>
              <span><b>Sin plantilla registrada</b><em>0</em></span>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>
      </section>

      <section class="grid two">
        <div class="panel">
          <div class="panel-head"><h2>Audiencia por actor</h2><span><?= e(count($actorTypes)) ?> tipos visibles</span></div>
          <div class="table-wrap">
            <table>
              <thead><tr><th>Tipo actor</th><th>Total</th></tr></thead>
              <tbody>
              <?php foreach ($actorTypes as $actorTypeRow) : ?>
                <tr><td><?= e((string) ($actorTypeRow['tipo_actor'] ?? '')) ?></td><td><?= e((int) ($actorTypeRow['total'] ?? 0)) ?></td></tr>
              <?php endforeach; ?>
              <?php if (!$actorTypes) : ?><tr><td colspan="2">Sin datos para los filtros actuales.</td></tr><?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

      </section>

      <section class="panel" data-campaign-history>
        <div class="panel-head"><h2>Historial <?= e($isAll ? 'de todas las campañas' : $selected) ?></h2><span><?= e((int) ($detail['history']['total'] ?? 0)) ?> registros</span></div>
        <?php if ($isLightDetail) : ?>
          <div class="notice">La campaña se abrió en modo rápido. Carga el historial cuando necesites revisar destinatarios y errores.</div>
          <div class="campaign-actions"><a class="button primary" href="<?= e(url(array_merge($historyParams, ['history' => 1]))) ?>">Cargar historial</a></div>
        <?php else : ?>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Fecha</th><th>Campaña</th><th>Plantilla</th><th>Canal</th><th>Estado</th><th>Destinatario</th><th>Actor</th><th>Abrió</th><th>Aperturas</th><th>Error</th></tr></thead>
            <tbody>
            <?php foreach (($detail['history']['items'] ?? []) as $row) : ?>
              <tr>
                <td><?= e($row['fecha_envio'] ?? '') ?></td>
                <td><?= e($row['campaign_tag'] ?? '') ?></td>
                <td><?= e($row['template_name'] ?? 'Manual') ?></td>
                <td><span class="badge"><?= e($row['canal'] ?? '') ?></span></td>
                <td><span class="status <?= e(strtolower((string) ($row['estado'] ?? ''))) ?>"><?= e($row['estado'] ?? '') ?></span></td>
                <td><?= e($row['nombre_destinatario'] ?? '') ?></td>
                <td><?= e($row['tipo_actor'] ?? '') ?></td>
                <td><span class="status <?= !empty($row['opened_at']) || (int) ($row['open_count'] ?? 0) > 0 ? 'sent' : 'pending' ?>"><?= !empty($row['opened_at']) || (int) ($row['open_count'] ?? 0) > 0 ? 'Si' : 'No' ?></span></td>
                <td><?= e((int) ($row['open_count'] ?? 0)) ?></td>
                <td><?= e($row['error_info'] ?? '') ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php if (($detail['history']['pages'] ?? 1) > 1) : ?>
          <div class="pagination">
            <?php if (($detail['history']['page'] ?? 1) > 1) : ?><a href="<?= e(url(array_merge($historyParams, ['p' => (int) $detail['history']['page'] - 1]))) ?>">Anterior</a><?php endif; ?>
            <span>Página <?= e((int) $detail['history']['page']) ?> de <?= e((int) $detail['history']['pages']) ?></span>
            <?php if (($detail['history']['page'] ?? 1) < ($detail['history']['pages'] ?? 1)) : ?><a href="<?= e(url(array_merge($historyParams, ['p' => (int) $detail['history']['page'] + 1]))) ?>">Siguiente</a><?php endif; ?>
          </div>
        <?php endif; ?>
        <?php endif; ?>
      </section>

  </div>
</section></div>
<div class="modal campaign-analytics-modal" id="campaign-analytics-modal" hidden>
  <div class="modal-backdrop" data-modal-close></div>
  <section class="modal-panel campaign-analytics-panel" role="dialog" aria-modal="true" aria-labelledby="campaign-analytics-title">
    <button type="button" class="modal-close" data-modal-close aria-label="Cerrar">&times;</button>
    <div class="campaign-analytics-workspace">
      <header class="campaign-analytics-hero">
        <div>
          <span class="eyebrow">Analítica bajo demanda</span>
          <h3 id="campaign-analytics-title">Consulta de campaña</h3>
          <p>Filtra primero; luego carga solo el resumen necesario.</p>
        </div>
      </header>
      <form class="campaign-analytics-form" method="get" data-campaign-analytics-form>
        <input type="hidden" name="action" value="campaign-analytics-panel">
        <div class="analytics-launch-grid">
          <label>Desde
            <input type="date" name="from">
          </label>
          <label>Hasta
            <input type="date" name="to">
          </label>
          <label>Campaña analítica
            <select name="campaign" required>
              <option value="">Selecciona campaña</option>
              <?php $renderAnalyticsOptions($analyticsCampaignOptions, ''); ?>
            </select>
          </label>
          <label>Canal
            <select name="channel" required>
              <option value="">Selecciona canal</option>
              <option value="email">Email</option>
              <option value="sms">SMS</option>
              <option value="whatsapp">WhatsApp</option>
            </select>
          </label>
          <label>Fuente/Recurso
            <select name="source">
              <option value="">Todas</option>
              <?php $renderAnalyticsOptions($analyticsSourceOptions, ''); ?>
            </select>
          </label>
          <label>Medio
            <select name="medium">
              <option value="">Según canal</option>
              <?php $renderAnalyticsOptions($analyticsMediumOptions, ''); ?>
            </select>
          </label>
        </div>
        <div class="campaign-actions">
          <button type="button" class="button ghost" data-modal-close>Cancelar</button>
          <button class="primary">Consultar</button>
        </div>
      </form>
      <div class="campaign-analytics-result" data-campaign-analytics-result aria-live="polite">
        <div class="campaign-analytics-empty">
          <strong>Sin consulta cargada</strong>
          <span>Selecciona filtros y presiona Consultar.</span>
        </div>
      </div>
    </div>
  </section>
</div>
<?php endif; ?>
