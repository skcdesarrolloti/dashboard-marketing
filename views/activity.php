<?php
$selectedActor = (string) ($data['selected_actor'] ?? $actor ?? '');
$selectedUsuario = (string) ($data['selected_usuario'] ?? $usuario ?? '');
$selectedNombre = (string) ($data['selected_nombre'] ?? $nombre ?? '');
$selectedMenu = (string) ($data['selected_menu'] ?? $menu ?? '');
$actorTabs = (array) ($data['actor_tabs'] ?? []);
$tabTotal = array_reduce($actorTabs, static fn (int $carry, array $row): int => $carry + (int) ($row['total'] ?? 0), 0);
$activityTabUrl = static function (string $actorValue = '') use ($from, $to): string {
    $params = ['page' => 'actividad', 'from' => $from, 'to' => $to];
    if ($actorValue !== '') {
        $params['actor'] = $actorValue;
    }
    return url($params);
};
$formatSessionDate = static function (mixed $value): string {
    $timestamp = (int) $value;
    return $timestamp > 0 ? date('Y-m-d', $timestamp) : '';
};
?>
<header class="page-head">
  <div>
    <h1>Actividad de portales</h1>
    <p>Sesiones, accesos a menús y navegación reciente.</p>
  </div>
</header>

<?php if (!($data['access_table_exists'] ?? false)) : ?>
  <div class="notice">
    La tabla <strong>wp_jet_cct_accesos_portal</strong> aún no existe. Cuando exista, aquí aparecerán menús, rutas y accesos recientes.
  </div>
<?php endif; ?>

<?php if ($actorTabs !== []) : ?>
  <nav class="activity-actor-tabs" aria-label="Actividad por tipo de actor">
    <a class="<?= $selectedActor === '' ? 'active' : '' ?>" href="<?= e($activityTabUrl()) ?>">
      <strong>Todos</strong>
      <span><?= e($tabTotal) ?> eventos</span>
    </a>
    <?php foreach ($actorTabs as $tab) : $tabActor = (string) ($tab['tipo_actor'] ?? ''); ?>
      <a class="<?= $selectedActor === $tabActor ? 'active' : '' ?>" href="<?= e($activityTabUrl($tabActor)) ?>">
        <strong><?= e($tabActor) ?></strong>
        <span><?= e((int) ($tab['sesiones'] ?? 0)) ?> sesiones · <?= e((int) ($tab['accesos'] ?? 0)) ?> accesos</span>
      </a>
    <?php endforeach; ?>
  </nav>
<?php endif; ?>

<form class="inline-filters activity-filters" method="get">
  <input type="hidden" name="page" value="actividad">
  <?php if ($actorTabs !== [] && $selectedActor !== '') : ?>
    <input type="hidden" name="actor" value="<?= e($selectedActor) ?>">
  <?php endif; ?>
  <input type="date" name="from" value="<?= e($from) ?>">
  <input type="date" name="to" value="<?= e($to) ?>">
  <input type="search" name="usuario" value="<?= e($selectedUsuario) ?>" placeholder="Usuario" aria-label="Usuario">
  <input type="search" name="nombre" value="<?= e($selectedNombre) ?>" placeholder="Nombre" aria-label="Nombre">
  <input type="search" name="menu" value="<?= e($selectedMenu) ?>" placeholder="Menú" aria-label="Menú">
  <?php if ($actorTabs === [] || $selectedActor === '') : ?>
    <select name="actor" aria-label="Actor">
      <option value="">Todos los actores</option>
      <?php foreach (($data['actor_options'] ?? []) as $option) : $value = (string) ($option['tipo_actor'] ?? ''); ?>
        <option value="<?= e($value) ?>" <?= $selectedActor === $value ? 'selected' : '' ?>><?= e($value) ?></option>
      <?php endforeach; ?>
    </select>
  <?php endif; ?>
  <button>Filtrar</button>
  <a class="button ghost" href="<?= e(url(['page' => 'actividad'])) ?>">Limpiar</a>
</form>

<section class="metrics">
  <article><span>Usuarios con login</span><strong><?= e((int) ($data['sessions']['usuarios'] ?? 0)) ?></strong></article>
  <article><span>Sesiones acumuladas</span><strong><?= e((int) ($data['sessions']['sesiones'] ?? 0)) ?></strong></article>
  <article><span>Usuarios en rango</span><strong><?= e((int) ($data['recent_sessions']['usuarios_recientes'] ?? 0)) ?></strong></article>
  <article><span>Accesos a menús</span><strong><?= e((int) ($data['access']['accesos'] ?? 0)) ?></strong></article>
</section>

<section class="grid two">
  <div class="panel">
    <div class="panel-head"><h2>Sesiones por actor</h2><span>Tabla sesiones</span></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Actor</th><th>Usuarios</th><th>Sesiones</th><th>Último login</th></tr></thead>
        <tbody>
          <?php foreach (($data['by_actor'] ?? []) as $row) : ?>
            <tr>
              <td><?= e($row['tipo_actor']) ?></td>
              <td><?= e((int) $row['usuarios']) ?></td>
              <td><?= e((int) $row['sesiones']) ?></td>
              <td><?= e($formatSessionDate($row['ultima_fecha'] ?? 0) ?: '-') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head"><h2>Menús más visitados</h2><span><?= e($from) ?> a <?= e($to) ?></span></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Proyecto</th><th>Menú</th><th>Accesos</th><th>Usuarios únicos</th></tr></thead>
        <tbody>
          <?php foreach (($data['menus'] ?? []) as $row) : ?>
            <tr>
              <td><?= e($row['proyecto']) ?></td>
              <td><?= e($row['menu']) ?></td>
              <td><?= e((int) $row['accesos']) ?></td>
              <td><?= e((int) $row['usuarios_unicos']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>

<section class="grid two">
  <div class="panel">
    <div class="panel-head"><h2>Usuarios con más sesiones</h2><span>Top 12</span></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Actor</th><th>Usuario</th><th>Nombre</th><th>Sesiones</th><th>Último login</th></tr></thead>
        <tbody>
          <?php foreach (($data['top_users'] ?? []) as $row) : ?>
            <tr>
              <td><?= e($row['tipo_actor']) ?></td>
              <td><?= e($row['usuario']) ?></td>
              <td><?= e($row['nombre']) ?></td>
              <td><?= e((int) $row['cantidad_sesiones']) ?></td>
              <td><?= e($formatSessionDate($row['fecha'] ?? 0) ?: '-') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head"><h2>Accesos por actor</h2><span><?= e($from) ?> a <?= e($to) ?></span></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Actor</th><th>Accesos</th><th>Usuarios únicos</th><th>Último acceso</th></tr></thead>
        <tbody>
          <?php foreach (($data['access_by_actor'] ?? []) as $row) : ?>
            <tr>
              <td><?= e($row['tipo_actor']) ?></td>
              <td><?= e((int) $row['accesos']) ?></td>
              <td><?= e((int) $row['usuarios_unicos']) ?></td>
              <td><?= e($row['ultimo_acceso']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>

<section class="panel">
  <div class="panel-head"><h2>Últimos accesos</h2><span>30 eventos recientes</span></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Fecha</th><th>Proyecto</th><th>Actor</th><th>Usuario</th><th>Nombre</th><th>Menú</th><th>Ruta</th><th>IP</th></tr></thead>
      <tbody>
        <?php foreach (($data['recent'] ?? []) as $row) : ?>
          <tr>
            <td><?= e($row['creado_en']) ?></td>
            <td><?= e($row['proyecto']) ?></td>
            <td><?= e($row['tipo_actor']) ?></td>
            <td><?= e($row['usuario']) ?></td>
            <td><?= e($row['nombre']) ?></td>
            <td><?= e($row['menu']) ?></td>
            <td><?= e($row['ruta']) ?></td>
            <td><?= e($row['ip']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
