<?php
$statusLabels = [
    'idea' => 'Idea',
    'planned' => 'Planeado',
    'scheduled' => 'Programado',
    'published' => 'Publicado',
    'cancelled' => 'Cancelado',
];
$channelLabels = [
    'instagram' => 'Instagram',
    'facebook' => 'Facebook',
    'whatsapp' => 'WhatsApp',
    'email' => 'Email',
    'web' => 'Web',
    'tiktok' => 'TikTok',
    'linkedin' => 'LinkedIn',
    'google' => 'Google',
];
$typeLabels = [
    'post' => 'Post',
    'reel' => 'Reel',
    'story' => 'Historia',
    'ad' => 'Anuncio',
    'email' => 'Email',
    'blog' => 'Blog',
    'landing' => 'Landing',
];
$channelInitials = [
    'instagram' => 'IG',
    'facebook' => 'FB',
    'whatsapp' => 'WA',
    'email' => 'EM',
    'web' => 'WEB',
    'tiktok' => 'TT',
    'linkedin' => 'IN',
    'google' => 'GG',
];
$value = static fn (string $key, string $fallback = ''): string => (string) ($edit[$key] ?? $fallback);
$monthNames = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
$dayNames = [1 => 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
$weekLabel = $weekStart->format('j') . ' ' . ucfirst($monthNames[(int) $weekStart->format('n')]) . ' - ' . $weekEnd->format('j') . ' ' . ucfirst($monthNames[(int) $weekEnd->format('n')]) . ' ' . $weekEnd->format('Y');
$prevWeek = $weekStart->modify('-7 days')->format('Y-m-d');
$nextWeek = $weekStart->modify('+7 days')->format('Y-m-d');
$todayWeek = (new DateTimeImmutable())->modify('monday this week')->format('Y-m-d');
$itemsByDate = [];
foreach ($calendarItems as $item) {
    $itemsByDate[(string) ($item['date'] ?? '')][] = $item;
}
$days = [];
$cursor = new DateTimeImmutable($calendarFrom);
$end = new DateTimeImmutable($calendarTo);
while ($cursor <= $end) {
    $days[] = $cursor;
    $cursor = $cursor->modify('+1 day');
}
$startHour = 8;
$endHour = 19;
$hours = range($startHour, $endHour);
$slotHeight = 76;
$externalUrl = static fn (string $url): string => filter_var($url, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $url) ? $url : '';
$eventTop = static function (array $item) use ($startHour, $endHour, $slotHeight): int {
    $time = (string) ($item['time'] ?? '');
    if (!preg_match('/^(\d{2}):(\d{2})$/', $time, $match)) {
        return 12;
    }
    $hour = max($startHour, min($endHour, (int) $match[1]));
    $minute = max(0, min(59, (int) $match[2]));
    return (int) round((($hour - $startHour) * $slotHeight) + (($minute / 60) * $slotHeight) + 10);
};
$bestPercent = static function (DateTimeImmutable $day, int $hour): int {
    $dayWeight = [1 => 52, 2 => 68, 3 => 74, 4 => 70, 5 => 64, 6 => 45, 7 => 39][(int) $day->format('N')] ?? 50;
    $hourBoost = match (true) {
        $hour >= 11 && $hour <= 13 => 16,
        $hour >= 16 && $hour <= 18 => 22,
        $hour >= 9 && $hour <= 10 => 8,
        default => -8,
    };
    return max(8, min(96, $dayWeight + $hourBoost));
};
$shortText = static fn (string $text, int $limit = 82): string => mb_strlen($text) > $limit ? mb_substr($text, 0, $limit - 1) . '…' : $text;
?>

<header class="planner-hero">
  <div>
    <span class="eyebrow">Planificación de marketing</span>
    <h1>Planificador</h1>
    <p>Calendario semanal para programar, revisar y medir publicaciones por canal.</p>
  </div>
  <div class="planner-clock" aria-label="Hora local">
    <span><?= e(date('H:i')) ?></span>
    <small>America/Bogota</small>
  </div>
</header>

<?php if (isset($_GET['saved'])) : ?><div class="notice">Registro guardado en base de datos.</div><?php endif; ?>
<?php if (isset($_GET['deleted'])) : ?><div class="notice">Registro eliminado.</div><?php endif; ?>

<section class="planner-console" data-planner-root>
  <nav class="planner-tabs" aria-label="Vistas del planificador">
    <button class="active" type="button" data-planner-tab="calendar">Calendario</button>
    <button type="button" data-planner-tab="list">Listado</button>
    <button type="button" data-planner-tab="autolists">Autolistas</button>
  </nav>

  <form class="planner-toolbar" method="get" data-autosubmit>
    <input type="hidden" name="page" value="planificador">
    <input type="hidden" name="week" value="<?= e($week) ?>">
    <div class="planner-search">
      <label class="sr-only" for="planner-search">Buscar</label>
      <input id="planner-search" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Buscar publicación, campaña o responsable">
    </div>
    <a class="planner-tool-button" href="<?= e(url(['page' => 'planificador', 'week' => $todayWeek])) ?>">Hoy</a>
    <div class="planner-week-switch">
      <a aria-label="Semana anterior" href="<?= e(url(array_merge($_GET, ['page' => 'planificador', 'week' => $prevWeek]))) ?>">‹</a>
      <strong><?= e($weekLabel) ?></strong>
      <a aria-label="Semana siguiente" href="<?= e(url(array_merge($_GET, ['page' => 'planificador', 'week' => $nextWeek]))) ?>">›</a>
    </div>
    <select name="status" aria-label="Estado">
      <option value="">Todos los estados</option>
      <?php foreach ($statusLabels as $key => $label) : ?><option value="<?= e($key) ?>" <?= ($filters['status'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
    </select>
    <select name="channel" aria-label="Canal">
      <option value="">Todos los canales</option>
      <?php foreach ($channelLabels as $key => $label) : ?><option value="<?= e($key) ?>" <?= ($filters['channel'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
    </select>
    <div class="planner-best-times">
      <button class="planner-tool-button" type="button" data-best-times-toggle aria-expanded="false">Ver mejores horas</button>
      <div class="planner-best-menu" data-best-times-menu hidden>
        <button type="button" data-best-channel="">Ninguna</button>
        <?php foreach ($channelLabels as $key => $label) : ?><button type="button" data-best-channel="<?= e($key) ?>"><?= e($label) ?></button><?php endforeach; ?>
        <label class="planner-toggle"><span>Mostrar porcentaje</span><input type="checkbox" data-best-percent></label>
      </div>
    </div>
    <button class="planner-primary-action" type="button" data-modal-open="planner-post-modal">+ Crear publicación</button>
  </form>

  <section class="planner-metric-strip" aria-label="Resumen del planificador">
    <article><span>Total</span><strong><?= e((int) ($summary['total'] ?? 0)) ?></strong></article>
    <article><span>Programados</span><strong><?= e((int) ($summary['scheduled'] ?? 0)) ?></strong></article>
    <article><span>Publicados</span><strong><?= e((int) ($summary['published'] ?? 0)) ?></strong></article>
    <article><span>Interacciones</span><strong><?= e((int) ($summary['interactions'] ?? 0)) ?></strong></article>
  </section>

  <section class="planner-view active" data-planner-view="calendar">
    <div class="planner-week-calendar best-times-off hide-percent" style="--slot-height: <?= e($slotHeight) ?>px; --hour-count: <?= e(count($hours)) ?>;">
      <aside class="planner-time-axis" aria-hidden="true">
        <div class="planner-time-head"></div>
        <?php foreach ($hours as $hour) : ?><span><?= e(sprintf('%02d:00', $hour)) ?></span><?php endforeach; ?>
      </aside>
      <div class="planner-week-scroll">
        <div class="planner-week-columns">
          <?php foreach ($days as $day) : ?>
            <?php $date = $day->format('Y-m-d'); $dayItems = $itemsByDate[$date] ?? []; ?>
            <section class="planner-week-day <?= $date === date('Y-m-d') ? 'today' : '' ?>">
              <header class="planner-week-day-head">
                <span><?= e($dayNames[(int) $day->format('N')]) ?></span>
                <strong><?= e($day->format('d')) ?></strong>
                <small><?= e(count($dayItems)) ?> posts</small>
              </header>
              <div class="planner-day-column" data-planner-date="<?= e($date) ?>">
                <div class="planner-best-layer">
                  <?php foreach ($hours as $hour) : ?>
                    <?php $percent = $bestPercent($day, $hour); ?>
                    <button type="button" class="planner-best-slot" data-planner-slot data-time="<?= e(sprintf('%02d:00', $hour)) ?>" style="--heat: <?= e($percent / 100) ?>;">
                      <span><?= e($percent) ?>%</span>
                    </button>
                  <?php endforeach; ?>
                </div>
                <div class="planner-hour-lines" aria-hidden="true">
                  <?php foreach ($hours as $_hour) : ?><span></span><?php endforeach; ?>
                </div>
                <?php foreach ($dayItems as $item) : ?>
                  <?php $title = (string) ($item['title'] ?: ($item['campaign'] ?: 'Sin título')); ?>
                  <a class="planner-post-card channel-<?= e((string) ($item['channel'] ?? 'instagram')) ?> status-<?= e((string) ($item['status'] ?? 'planned')) ?>" style="top: <?= e($eventTop($item)) ?>px;" href="<?= e(url(['page' => 'planificador', 'week' => $week, 'edit' => (int) ($item['id'] ?? 0)])) ?>">
                    <span class="planner-post-top"><b><?= e($channelInitials[$item['channel']] ?? strtoupper((string) $item['channel'])) ?></b><time><?= e($item['time'] ?: 'Sin hora') ?></time></span>
                    <strong><?= e($shortText($title, 58)) ?></strong>
                    <small><?= e($shortText((string) ($item['copy'] ?: $item['campaign'] ?: $item['notes'] ?: 'Sin descripción'))) ?></small>
                    <span class="planner-post-foot"><?= e($typeLabels[$item['type']] ?? $item['type']) ?> · <?= e($statusLabels[$item['status']] ?? $item['status']) ?></span>
                  </a>
                <?php endforeach; ?>
              </div>
            </section>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </section>

  <section class="planner-view" data-planner-view="list" hidden>
    <div class="planner-log">
      <div class="panel-head"><h2>Registro de publicaciones</h2><span><?= e(count($items)) ?> registros filtrados</span></div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Fecha</th><th>Contenido</th><th>Campaña</th><th>Canal</th><th>Estado</th><th>Responsable</th><th>Resultados</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($items as $item) : ?>
            <tr>
              <td><strong><?= e($item['date']) ?></strong><br><small><?= e($item['time'] ?: 'Sin hora') ?></small></td>
              <td><strong><?= e($item['title'] ?: 'Sin título') ?></strong><br><small><?= e($typeLabels[$item['type']] ?? $item['type']) ?></small></td>
              <td><?= e($item['campaign'] ?: '-') ?></td>
              <td><span class="badge"><?= e($channelLabels[$item['channel']] ?? $item['channel']) ?></span></td>
              <td><span class="status <?= e((string) $item['status']) ?>"><?= e($statusLabels[$item['status']] ?? $item['status']) ?></span></td>
              <td><?= e($item['owner'] ?: '-') ?></td>
              <td><?= e((int) $item['views']) ?> vistas · <?= e((int) $item['interactions']) ?> int.</td>
              <td class="actions">
                <?php if ($externalUrl((string) ($item['post_url'] ?? ''))) : ?><a class="button slim" href="<?= e($item['post_url']) ?>" target="_blank" rel="noopener noreferrer">Ver</a><?php endif; ?>
                <a class="button slim ghost" href="<?= e(url(['page' => 'planificador', 'week' => $week, 'edit' => (int) $item['id']])) ?>">Editar</a>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$items) : ?><tr><td colspan="8"><div class="empty-state">No hay publicaciones para los filtros actuales.</div></td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <section class="planner-view" data-planner-view="autolists" hidden>
    <div class="planner-autolists">
      <?php foreach (['idea' => 'Ideas por convertir', 'planned' => 'Pendiente por aprobar', 'scheduled' => 'En programación', 'published' => 'Publicado y medible'] as $status => $label) : ?>
        <article>
          <header><h2><?= e($label) ?></h2><span><?= e((int) ($summary[$status] ?? 0)) ?></span></header>
          <?php foreach (array_slice(array_values(array_filter($items, static fn ($item) => ($item['status'] ?? '') === $status)), 0, 6) as $item) : ?>
            <a href="<?= e(url(['page' => 'planificador', 'week' => $week, 'edit' => (int) $item['id']])) ?>">
              <strong><?= e($item['title'] ?: $item['campaign'] ?: 'Sin título') ?></strong>
              <small><?= e($item['date']) ?> · <?= e($channelLabels[$item['channel']] ?? $item['channel']) ?></small>
            </a>
          <?php endforeach; ?>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
</section>

<div class="modal planner-post-modal" id="planner-post-modal" <?= ($edit || isset($_GET['new'])) ? '' : 'hidden' ?>>
  <div class="modal-backdrop" data-modal-close></div>
  <section class="modal-panel planner-post-panel" role="dialog" aria-modal="true" aria-labelledby="planner-post-title">
    <button type="button" class="modal-close" data-modal-close aria-label="Cerrar">&times;</button>
    <div class="modal-content">
      <div class="planner-form-panel" id="planner-form">
        <div class="panel-head"><h2 id="planner-post-title"><?= $edit ? 'Editar publicación' : 'Nueva publicación' ?></h2><span>CRUD gerencial</span></div>
        <form method="post" class="planner-form">
          <input type="hidden" name="action" value="save_planner_post">
          <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="id" value="<?= e($value('id')) ?>">
          <div class="planner-form-grid">
            <label>Fecha<input type="date" name="date" value="<?= e($value('date', date('Y-m-d'))) ?>" required></label>
            <label>Hora<input type="time" name="time" value="<?= e($value('time', '09:00')) ?>"></label>
          </div>
          <div class="planner-form-grid">
            <label>Estado<select name="status"><?php foreach ($statusLabels as $key => $label) : ?><option value="<?= e($key) ?>" <?= $value('status', 'planned') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
            <label>Canal<select name="channel"><?php foreach ($channelLabels as $key => $label) : ?><option value="<?= e($key) ?>" <?= $value('channel', 'instagram') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
          </div>
          <div class="planner-form-grid">
            <label>Tipo<select name="type"><?php foreach ($typeLabels as $key => $label) : ?><option value="<?= e($key) ?>" <?= $value('type', 'post') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
            <label>Campaña<input name="campaign" value="<?= e($value('campaign')) ?>" placeholder="feria_vivienda_2026_leads"></label>
          </div>
          <label>Título<input name="title" value="<?= e($value('title')) ?>" placeholder="Publicación para promocionar..."></label>
          <label>Responsable<input name="owner" value="<?= e($value('owner', (string) (App\Auth::user()['nombre'] ?? ''))) ?>"></label>
          <label>Copy / contenido<textarea name="copy" rows="5" placeholder="Texto del post, reel, historia o correo"><?= e($value('copy')) ?></textarea></label>
          <div class="planner-form-grid">
            <label>URL asset / pieza<input name="asset_url" value="<?= e($value('asset_url')) ?>" placeholder="https://..."></label>
            <label>URL publicada<input name="post_url" value="<?= e($value('post_url')) ?>" placeholder="https://..."></label>
          </div>
          <div class="planner-form-grid three">
            <label>Alcance<input type="number" min="0" name="reach" value="<?= e($value('reach', '0')) ?>"></label>
            <label>Vistas<input type="number" min="0" name="views" value="<?= e($value('views', '0')) ?>"></label>
            <label>Interacciones<input type="number" min="0" name="interactions" value="<?= e($value('interactions', '0')) ?>"></label>
          </div>
          <label>Notas<textarea name="notes" rows="3" placeholder="Pendientes, aprobación, aprendizajes"><?= e($value('notes')) ?></textarea></label>
          <div class="planner-form-actions">
            <button class="primary"><?= $edit ? 'Guardar cambios' : 'Crear registro' ?></button>
            <?php if ($edit) : ?><a class="button ghost" href="<?= e(url(['page' => 'planificador', 'week' => $week])) ?>">Nuevo</a><?php endif; ?>
          </div>
        </form>
        <?php if ($edit) : ?>
          <form method="post" class="planner-delete-form" onsubmit="return confirm('¿Eliminar este registro del planificador?');">
            <input type="hidden" name="action" value="delete_planner_post">
            <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= e((int) ($edit['id'] ?? 0)) ?>">
            <button class="button danger">Eliminar registro</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </section>
</div>
