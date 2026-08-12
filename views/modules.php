<header class="page-head hero-head">
  <div>
    <span class="eyebrow">Unificación SKC</span>
    <h1>Módulos conectados</h1>
    <p>Mapa operativo de proyectos revisados y piezas integradas al dashboard de promoción.</p>
  </div>
</header>

<section class="modules-grid">
  <?php foreach ($modules as $module) : ?>
    <article class="module-card <?= $module['exists'] ? '' : 'muted-card' ?>">
      <div>
        <span class="status <?= str_contains(strtolower($module['status']), 'integrado') ? 'sent' : 'pending' ?>"><?= e($module['status']) ?></span>
        <h2><?= e($module['name']) ?></h2>
        <p><?= e($module['detail']) ?></p>
      </div>
      <code><?= e($module['exists'] ? $module['path'] : 'No encontrado') ?></code>
    </article>
  <?php endforeach; ?>
</section>
