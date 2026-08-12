<?php
$option = static function (array $rows, string $selected): void {
    foreach ($rows as $row) {
        $value = (string) ($row['value'] ?? '');
        if ($value === '') {
            continue;
        }
        echo '<option value="' . e($value) . '"' . ($selected === $value ? ' selected' : '') . '>' . e($value) . '</option>';
    }
};
$pageUrl = static fn (string $nextTab, int $next): string => url(array_merge($_GET, ['page' => 'analiticas', 'tab' => $nextTab, 'p' => $next]));
$tabUrl = static fn (string $nextTab): string => url(array_merge($_GET, ['page' => 'analiticas', 'tab' => $nextTab, 'p' => 1]));
$maxHits = static function (array $rows, string $key): int {
    $max = 0;
    foreach ($rows as $row) {
        $max = max($max, (int) ($row[$key] ?? 0));
    }
    return max(1, $max);
};
$shortUrl = static function (string $url): string {
    $host = parse_url($url, PHP_URL_HOST);
    $path = parse_url($url, PHP_URL_PATH);
    return $host ? $host . ($path ? rtrim($path, '/') : '') : $url;
};
$externalUrl = static function (string $url): string {
    return filter_var($url, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $url) ? $url : '';
};
$sourceMax = $maxHits($data['sources'] ?? [], 'hits');
$mediumMax = $maxHits($data['mediums'] ?? [], 'hits');
$countryMax = $maxHits($data['countries'] ?? [], 'hits');
$cityMax = $maxHits($data['cities'] ?? [], 'hits');
$campaignMax = $maxHits($data['campaigns'] ?? [], 'hits');
$dailyMax = $maxHits($data['daily'] ?? [], 'hits');
$eventTypeMax = $maxHits($data['event_types'] ?? [], 'hits');
$pageMax = $maxHits($data['pages'] ?? [], 'hits');
$referrerMax = $maxHits($data['referrers'] ?? [], 'hits');
$hits = (int) ($data['totals']['hits'] ?? 0);
$events = (int) ($data['totals']['event_hits'] ?? 0);
$views = (int) ($data['totals']['view_hits'] ?? 0);
$eventRate = $hits > 0 ? (int) round(($events / $hits) * 100) : 0;
$registeredHits = (int) ($data['totals']['registered_hits'] ?? 0);
$guestHits = (int) ($data['totals']['guest_hits'] ?? 0);
$registeredShare = $hits > 0 ? (int) round(($registeredHits / $hits) * 100) : 0;
$guestShare = max(0, 100 - $registeredShare);
$uniqueHits = (int) ($data['totals']['unique_ips'] ?? 0);
$uniqueRate = $hits > 0 ? min(100, (int) round(($uniqueHits / $hits) * 100)) : 0;
$dailyRows = $data['daily'] ?? [];
$dailyCount = count($dailyRows);
$dailyTotal = 0;
$dailyPeak = ['hits' => 0, 'day' => null];
foreach ($dailyRows as $dailyRow) {
    $dailyHits = (int) ($dailyRow['hits'] ?? 0);
    $dailyTotal += $dailyHits;
    if ($dailyHits >= (int) ($dailyPeak['hits'] ?? 0)) {
        $dailyPeak = ['hits' => $dailyHits, 'day' => $dailyRow['day'] ?? null];
    }
}
$dailyAverage = $dailyCount > 0 ? (int) round($dailyTotal / $dailyCount) : 0;
$dailyLast = $dailyCount > 0 ? (int) ($dailyRows[$dailyCount - 1]['hits'] ?? 0) : 0;
$dailyTickEvery = $dailyCount > 24 ? 7 : 5;
$dailyAverageOffset = $dailyMax > 0 ? (int) round(($dailyAverage / $dailyMax) * 136) : 0;
$canMergeAnalyticsCampaigns = (bool) ($canMergeAnalyticsCampaigns ?? false);
?>
<header class="page-head hero-head">
  <div>
    <span class="eyebrow">Tracker marketing</span>
    <h1>Analíticas</h1>
    <p>Fuentes, campañas, embudo de eventos, inmuebles y trazabilidad del tráfico.</p>
  </div>
  <a class="button ghost" href="<?= e(url(['page' => 'herramienta'])) ?>">Generar UTM</a>
</header>

<?php if (isset($_GET['campaign_merge_status'])) : ?>
  <?php
    $mergeStatus = (string) ($_GET['campaign_merge_status'] ?? '');
    $mergeCount = (int) ($_GET['campaign_merged'] ?? 0);
    $mergeFrom = (string) ($_GET['campaign_from'] ?? '');
    $mergeTo = (string) ($_GET['campaign_to'] ?? '');
    $mergeOk = $mergeStatus === 'updated' && $mergeCount > 0;
    $mergeMessage = match ($mergeStatus) {
        'updated' => $mergeCount . ' registros movidos de "' . $mergeFrom . '" a "' . $mergeTo . '".',
        'not_found' => 'No se encontraron registros con la campaña origen "' . $mergeFrom . '".',
        'same' => 'La campaña origen y destino son iguales; no se movió nada.',
        'unchanged' => 'La campaña fue encontrada, pero MySQL no reportó cambios. Revisa si el destino ya tenía el mismo valor.',
        default => 'No fue posible unificar la campaña. Revisa origen y destino.',
    };
  ?>
  <div class="<?= $mergeOk ? 'notice' : 'alert' ?>" role="<?= $mergeOk ? 'status' : 'alert' ?>"><?= e($mergeMessage) ?></div>
<?php endif; ?>

<form class="filter-panel analytics-filters" method="get" data-autosubmit>
  <input type="hidden" name="page" value="analiticas">
  <input type="hidden" name="tab" value="<?= e($tab) ?>">
  <div>
    <label>Desde</label>
    <input type="date" name="from" value="<?= e($from) ?>">
  </div>
  <div>
    <label>Hasta</label>
    <input type="date" name="to" value="<?= e($to) ?>">
  </div>
  <div>
    <label>Campaña</label>
    <select name="campaign"><option value="">Todas</option><?php $option($data['options']['campaigns'] ?? [], $filters['campaign'] ?? ''); ?></select>
  </div>
  <div>
    <label>Fuente</label>
    <select name="source"><option value="">Todas</option><?php $option($data['options']['sources'] ?? [], $filters['source'] ?? ''); ?></select>
  </div>
  <div>
    <label>Medio</label>
    <select name="medium"><option value="">Todos</option><?php $option($data['options']['mediums'] ?? [], $filters['medium'] ?? ''); ?></select>
  </div>
  <div>
    <label>Canal</label>
    <select name="channel">
      <option value="">Todos</option>
      <option value="email" <?= ($filters['channel'] ?? '') === 'email' ? 'selected' : '' ?>>Email</option>
      <option value="sms" <?= ($filters['channel'] ?? '') === 'sms' ? 'selected' : '' ?>>SMS</option>
      <option value="whatsapp" <?= ($filters['channel'] ?? '') === 'whatsapp' ? 'selected' : '' ?>>WhatsApp</option>
    </select>
  </div>
  <div>
    <label>Tipo evento</label>
    <select name="event_type"><option value="">Todos</option><?php $option($data['options']['event_types'] ?? [], $filters['event_type'] ?? ''); ?></select>
  </div>
  <div>
    <label>País</label>
    <select name="country"><option value="">Todos</option><?php $option($data['options']['countries'] ?? [], $filters['country'] ?? ''); ?></select>
  </div>
  <div>
    <label>Ciudad</label>
    <select name="city"><option value="">Todas</option><?php $option($data['options']['cities'] ?? [], $filters['city'] ?? ''); ?></select>
  </div>
  <div>
    <label>Usuario</label>
    <select name="user_type">
      <option value="">Todos</option>
      <option value="registered" <?= ($filters['user_type'] ?? '') === 'registered' ? 'selected' : '' ?>>Registrados</option>
      <option value="guest" <?= ($filters['user_type'] ?? '') === 'guest' ? 'selected' : '' ?>>Visitantes</option>
    </select>
  </div>
  <div>
    <label>Buscar</label>
    <input name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Campaña, URL, ref o evento">
  </div>
  <div>
    <label>Ref inmueble</label>
    <input name="search_ref" value="<?= e($filters['search_ref'] ?? '') ?>" placeholder="Código o ID">
  </div>
  <div class="filter-actions">
    <button class="primary">Aplicar</button>
    <a class="button" href="<?= e(url(['page' => 'analiticas'])) ?>">Limpiar</a>
  </div>
</form>

<?php if ($canMergeAnalyticsCampaigns) : ?>
<section class="panel">
  <div class="panel-head"><h2>Unificar campañas</h2><span>Corrección de nombres</span></div>
  <form class="filter-panel compact-filters" method="post" onsubmit="return confirm('¿Unificar esta campaña en el historial de analíticas?')">
    <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="merge_analytics_campaign">
    <div>
      <label>Campaña origen</label>
      <select name="from_campaign" required>
        <option value="">Selecciona campaña</option>
        <?php $option($data['options']['campaigns'] ?? [], ''); ?>
      </select>
    </div>
    <div>
      <label>Campaña destino</label>
      <input name="to_campaign" list="analytics-campaign-list" placeholder="Nombre correcto" required>
    </div>
    <div class="filter-actions">
      <button class="primary">Unificar</button>
    </div>
  </form>
  <datalist id="analytics-campaign-list">
    <?php foreach (($data['options']['campaigns'] ?? []) as $campaignOption) : ?>
      <?php $campaignValue = trim((string) ($campaignOption['value'] ?? '')); ?>
      <?php if ($campaignValue !== '') : ?><option value="<?= e($campaignValue) ?>"></option><?php endif; ?>
    <?php endforeach; ?>
  </datalist>
</section>
<?php endif; ?>

<section class="metrics analytics-metrics">
  <article><span>Total hits</span><strong><?= e($hits) ?></strong></article>
  <article><span>Usuarios únicos</span><strong><?= e((int) ($data['totals']['unique_ips'] ?? 0)) ?></strong></article>
  <article><span>Eventos / interacción</span><strong><?= e($eventRate) ?>%</strong></article>
  <article><span>Campañas</span><strong><?= e((int) ($data['totals']['campaign_count'] ?? 0)) ?></strong></article>
</section>

<section class="analytics-insights">
  <article><span>Hits hoy</span><strong><?= e((int) ($data['totals']['today_hits'] ?? 0)) ?></strong></article>
  <article><span>Vistas</span><strong><?= e($views) ?></strong></article>
  <article><span>Clicks / eventos</span><strong><?= e($events) ?></strong></article>
  <article><span>Registrados</span><strong><?= e($registeredHits) ?></strong></article>
  <article><span>Visitantes</span><strong><?= e($guestHits) ?></strong></article>
</section>

<nav class="tabs glass-tabs" data-analytics-tabs>
  <a class="<?= $tab === 'general' ? 'active' : '' ?>" href="<?= e($tabUrl('general')) ?>">General</a>
  <a class="<?= $tab === 'campanas' ? 'active' : '' ?>" href="<?= e($tabUrl('campanas')) ?>">Campañas</a>
  <a class="<?= $tab === 'inmuebles' ? 'active' : '' ?>" href="<?= e($tabUrl('inmuebles')) ?>">Inmuebles</a>
  <a class="<?= $tab === 'eventos' ? 'active' : '' ?>" href="<?= e($tabUrl('eventos')) ?>">Eventos</a>
  <a class="<?= $tab === 'logs' ? 'active' : '' ?>" href="<?= e($tabUrl('logs')) ?>">Logs</a>
</nav>

<div data-analytics-panes>
  <section class="analytics-pane <?= $tab === 'general' ? 'active' : '' ?>" data-analytics-pane="general">
    <div class="grid two analytics-grid">
      <div class="panel analytics-chart-panel">
        <div class="panel-head trend-head">
          <div><h2>Tendencia diaria</h2><span>Últimos <?= e($dailyCount) ?> días con datos</span></div>
          <div class="trend-summary" aria-label="Resumen de tendencia diaria">
            <span><b><?= e($dailyLast) ?></b><small>último día</small></span>
            <span><b><?= e($dailyAverage) ?></b><small>promedio</small></span>
            <span><b><?= e((int) ($dailyPeak['hits'] ?? 0)) ?></b><small>pico<?= !empty($dailyPeak['day']) ? ' ' . e(date('d/m', strtotime((string) $dailyPeak['day']))) : '' ?></small></span>
          </div>
        </div>
        <div class="trend-chart-shell" role="img" aria-label="Tendencia diaria de hits de los ultimos <?= e($dailyCount) ?> dias">
          <div class="trend-scale" aria-hidden="true">
            <span><?= e($dailyMax) ?></span>
            <span><?= e((int) round($dailyMax / 2)) ?></span>
            <span>0</span>
          </div>
          <div class="trend-chart" style="--avg-offset: <?= e($dailyAverageOffset) ?>px;" data-average="Promedio <?= e($dailyAverage) ?>">
            <?php foreach ($dailyRows as $index => $row) : $height = (int) round(((int) ($row['hits'] ?? 0) / $dailyMax) * 100); ?>
              <span class="chart-tooltip" tabindex="0" style="--height: <?= e(max(4, $height)) ?>%;" data-tooltip="<?= e(date('d/m/Y', strtotime((string) ($row['day'] ?? 'now'))) . ' · ' . (int) ($row['hits'] ?? 0) . ' hits · ' . (int) ($row['unique_ips'] ?? 0) . ' usuarios · ' . (int) ($row['events'] ?? 0) . ' eventos') ?>">
                <i></i><b><?= ($index === 0 || $index === $dailyCount - 1 || $index % $dailyTickEvery === 0) ? e(date('d/m', strtotime((string) ($row['day'] ?? 'now')))) : '' ?></b>
              </span>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <div class="panel analytics-chart-panel">
        <div class="panel-head"><h2>Tipos de evento</h2><span>Vistas vs interacciones</span></div>
        <div class="analytics-bars">
          <?php foreach (($data['event_types'] ?? []) as $row) : ?>
            <div style="--bar: <?= e((int) round(((int) ($row['hits'] ?? 0) / $eventTypeMax) * 100)) ?>%;">
              <span><?= e($row['event_type'] ?? 'view') ?></span><i></i><strong><?= e((int) ($row['hits'] ?? 0)) ?></strong>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="grid two analytics-grid analytics-composition-grid">
      <div class="panel audience-panel">
        <div class="panel-head"><h2>Composición de audiencia</h2><span>Registrados vs visitantes</span></div>
        <div class="audience-chart">
          <div class="donut-chart" style="--registered: <?= e($registeredShare) ?>%;" role="img" aria-label="<?= e($registeredShare) ?>% registrados y <?= e($guestShare) ?>% visitantes">
            <strong><?= e($registeredShare) ?>%</strong><span>registrados</span>
          </div>
          <div class="chart-legend">
            <div><i class="registered"></i><span>Registrados</span><strong><?= e($registeredHits) ?></strong></div>
            <div><i class="guest"></i><span>Visitantes</span><strong><?= e($guestHits) ?></strong></div>
          </div>
        </div>
      </div>
      <div class="panel">
        <div class="panel-head"><h2>Calidad del tráfico</h2><span>Distribución de actividad</span></div>
        <div class="analytics-bars quality-bars">
          <div style="--bar: 100%;"><span>Vistas</span><i></i><strong><?= e($views) ?></strong></div>
          <div style="--bar: <?= e($eventRate) ?>%;"><span>Interacciones</span><i></i><strong><?= e($eventRate) ?>%</strong></div>
          <div style="--bar: <?= e($uniqueRate) ?>%;"><span>Usuarios únicos</span><i></i><strong><?= e($uniqueRate) ?>%</strong></div>
        </div>
      </div>
    </div>

    <div class="grid three analytics-grid">
      <div class="panel">
        <div class="panel-head"><h2>Fuentes</h2><span>Top 8</span></div>
        <div class="rank-list">
          <?php foreach (($data['sources'] ?? []) as $row) : ?>
            <div style="--bar: <?= e((int) round(((int) $row['hits'] / $sourceMax) * 100)) ?>%;"><span><?= e($row['source']) ?></span><strong><?= e((int) $row['hits']) ?></strong></div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="panel">
        <div class="panel-head"><h2>Medios</h2><span>Canales</span></div>
        <div class="rank-list">
          <?php foreach (($data['mediums'] ?? []) as $row) : ?>
            <div style="--bar: <?= e((int) round(((int) $row['hits'] / $mediumMax) * 100)) ?>%;"><span><?= e($row['medium']) ?></span><strong><?= e((int) $row['hits']) ?></strong></div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="panel">
        <div class="panel-head"><h2>Ciudades</h2><span>Origen</span></div>
        <div class="rank-list">
          <?php foreach (($data['cities'] ?? []) as $row) : ?>
            <div style="--bar: <?= e((int) round(((int) $row['hits'] / $cityMax) * 100)) ?>%;"><span><?= e($row['city']) ?></span><strong><?= e((int) $row['hits']) ?></strong></div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="panel">
        <div class="panel-head"><h2>Países</h2><span>Origen</span></div>
        <div class="rank-list">
          <?php foreach (($data['countries'] ?? []) as $row) : ?>
            <div style="--bar: <?= e((int) round(((int) $row['hits'] / $countryMax) * 100)) ?>%;"><span><?= e($row['country']) ?></span><strong><?= e((int) $row['hits']) ?></strong></div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="grid two analytics-grid">
      <div class="panel">
        <div class="panel-head"><h2>Páginas más visitadas</h2><span>Top 10</span></div>
        <div class="analytics-bars analytics-url-bars">
          <?php foreach (($data['pages'] ?? []) as $row) : $pageHref = $externalUrl((string) ($row['page_url'] ?? '')); ?>
            <?php if ($pageHref) : ?><a class="analytics-bar-row chart-tooltip" href="<?= e($pageHref) ?>" target="_blank" rel="noopener noreferrer" style="--bar: <?= e((int) round(((int) ($row['hits'] ?? 0) / $pageMax) * 100)) ?>%;" data-tooltip="<?= e((string) ($row['page_url'] ?? 'Sin URL')) ?>"><?php else : ?><div class="analytics-bar-row chart-tooltip" style="--bar: <?= e((int) round(((int) ($row['hits'] ?? 0) / $pageMax) * 100)) ?>%;" data-tooltip="Sin URL"><?php endif; ?>
              <span><?= e($shortUrl((string) ($row['page_url'] ?? ''))) ?></span><i></i><strong><?= e((int) ($row['hits'] ?? 0)) ?></strong>
            <?= $pageHref ? '</a>' : '</div>' ?>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="panel">
        <div class="panel-head"><h2>Referidores</h2><span>Entrada</span></div>
        <div class="analytics-bars analytics-url-bars">
          <?php foreach (($data['referrers'] ?? []) as $row) : $referrerHref = $externalUrl((string) ($row['referrer'] ?? '')); ?>
            <?php if ($referrerHref) : ?><a class="analytics-bar-row chart-tooltip" href="<?= e($referrerHref) ?>" target="_blank" rel="noopener noreferrer" style="--bar: <?= e((int) round(((int) ($row['hits'] ?? 0) / $referrerMax) * 100)) ?>%;" data-tooltip="<?= e((string) ($row['referrer'] ?? 'Directo')) ?>"><?php else : ?><div class="analytics-bar-row chart-tooltip" style="--bar: <?= e((int) round(((int) ($row['hits'] ?? 0) / $referrerMax) * 100)) ?>%;" data-tooltip="<?= e((string) ($row['referrer'] ?? 'Directo')) ?>"><?php endif; ?>
              <span><?= e($shortUrl((string) ($row['referrer'] ?? ''))) ?></span><i></i><strong><?= e((int) ($row['hits'] ?? 0)) ?></strong>
            <?= $referrerHref ? '</a>' : '</div>' ?>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </section>

  <section class="analytics-pane <?= $tab === 'campanas' ? 'active' : '' ?>" data-analytics-pane="campanas">
    <section class="panel">
      <div class="panel-head"><h2>Campañas rastreadas</h2><span><?= e(count($data['campaigns'] ?? [])) ?> visibles</span></div>
      <div class="campaign-chart-list">
        <?php foreach (($data['campaigns'] ?? []) as $row) : ?>
          <a href="<?= e(url(array_merge($_GET, ['page' => 'analiticas', 'tab' => 'campanas', 'campaign' => (string) ($row['camp_slug'] ?? ''), 'p' => 1]))) ?>" style="--bar: <?= e((int) round(((int) ($row['hits'] ?? 0) / $campaignMax) * 100)) ?>%;">
            <span><strong><?= e($row['camp_slug']) ?></strong><small><?= e((int) ($row['unique_ips'] ?? 0)) ?> usuarios · <?= e((int) ($row['events'] ?? 0)) ?> eventos · <?= e((int) ($row['property_views'] ?? 0)) ?> inmuebles</small></span>
            <i></i>
            <b><?= e((int) ($row['hits'] ?? 0)) ?></b>
          </a>
        <?php endforeach; ?>
      </div>
    </section>

    <div class="grid three analytics-grid">
      <div class="panel">
        <div class="panel-head"><h2>Campaña + canal</h2><span>Fuente / medio</span></div>
        <div class="table-wrap">
          <table><thead><tr><th>Campaña</th><th>Fuente</th><th>Medio</th><th>Hits</th><th>Únicos</th></tr></thead><tbody>
            <?php foreach (($data['campaign_sources'] ?? []) as $row) : ?><tr><td><?= e($row['camp_slug']) ?></td><td><?= e($row['source']) ?></td><td><?= e($row['medium']) ?></td><td><?= e((int) $row['hits']) ?></td><td><?= e((int) $row['unique_ips']) ?></td></tr><?php endforeach; ?>
          </tbody></table>
        </div>
      </div>
      <div class="panel">
        <div class="panel-head"><h2>Eventos por campaña</h2><span>Clicks</span></div>
        <div class="table-wrap">
          <table><thead><tr><th>Campaña</th><th>Evento</th><th>Clics</th><th>Únicos</th></tr></thead><tbody>
            <?php foreach (($data['campaign_events'] ?? []) as $row) : ?><tr><td><?= e($row['camp_slug']) ?></td><td><?= e($row['event_label']) ?></td><td><?= e((int) $row['hits']) ?></td><td><?= e((int) $row['unique_ips']) ?></td></tr><?php endforeach; ?>
          </tbody></table>
        </div>
      </div>
      <div class="panel">
        <div class="panel-head"><h2>Inmuebles por campaña</h2><span>Vistas</span></div>
        <div class="table-wrap">
          <table><thead><tr><th>Campaña</th><th>Ref</th><th>Vistas</th><th>Únicos</th></tr></thead><tbody>
            <?php foreach (($data['campaign_properties'] ?? []) as $row) : $propertyHref = $externalUrl((string) ($row['page_url'] ?? '')); ?><tr><td><?= e($row['camp_slug']) ?></td><td><?php if ($propertyHref) : ?><a class="table-link" href="<?= e($propertyHref) ?>" target="_blank" rel="noopener noreferrer"><?= e($row['object_ref'] ?: $row['object_id']) ?></a><?php else : ?><?= e($row['object_ref'] ?: $row['object_id']) ?><?php endif; ?></td><td><?= e((int) $row['views']) ?></td><td><?= e((int) $row['unique_ips']) ?></td></tr><?php endforeach; ?>
          </tbody></table>
        </div>
      </div>
      <div class="panel">
        <div class="panel-head"><h2>Ubicación por campaña</h2><span>Ciudad / país</span></div>
        <div class="table-wrap">
          <table><thead><tr><th>Campaña</th><th>Ciudad</th><th>País</th><th>Hits</th></tr></thead><tbody>
            <?php foreach (($data['campaign_locations'] ?? []) as $row) : ?><tr><td><?= e($row['camp_slug']) ?></td><td><?= e($row['city']) ?></td><td><?= e($row['country']) ?></td><td><?= e((int) $row['hits']) ?></td></tr><?php endforeach; ?>
          </tbody></table>
        </div>
      </div>
    </div>
  </section>

  <section class="analytics-pane <?= $tab === 'inmuebles' ? 'active' : '' ?>" data-analytics-pane="inmuebles">
    <div class="panel">
      <div class="panel-head"><h2>Inmuebles más vistos</h2><span><?= e((int) $data['propertyPagination']['total']) ?> registros</span></div>
      <div class="table-wrap">
        <table><thead><tr><th>ID</th><th>Referencia</th><th>Vistas</th><th>Únicos</th><th>Última vista</th><th></th></tr></thead><tbody>
          <?php foreach ($data['properties'] as $row) : $propertyHref = $externalUrl((string) ($row['page_url'] ?? '')); ?><tr><td><?= e((int) $row['object_id']) ?></td><td><strong><?= e($row['object_ref']) ?></strong></td><td><?= e((int) $row['views']) ?></td><td><?= e((int) $row['unique_ips']) ?></td><td><?= e($row['last_view']) ?></td><td><?php if ($propertyHref) : ?><a class="button compact" href="<?= e($propertyHref) ?>" target="_blank" rel="noopener noreferrer">Ver inmueble</a><?php else : ?><span class="muted-action">Sin URL</span><?php endif; ?></td></tr><?php endforeach; ?>
        </tbody></table>
      </div>
    </div>
    <?php if (($data['propertyPagination']['pages'] ?? 1) > 1) : ?>
      <div class="pagination">
        <?php if ($data['propertyPagination']['page'] > 1) : ?><a href="<?= e($pageUrl('inmuebles', (int) $data['propertyPagination']['page'] - 1)) ?>">Anterior</a><?php endif; ?>
        <span>Página <?= e((int) $data['propertyPagination']['page']) ?> de <?= e((int) $data['propertyPagination']['pages']) ?></span>
        <?php if ($data['propertyPagination']['page'] < $data['propertyPagination']['pages']) : ?><a href="<?= e($pageUrl('inmuebles', (int) $data['propertyPagination']['page'] + 1)) ?>">Siguiente</a><?php endif; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="analytics-pane <?= $tab === 'eventos' ? 'active' : '' ?>" data-analytics-pane="eventos">
    <div class="panel">
      <div class="panel-head"><h2>Eventos de clic</h2><span><?= e(count($data['events'])) ?> eventos</span></div>
      <div class="table-wrap">
        <table><thead><tr><th>Etiqueta</th><th>Clics</th><th>Usuarios únicos</th><th>Último evento</th></tr></thead><tbody>
          <?php foreach ($data['events'] as $row) : ?><tr><td><strong><?= e($row['event_label']) ?></strong></td><td><?= e((int) $row['hits']) ?></td><td><?= e((int) $row['unique_ips']) ?></td><td><?= e($row['last_event']) ?></td></tr><?php endforeach; ?>
        </tbody></table>
      </div>
    </div>
  </section>

  <section class="analytics-pane <?= $tab === 'logs' ? 'active' : '' ?>" data-analytics-pane="logs">
    <div class="panel">
      <div class="panel-head"><h2>Historial detallado</h2><span><?= e((int) $data['logPagination']['total']) ?> logs</span></div>
      <div class="table-wrap">
        <table><thead><tr><th>Fecha</th><th>Tipo</th><th>Campaña</th><th>Fuente</th><th>Medio</th><th>Objeto</th><th>Ubicación</th><th>URL</th><th>Usuario</th></tr></thead><tbody>
          <?php foreach ($data['logs'] as $row) : ?>
            <tr>
              <td><?= e($row['fecha_visita']) ?></td>
              <td><?= e($row['event_type'] === 'event' ? ('Evt: ' . $row['event_label']) : $row['event_type']) ?></td>
              <td><?= e($row['camp_slug']) ?></td>
              <td><?= e($row['utm_source'] ?: '-') ?></td>
              <td><?= e($row['utm_medium'] ?: '-') ?></td>
              <td><?= e(trim((string) $row['object_ref']) !== '' ? $row['object_ref'] : $row['object_id']) ?></td>
              <td><?= e(trim(($row['city'] ?? '') . ', ' . ($row['country'] ?? ''), ', ')) ?></td>
              <td><?php $logHref = $externalUrl((string) ($row['page_url'] ?? '')); ?><?php if ($logHref) : ?><a class="table-link" href="<?= e($logHref) ?>" target="_blank" rel="noopener noreferrer" title="<?= e($logHref) ?>"><?= e($shortUrl($logHref)) ?></a><?php else : ?>-<?php endif; ?></td>
              <td><?= ((int) $row['user_id']) > 0 ? 'Registrado' : 'Visitante' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody></table>
      </div>
    </div>
    <?php if (($data['logPagination']['pages'] ?? 1) > 1) : ?>
      <div class="pagination">
        <?php if ($data['logPagination']['page'] > 1) : ?><a href="<?= e($pageUrl('logs', (int) $data['logPagination']['page'] - 1)) ?>">Anterior</a><?php endif; ?>
        <span>Página <?= e((int) $data['logPagination']['page']) ?> de <?= e((int) $data['logPagination']['pages']) ?></span>
        <?php if ($data['logPagination']['page'] < $data['logPagination']['pages']) : ?><a href="<?= e($pageUrl('logs', (int) $data['logPagination']['page'] + 1)) ?>">Siguiente</a><?php endif; ?>
      </div>
    <?php endif; ?>
  </section>
</div>
