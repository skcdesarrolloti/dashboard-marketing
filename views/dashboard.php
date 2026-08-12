<header class="page-head">
  <div>
    <h1>Dashboard de promoción</h1>
    <p>Actores, campañas, actividad y analíticas en una sola vista.</p>
  </div>
  <form class="inline-filters" method="get">
    <input type="date" name="from" value="<?= e($from) ?>">
    <input type="date" name="to" value="<?= e($to) ?>">
    <button>Filtrar</button>
    <a class="button ghost" href="<?= e(url()) ?>">Limpiar</a>
  </form>
</header>

<?php if ($from === '' && $to === '') : ?>
  <div class="notice">Inicio sin filtro de fecha: mostrando todo el histórico disponible.</div>
<?php endif; ?>

<section class="metrics">
  <article><span>Hits</span><strong><?= e((int) ($analytics['totals']['hits'] ?? 0)) ?></strong></article>
  <article><span>Usuarios únicos</span><strong><?= e((int) ($analytics['totals']['unique_ips'] ?? 0)) ?></strong></article>
  <article><span>Sesiones portal</span><strong><?= e((int) ($activity['sessions']['sesiones'] ?? 0)) ?></strong></article>
  <article><span>Campañas</span><strong><?= e(count($campaigns)) ?></strong></article>
</section>

<section class="grid two">
  <div class="panel">
    <div class="panel-head"><h2>Fuentes principales</h2><a href="<?= e(url(['page' => 'analiticas'])) ?>">Ver analíticas</a></div>
    <table>
      <thead><tr><th>Fuente</th><th>Hits</th></tr></thead>
      <tbody>
      <?php foreach (($analytics['sources'] ?? []) as $row) : ?>
        <tr><td><?= e($row['source']) ?></td><td><?= e((int) $row['hits']) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="panel">
    <div class="panel-head"><h2>Últimas campañas</h2><a href="<?= e(url(['page' => 'campanas'])) ?>">Ver campañas</a></div>
    <table>
      <thead><tr><th>Campaña</th><th>Pendientes por enviar</th><th>Enviados</th><th>Fallidos</th></tr></thead>
      <tbody>
      <?php foreach (array_slice($campaigns, 0, 8) as $row) : ?>
        <tr><td><?= e($row['tag']) ?></td><td><?= e((int) ($row['pending_total'] ?? 0)) ?></td><td><?= e((int) ($row['sent_total'] ?? 0)) ?></td><td><?= e((int) ($row['failed_total'] ?? 0)) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
