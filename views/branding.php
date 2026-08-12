<?php

$assets = (array) ($data['assets'] ?? []);
$summary = (array) ($data['summary'] ?? []);
$channelCounts = (array) ($data['channelCounts'] ?? []);
$categoryCounts = (array) ($data['categoryCounts'] ?? []);
$topAssets = (array) ($data['topAssets'] ?? []);
$socialPosts = (array) ($socialPosts ?? []);
$linkAssetId = max(0, (int) ($linkAssetId ?? 0));
$editing = (array) ($edit ?? []);
$selectedChannels = array_map('strval', (array) ($editing['channels'] ?? []));
$isEditing = (int) ($editing['id'] ?? 0) > 0;
$maxChannel = max(1, ...array_values($channelCounts ?: [1]));
$maxCategory = max(1, ...array_values($categoryCounts ?: [1]));
$formatNumber = static fn (mixed $value): string => number_format((int) $value, 0, ',', '.');
$formatDate = static function (mixed $value): string {
    $timestamp = strtotime((string) $value);
    return $timestamp ? date('d/m/Y', $timestamp) : 'Sin fecha';
};
$fileSize = static function (mixed $bytes): string {
    $bytes = (int) $bytes;
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 0, ',', '.') . ' KB';
    return $bytes . ' B';
};
$statusClass = static fn (string $status): string => preg_replace('/[^a-z_]/', '', $status) ?: 'designed';
$platformLabels = ['instagram' => 'Instagram', 'facebook' => 'Facebook'];
$safeExternalUrl = static function (mixed $value): string {
    $value = trim((string) $value);
    return filter_var($value, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $value) ? $value : '';
};
?>

<section class="branding-hero">
  <div class="branding-hero-copy">
    <span class="branding-eyebrow">Ecosistema visual de marca</span>
    <h1>Banco de Piezas de Branding</h1>
    <p>Centraliza, clasifica y sigue cada pieza del cambio de imagen de SK&amp;C SuCasa Inmobiliaria, desde el arte final hasta su implementación e impacto comercial.</p>
    <div class="branding-hero-meta" aria-label="Resumen del módulo">
      <span><strong><?= e((int) ($summary['total'] ?? 0)) ?></strong> piezas activas</span>
      <span><strong><?= e((int) ($summary['branding_progress'] ?? 0)) ?>%</strong> implementado</span>
      <span><strong><?= e((int) ($summary['visual_consistency'] ?? 0)) ?>%</strong> coherencia visual</span>
    </div>
  </div>
  <?php if ($canManage) : ?>
    <button class="branding-primary-action" type="button" data-open-branding-modal>
      <span class="branding-action-icon" aria-hidden="true"></span>
      <?= $isEditing ? 'Continuar edición' : 'Subir nueva pieza' ?>
    </button>
  <?php endif; ?>
</section>

<?php if ($error) : ?><div class="branding-alert is-error" role="alert"><?= e($error) ?></div><?php endif; ?>
<?php if ($success) : ?><div class="branding-alert is-success" role="status"><?= e($success) ?></div><?php endif; ?>

<section class="branding-metrics" aria-label="Indicadores de branding">
  <article>
    <span class="branding-metric-label">Total cargadas</span>
    <strong><?= e($formatNumber($summary['total'] ?? 0)) ?></strong>
    <small>Diseños registrados y activos</small>
  </article>
  <article class="is-positive">
    <span class="branding-metric-label">Aprobadas</span>
    <strong><?= e($formatNumber($summary['approved'] ?? 0)) ?></strong>
    <small>Artes listos para uso</small>
  </article>
  <article class="is-implemented">
    <span class="branding-metric-label">Implementadas</span>
    <strong><?= e($formatNumber($summary['implemented'] ?? 0)) ?></strong>
    <small>Publicadas o utilizadas</small>
  </article>
  <article class="is-pending">
    <span class="branding-metric-label">Pendientes</span>
    <strong><?= e($formatNumber($summary['pending'] ?? 0)) ?></strong>
    <small>Diseño, revisión o ajuste</small>
  </article>
  <article class="branding-progress-card">
    <div class="branding-progress-ring" style="--progress: <?= e((int) ($summary['branding_progress'] ?? 0)) ?>" aria-hidden="true">
      <span><?= e((int) ($summary['branding_progress'] ?? 0)) ?>%</span>
    </div>
    <div><span class="branding-metric-label">Avance de branding</span><small>Implementación del cambio de imagen</small></div>
  </article>
  <article class="branding-progress-card">
    <div class="branding-progress-ring is-consistency" style="--progress: <?= e((int) ($summary['visual_consistency'] ?? 0)) ?>" aria-hidden="true">
      <span><?= e((int) ($summary['visual_consistency'] ?? 0)) ?>%</span>
    </div>
    <div><span class="branding-metric-label">Coherencia visual</span><small>Promedio de cumplimiento de marca</small></div>
  </article>
</section>

<section class="branding-insights">
  <article class="branding-panel">
    <header class="branding-panel-head">
      <div><span>Distribución</span><h2>Piezas por canal</h2></div>
      <small>Una pieza puede estar en varios canales</small>
    </header>
    <div class="branding-bars">
      <?php foreach ($channelCounts as $key => $count) : ?>
        <div class="branding-bar-row">
          <div><span><?= e($channels[$key] ?? $key) ?></span><strong><?= e($formatNumber($count)) ?></strong></div>
          <span class="branding-bar-track" aria-hidden="true"><i style="--bar: <?= e((int) round(((int) $count / $maxChannel) * 100)) ?>%"></i></span>
        </div>
      <?php endforeach; ?>
      <?php if (array_sum($channelCounts) === 0) : ?><p class="branding-empty-copy">Los canales aparecerán cuando registres la primera pieza.</p><?php endif; ?>
    </div>
  </article>

  <article class="branding-panel">
    <header class="branding-panel-head">
      <div><span>Portafolio</span><h2>Composición por categoría</h2></div>
      <small><?= e(count($categoryCounts)) ?> categorías activas</small>
    </header>
    <div class="branding-bars is-category">
      <?php foreach ($categoryCounts as $key => $count) : ?>
        <div class="branding-bar-row">
          <div><span><?= e($categories[$key] ?? $key) ?></span><strong><?= e($formatNumber($count)) ?></strong></div>
          <span class="branding-bar-track" aria-hidden="true"><i style="--bar: <?= e((int) round(((int) $count / $maxCategory) * 100)) ?>%"></i></span>
        </div>
      <?php endforeach; ?>
      <?php if ($categoryCounts === []) : ?><p class="branding-empty-copy">Aún no hay categorías con piezas activas.</p><?php endif; ?>
    </div>
  </article>

  <article class="branding-panel branding-top-panel">
    <header class="branding-panel-head">
      <div><span>Impacto</span><h2>Piezas con mejor resultado</h2></div>
      <small>Alcance, interacción y contactos</small>
    </header>
    <ol class="branding-ranking">
      <?php foreach ($topAssets as $index => $asset) : ?>
        <li>
          <span class="branding-rank-number"><?= e($index + 1) ?></span>
          <div><strong><?= e($asset['title'] ?? '') ?></strong><small><?= e($categories[$asset['category'] ?? ''] ?? '') ?></small></div>
          <span class="branding-rank-score"><?= e($formatNumber($asset['impact_score'] ?? 0)) ?><small>impacto</small></span>
        </li>
      <?php endforeach; ?>
      <?php if ($topAssets === []) : ?><li class="branding-empty-copy">Registra indicadores para identificar las piezas más efectivas.</li><?php endif; ?>
    </ol>
  </article>
</section>

<section class="branding-library">
  <header class="branding-library-head">
    <div>
      <span class="branding-eyebrow">Biblioteca visual</span>
      <h2>Piezas registradas</h2>
      <p>Consulta artes, responsables, estado e impacto desde un solo lugar.</p>
    </div>
    <span class="branding-result-count"><?= e(count($assets)) ?> resultados</span>
  </header>

  <form method="get" class="branding-filters" aria-label="Filtros del Banco de Piezas">
    <input type="hidden" name="page" value="branding">
    <label class="branding-search-field">
      <span>Buscar</span>
      <input name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Nombre, código, campaña o diseñador">
    </label>
    <label><span>Categoría</span><select name="category"><option value="">Todas</option><?php foreach ($categories as $key => $label) : ?><option value="<?= e($key) ?>" <?= ($filters['category'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
    <label><span>Estado</span><select name="status"><option value="">Todos</option><?php foreach ($statuses as $key => $label) : ?><option value="<?= e($key) ?>" <?= ($filters['status'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
    <label><span>Canal</span><select name="channel"><option value="">Todos</option><?php foreach ($channels as $key => $label) : ?><option value="<?= e($key) ?>" <?= ($filters['channel'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
    <label><span>Desde</span><input type="date" name="from" value="<?= e($filters['from'] ?? '') ?>"></label>
    <label><span>Hasta</span><input type="date" name="to" value="<?= e($filters['to'] ?? '') ?>"></label>
    <label><span>Visibilidad</span><select name="active"><option value="1" <?= ($filters['active'] ?? '1') === '1' ? 'selected' : '' ?>>Activas</option><option value="0" <?= ($filters['active'] ?? '') === '0' ? 'selected' : '' ?>>Archivadas</option><option value="all" <?= ($filters['active'] ?? '') === 'all' ? 'selected' : '' ?>>Todas</option></select></label>
    <div class="branding-filter-actions"><button type="submit">Aplicar filtros</button><a class="button ghost" href="<?= e(url_page('branding')) ?>">Limpiar</a></div>
  </form>

  <div class="branding-grid">
    <?php foreach ($assets as $asset) :
      $mime = (string) ($asset['mime_type'] ?? '');
      $isImage = str_starts_with($mime, 'image/');
      $isVideo = str_starts_with($mime, 'video/');
      $extension = strtolower(pathinfo((string) ($asset['original_name'] ?? ''), PATHINFO_EXTENSION));
      $isPdf = $mime === 'application/pdf' || $extension === 'pdf';
      $fileUrl = url(['action' => 'branding-file', 'id' => (int) $asset['id']]);
      $downloadUrl = url(['action' => 'branding-file', 'id' => (int) $asset['id'], 'download' => 1]);
      $linkedPosts = (array) ($asset['linked_posts'] ?? []);
      $socialKpis = (array) ($asset['social_kpis'] ?? []);
      $linkedPostIds = array_map('intval', array_column($linkedPosts, '_ID'));
    ?>
      <article class="branding-piece-card <?= empty($asset['active']) ? 'is-archived' : '' ?>">
        <div class="branding-piece-preview">
          <?php if ($isImage) : ?>
            <img src="<?= e($fileUrl) ?>" alt="Vista previa de <?= e($asset['title']) ?>" loading="lazy" width="600" height="400">
          <?php elseif ($isVideo) : ?>
            <video src="<?= e($fileUrl) ?>" preload="metadata" muted playsinline aria-label="Vista previa de video: <?= e($asset['title']) ?>"></video>
            <span class="branding-file-kind">Video</span>
          <?php else : ?>
            <div class="branding-file-placeholder" aria-label="Archivo <?= e(strtoupper(pathinfo((string) ($asset['original_name'] ?? ''), PATHINFO_EXTENSION))) ?>">
              <span aria-hidden="true"></span>
              <strong><?= e(strtoupper(pathinfo((string) ($asset['original_name'] ?? ''), PATHINFO_EXTENSION) ?: 'ARTE')) ?></strong>
            </div>
          <?php endif; ?>
          <span class="branding-status is-<?= e($statusClass((string) $asset['status'])) ?>"><?= e($statuses[$asset['status']] ?? $asset['status']) ?></span>
          <?php if (empty($asset['active'])) : ?><span class="branding-archived-label">Archivada</span><?php endif; ?>
        </div>
        <div class="branding-piece-body">
          <div class="branding-piece-heading">
            <div><span><?= e($categories[$asset['category']] ?? $asset['category']) ?></span><h3><?= e($asset['title']) ?></h3></div>
            <span class="branding-compliance" title="Cumplimiento del manual de marca"><?= e((int) $asset['brand_compliance']) ?>%</span>
          </div>
          <?php if (!empty($asset['description'])) : ?><p class="branding-piece-description"><?= e($asset['description']) ?></p><?php endif; ?>
          <div class="branding-channel-list" aria-label="Canales de uso">
            <?php foreach ((array) $asset['channels'] as $channel) : ?><span><?= e($channels[$channel] ?? $channel) ?></span><?php endforeach; ?>
          </div>
          <dl class="branding-piece-meta">
            <div><dt>Creación</dt><dd><?= e($formatDate($asset['creation_date'])) ?></dd></div>
            <div><dt>Responsable</dt><dd><?= e($asset['designer_name'] ?: 'Sin asignar') ?></dd></div>
            <div><dt>Archivo</dt><dd><?= e($fileSize($asset['file_size'] ?? 0)) ?></dd></div>
          </dl>
          <?php if ($linkedPosts !== []) : ?>
            <div class="branding-kpi-context"><strong>KPIs de publicaciones</strong><span><?= e(count($linkedPosts)) ?> vinculada<?= count($linkedPosts) === 1 ? '' : 's' ?></span></div>
            <div class="branding-piece-kpis is-social">
              <span><small>Alcance</small><strong><?= e($formatNumber($socialKpis['reach'] ?? 0)) ?></strong></span>
              <span><small>Vistas</small><strong><?= e($formatNumber($socialKpis['views'] ?? 0)) ?></strong></span>
              <span><small>Interacciones</small><strong><?= e($formatNumber($socialKpis['interactions'] ?? 0)) ?></strong></span>
              <span><small>Clics</small><strong><?= e($formatNumber($socialKpis['clicks'] ?? 0)) ?></strong></span>
            </div>
            <div class="branding-linked-posts">
              <?php foreach (array_slice($linkedPosts, 0, 2) as $post) :
                $postUrl = $safeExternalUrl($post['permalink'] ?? '');
              ?>
                <div>
                  <span class="branding-platform-mark is-<?= e($post['platform'] ?? '') ?>" aria-hidden="true"><?= ($post['platform'] ?? '') === 'instagram' ? 'IG' : 'FB' ?></span>
                  <span><strong><?= e($platformLabels[$post['platform'] ?? ''] ?? 'Meta') ?> · <?= e($formatDate($post['post_date'] ?? '')) ?></strong><small><?= e($post['title'] ?: 'Publicación sin texto') ?></small></span>
                  <?php if ($postUrl !== '') : ?><a href="<?= e($postUrl) ?>" target="_blank" rel="noopener noreferrer" aria-label="Ver publicación en <?= e($platformLabels[$post['platform'] ?? ''] ?? 'Meta') ?>">Ver</a><?php endif; ?>
                </div>
              <?php endforeach; ?>
              <?php if (count($linkedPosts) > 2) : ?><small class="branding-linked-more">Y <?= e(count($linkedPosts) - 2) ?> publicación<?= count($linkedPosts) - 2 === 1 ? '' : 'es' ?> más</small><?php endif; ?>
            </div>
          <?php else : ?>
            <div class="branding-kpi-context"><strong>KPIs registrados</strong><span>Sin publicaciones vinculadas</span></div>
            <div class="branding-piece-kpis">
              <span><small>Alcance</small><strong><?= e($formatNumber($asset['reach_count'] ?? 0)) ?></strong></span>
              <span><small>Clics</small><strong><?= e($formatNumber($asset['clicks_count'] ?? 0)) ?></strong></span>
              <span><small>Leads</small><strong><?= e($formatNumber($asset['leads_count'] ?? 0)) ?></strong></span>
            </div>
          <?php endif; ?>
          <div class="branding-piece-actions">
            <?php if ($canManage) : ?>
              <button
                class="button branding-link-posts-button"
                type="button"
                data-open-post-link
                data-asset-id="<?= e((int) $asset['id']) ?>"
                data-asset-title="<?= e($asset['title']) ?>"
                data-linked-post-ids="<?= e(json_encode($linkedPostIds, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"
              ><?= $linkedPosts === [] ? 'Vincular publicaciones' : 'Administrar vínculos' ?></button>
            <?php endif; ?>
            <button
              class="button ghost branding-view-details-button"
              type="button"
              data-open-branding-detail
              data-detail-template="branding-detail-<?= e((int) $asset['id']) ?>"
              data-detail-title="<?= e($asset['title']) ?>"
              data-detail-file="<?= e($asset['original_name'] ?: 'Arte final') ?>"
            >Ver detalles</button>
            <a class="button ghost" href="<?= e($downloadUrl) ?>">Descargar</a>
            <?php if ($canManage) : ?>
              <a class="button ghost" href="<?= e(url_page('branding', ['edit' => (int) $asset['id']])) ?>">Editar</a>
              <form method="post" data-confirm-branding="<?= empty($asset['active']) ? '¿Restaurar esta pieza al banco activo?' : '¿Archivar esta pieza? Podrás restaurarla después.' ?>">
                <input type="hidden" name="action" value="<?= empty($asset['active']) ? 'branding_restore_asset' : 'branding_archive_asset' ?>">
                <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= e((int) $asset['id']) ?>">
                <button type="submit" class="button ghost <?= empty($asset['active']) ? '' : 'is-danger' ?>"><?= empty($asset['active']) ? 'Restaurar' : 'Archivar' ?></button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      </article>

      <template id="branding-detail-<?= e((int) $asset['id']) ?>">
        <div class="branding-detail-layout">
          <section class="branding-detail-media-column" aria-label="Vista del arte final">
            <div class="branding-detail-viewer <?= $isPdf ? 'is-pdf' : ($isVideo ? 'is-video' : ($isImage ? 'is-image' : 'is-file')) ?>">
              <?php if ($isImage) : ?>
                <img data-branding-detail-src="<?= e($fileUrl) ?>" alt="Arte final de <?= e($asset['title']) ?>" width="1200" height="800">
              <?php elseif ($isPdf) : ?>
                <iframe data-branding-detail-src="<?= e($fileUrl) ?>" title="PDF de <?= e($asset['title']) ?>"></iframe>
              <?php elseif ($isVideo) : ?>
                <video data-branding-detail-src="<?= e($fileUrl) ?>" controls preload="metadata" playsinline aria-label="Video de <?= e($asset['title']) ?>"></video>
              <?php else : ?>
                <div class="branding-detail-file-placeholder">
                  <span aria-hidden="true"></span>
                  <strong><?= e(strtoupper($extension ?: 'ARTE')) ?></strong>
                  <p>Este formato no tiene vista previa en el navegador.</p>
                </div>
              <?php endif; ?>
            </div>
            <div class="branding-detail-filebar">
              <div>
                <strong><?= e($asset['original_name'] ?: 'Arte final') ?></strong>
                <span><?= e($mime ?: 'Archivo') ?> · <?= e($fileSize($asset['file_size'] ?? 0)) ?></span>
              </div>
              <div>
                <?php if ($isPdf || $isImage || $isVideo) : ?><a class="button ghost" href="<?= e($fileUrl) ?>" target="_blank" rel="noopener">Abrir aparte</a><?php endif; ?>
                <a class="button" href="<?= e($downloadUrl) ?>">Descargar archivo</a>
              </div>
            </div>
          </section>

          <div class="branding-detail-information">
            <section class="branding-detail-summary">
              <div class="branding-detail-badges">
                <span><?= e($categories[$asset['category']] ?? $asset['category']) ?></span>
                <span class="is-status is-<?= e($statusClass((string) $asset['status'])) ?>"><?= e($statuses[$asset['status']] ?? $asset['status']) ?></span>
                <?php if (empty($asset['active'])) : ?><span class="is-archived">Archivada</span><?php endif; ?>
              </div>
              <?php if (!empty($asset['description'])) : ?><p><?= e($asset['description']) ?></p><?php else : ?><p class="branding-detail-muted">Esta pieza no tiene una descripción registrada.</p><?php endif; ?>
              <div class="branding-detail-compliance">
                <div><span>Coherencia con el manual de marca</span><strong><?= e((int) $asset['brand_compliance']) ?>%</strong></div>
                <span aria-hidden="true"><i style="--compliance: <?= e((int) $asset['brand_compliance']) ?>%"></i></span>
              </div>
            </section>

            <section class="branding-detail-section">
              <header><span>Información</span><h3>Datos de la pieza</h3></header>
              <dl class="branding-detail-data-grid">
                <div><dt>Código interno</dt><dd><?= e($asset['asset_code'] ?: 'Sin código') ?></dd></div>
                <div><dt>Campaña</dt><dd><?= e($asset['campaign_name'] ?: 'Sin campaña') ?></dd></div>
                <div><dt>Fecha de creación</dt><dd><?= e($formatDate($asset['creation_date'])) ?></dd></div>
                <div><dt>Fecha de aprobación</dt><dd><?= e($formatDate($asset['approval_date'] ?? '')) ?></dd></div>
                <div><dt>Fecha de publicación</dt><dd><?= e($formatDate($asset['publication_date'] ?? '')) ?></dd></div>
                <div><dt>Fecha de implementación</dt><dd><?= e($formatDate($asset['implementation_date'] ?? '')) ?></dd></div>
              </dl>
              <div class="branding-detail-channels">
                <strong>Canales de uso</strong>
                <div><?php foreach ((array) $asset['channels'] as $channel) : ?><span><?= e($channels[$channel] ?? $channel) ?></span><?php endforeach; ?></div>
              </div>
            </section>

            <section class="branding-detail-section">
              <header><span>Equipo</span><h3>Responsables</h3></header>
              <dl class="branding-detail-data-grid is-responsibles">
                <div><dt>Diseñó</dt><dd><?= e($asset['designer_name'] ?: 'Sin asignar') ?></dd></div>
                <div><dt>Aprobó</dt><dd><?= e($asset['approver_name'] ?: 'Sin asignar') ?></dd></div>
                <div><dt>Implementó</dt><dd><?= e($asset['implementer_name'] ?: 'Sin asignar') ?></dd></div>
              </dl>
            </section>

            <section class="branding-detail-section">
              <header><span>Resultados</span><h3>Indicadores registrados</h3></header>
              <div class="branding-detail-kpis">
                <span><small>Visualizaciones</small><strong><?= e($formatNumber($asset['views_count'] ?? 0)) ?></strong></span>
                <span><small>Alcance</small><strong><?= e($formatNumber($asset['reach_count'] ?? 0)) ?></strong></span>
                <span><small>Clics</small><strong><?= e($formatNumber($asset['clicks_count'] ?? 0)) ?></strong></span>
                <span><small>Leads</small><strong><?= e($formatNumber($asset['leads_count'] ?? 0)) ?></strong></span>
                <span><small>Referidos</small><strong><?= e($formatNumber($asset['referrals_count'] ?? 0)) ?></strong></span>
                <span><small>Captaciones</small><strong><?= e($formatNumber($asset['acquisitions_count'] ?? 0)) ?></strong></span>
              </div>
              <?php if ($linkedPosts !== []) : ?>
                <div class="branding-detail-social-kpis">
                  <strong>KPIs consolidados desde Meta</strong>
                  <div>
                    <span><small>Alcance</small><b><?= e($formatNumber($socialKpis['reach'] ?? 0)) ?></b></span>
                    <span><small>Vistas</small><b><?= e($formatNumber($socialKpis['views'] ?? 0)) ?></b></span>
                    <span><small>Interacciones</small><b><?= e($formatNumber($socialKpis['interactions'] ?? 0)) ?></b></span>
                    <span><small>Clics</small><b><?= e($formatNumber($socialKpis['clicks'] ?? 0)) ?></b></span>
                  </div>
                </div>
              <?php endif; ?>
            </section>

            <?php if ($linkedPosts !== []) : ?>
              <section class="branding-detail-section">
                <header><span>Contenido relacionado</span><h3>Publicaciones vinculadas</h3></header>
                <div class="branding-detail-posts">
                  <?php foreach ($linkedPosts as $post) : $postUrl = $safeExternalUrl($post['permalink'] ?? ''); ?>
                    <article>
                      <span class="branding-platform-mark is-<?= e($post['platform'] ?? '') ?>" aria-hidden="true"><?= ($post['platform'] ?? '') === 'instagram' ? 'IG' : 'FB' ?></span>
                      <div><strong><?= e($post['title'] ?: 'Publicación sin texto') ?></strong><small><?= e($platformLabels[$post['platform'] ?? ''] ?? 'Meta') ?> · <?= e($formatDate($post['post_date'] ?? '')) ?></small></div>
                      <?php if ($postUrl !== '') : ?><a href="<?= e($postUrl) ?>" target="_blank" rel="noopener noreferrer">Ver publicación</a><?php endif; ?>
                    </article>
                  <?php endforeach; ?>
                </div>
              </section>
            <?php endif; ?>

            <section class="branding-detail-section">
              <header><span>Seguimiento</span><h3>Observaciones</h3></header>
              <p class="branding-detail-observations"><?= e($asset['observations'] ?: 'No hay observaciones registradas para esta pieza.') ?></p>
            </section>
          </div>
        </div>
      </template>
    <?php endforeach; ?>
    <?php if ($assets === []) : ?>
      <div class="branding-empty-state">
        <span class="branding-empty-icon" aria-hidden="true"></span>
        <h3>No encontramos piezas</h3>
        <p><?= array_filter($filters, static fn ($value, $key) => $value !== '' && !($key === 'active' && $value === '1'), ARRAY_FILTER_USE_BOTH) ? 'Prueba con otros filtros o limpia la búsqueda.' : 'Sube el primer arte final para comenzar a medir el avance del cambio de imagen.' ?></p>
        <?php if ($canManage) : ?><button type="button" data-open-branding-modal>Subir primera pieza</button><?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<aside class="branding-strategy">
  <span aria-hidden="true"></span>
  <p>El Banco de Piezas de Branding convierte el cambio de imagen en una estrategia organizada, visible y medible, asegurando que cada diseño contribuya al posicionamiento, reconocimiento y crecimiento comercial de SuCasa Inmobiliaria.</p>
</aside>

<div class="branding-modal branding-detail-modal" data-branding-detail-modal hidden>
  <button class="branding-modal-backdrop" type="button" data-close-branding-detail tabindex="-1" aria-label="Cerrar detalle de la pieza"></button>
  <section class="branding-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="branding-detail-title" aria-describedby="branding-detail-description">
    <header class="branding-modal-head">
      <div>
        <span>Detalle de la pieza</span>
        <h2 id="branding-detail-title" data-branding-detail-title>Pieza de branding</h2>
        <p id="branding-detail-description" data-branding-detail-file>Arte final, información, responsables e indicadores.</p>
      </div>
      <button class="branding-modal-close" type="button" data-close-branding-detail aria-label="Cerrar detalle de la pieza"></button>
    </header>
    <div class="branding-detail-content" data-branding-detail-content></div>
  </section>
</div>

<?php if ($canManage) : ?>
<div class="branding-modal" data-branding-modal <?= $formOpen ? '' : 'hidden' ?>>
  <button class="branding-modal-backdrop" type="button" data-close-branding-modal tabindex="-1" aria-label="Cerrar formulario"></button>
  <section class="branding-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="branding-modal-title">
    <header class="branding-modal-head">
      <div>
        <span><?= $isEditing ? 'Actualizar registro' : 'Nueva pieza' ?></span>
        <h2 id="branding-modal-title"><?= $isEditing ? 'Editar pieza de branding' : 'Subir pieza al banco' ?></h2>
        <p>Registra el arte, su flujo de aprobación y los indicadores que permitan medir su aporte.</p>
      </div>
      <button class="branding-modal-close" type="button" data-close-branding-modal aria-label="Cerrar formulario"></button>
    </header>

    <form method="post" enctype="multipart/form-data" class="branding-form" data-branding-form>
      <input type="hidden" name="action" value="branding_save_asset">
      <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="id" value="<?= e((int) ($editing['id'] ?? 0)) ?>">

      <aside class="branding-link-guide" role="note" aria-labelledby="branding-link-guide-title">
        <span class="branding-link-guide-icon" aria-hidden="true"></span>
        <div>
          <strong id="branding-link-guide-title">¿Dónde vinculo las publicaciones anteriores?</strong>
          <p>Primero guarda esta pieza. Después, en su tarjeta del Banco, aparecerá el botón <b>Vincular publicaciones</b>.</p>
          <ol>
            <li><span>1</span>Completa y guarda la pieza.</li>
            <li><span>2</span>Busca su tarjeta en el Banco.</li>
            <li><span>3</span>Selecciona <b>Vincular publicaciones</b> y elige los contenidos de Meta.</li>
          </ol>
          <small>El alcance, las vistas, las interacciones y los clics sociales se calcularán automáticamente desde las publicaciones vinculadas.</small>
        </div>
      </aside>

      <fieldset>
        <legend><span>01</span> Identificación de la pieza</legend>
        <div class="branding-form-grid">
          <label><span>Nombre de la pieza <b aria-hidden="true">*</b></span><input name="title" required maxlength="255" value="<?= e($editing['title'] ?? '') ?>" data-branding-initial-focus></label>
          <label><span>Código interno</span><input name="asset_code" maxlength="80" value="<?= e($editing['asset_code'] ?? '') ?>" placeholder="BR-2026-001"></label>
          <label><span>Categoría <b aria-hidden="true">*</b></span><select name="category" required><option value="">Selecciona una categoría</option><?php foreach ($categories as $key => $label) : ?><option value="<?= e($key) ?>" <?= ($editing['category'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
          <label><span>Campaña o iniciativa</span><input name="campaign_name" maxlength="191" value="<?= e($editing['campaign_name'] ?? '') ?>" placeholder="Cambio de imagen 2026"></label>
          <label class="branding-field-wide"><span>Descripción</span><textarea name="description" rows="3" maxlength="3000" placeholder="Uso previsto, objetivo visual o contexto de la pieza"><?= e($editing['description'] ?? '') ?></textarea></label>
          <label class="branding-upload branding-field-wide">
            <input type="file" name="asset_file" <?= $isEditing ? '' : 'required' ?> accept=".jpg,.jpeg,.png,.webp,.gif,.svg,.pdf,.mp4,.webm,.mov,.ppt,.pptx,.psd,.ai,.eps" data-branding-file>
            <span class="branding-upload-icon" aria-hidden="true"></span>
            <span><strong><?= $isEditing ? 'Reemplazar arte final' : 'Adjuntar arte final' ?></strong><small>Imagen, PDF, video o archivo de diseño · máximo 50 MB<?= $isEditing ? '. Déjalo vacío para conservar el actual.' : '' ?></small><em data-branding-file-name><?= e($editing['original_name'] ?? 'Selecciona o arrastra un archivo') ?></em></span>
          </label>
        </div>
      </fieldset>

      <fieldset>
        <legend><span>02</span> Flujo y responsables</legend>
        <div class="branding-form-grid">
          <label><span>Estado <b aria-hidden="true">*</b></span><select name="status" required><?php foreach ($statuses as $key => $label) : ?><option value="<?= e($key) ?>" <?= ($editing['status'] ?? 'designed') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
          <label><span>Fecha de creación <b aria-hidden="true">*</b></span><input type="date" name="creation_date" required value="<?= e($editing['creation_date'] ?? date('Y-m-d')) ?>"></label>
          <label><span>Diseñó</span><input name="designer_name" maxlength="191" value="<?= e($editing['designer_name'] ?? '') ?>" autocomplete="name"></label>
          <label><span>Aprobó</span><input name="approver_name" maxlength="191" value="<?= e($editing['approver_name'] ?? '') ?>" autocomplete="name"></label>
          <label><span>Implementó</span><input name="implementer_name" maxlength="191" value="<?= e($editing['implementer_name'] ?? '') ?>" autocomplete="name"></label>
          <label><span>Fecha de aprobación</span><input type="date" name="approval_date" value="<?= e($editing['approval_date'] ?? '') ?>"></label>
          <label><span>Fecha de publicación</span><input type="date" name="publication_date" value="<?= e($editing['publication_date'] ?? '') ?>"></label>
          <label><span>Fecha de implementación</span><input type="date" name="implementation_date" value="<?= e($editing['implementation_date'] ?? '') ?>"></label>
          <fieldset class="branding-channel-field branding-field-wide">
            <legend>Canales de uso <b aria-hidden="true">*</b></legend>
            <div><?php foreach ($channels as $key => $label) : ?><label><input type="checkbox" name="channels[]" value="<?= e($key) ?>" <?= in_array($key, $selectedChannels, true) ? 'checked' : '' ?>><span><?= e($label) ?></span></label><?php endforeach; ?></div>
            <small>Selecciona al menos un canal.</small>
          </fieldset>
        </div>
      </fieldset>

      <fieldset>
        <legend><span>03</span> Impacto y coherencia</legend>
        <div class="branding-form-grid is-kpi-grid">
          <label><span>Visualizaciones</span><input type="number" name="views_count" min="0" value="<?= e((int) ($editing['views_count'] ?? 0)) ?>" inputmode="numeric"></label>
          <label><span>Alcance</span><input type="number" name="reach_count" min="0" value="<?= e((int) ($editing['reach_count'] ?? 0)) ?>" inputmode="numeric"></label>
          <label><span>Clics</span><input type="number" name="clicks_count" min="0" value="<?= e((int) ($editing['clicks_count'] ?? 0)) ?>" inputmode="numeric"></label>
          <label><span>Leads o contactos</span><input type="number" name="leads_count" min="0" value="<?= e((int) ($editing['leads_count'] ?? 0)) ?>" inputmode="numeric"></label>
          <label><span>Referidos</span><input type="number" name="referrals_count" min="0" value="<?= e((int) ($editing['referrals_count'] ?? 0)) ?>" inputmode="numeric"></label>
          <label><span>Captaciones</span><input type="number" name="acquisitions_count" min="0" value="<?= e((int) ($editing['acquisitions_count'] ?? 0)) ?>" inputmode="numeric"></label>
          <label class="branding-range-field branding-field-wide">
            <span>Cumplimiento del manual de marca</span>
            <div><input type="range" name="brand_compliance" min="0" max="100" step="5" value="<?= e((int) ($editing['brand_compliance'] ?? 0)) ?>" data-branding-compliance><output data-branding-compliance-output><?= e((int) ($editing['brand_compliance'] ?? 0)) ?>%</output></div>
            <small>Evalúa tipografía, paleta, logo, composición y tono visual.</small>
          </label>
          <label class="branding-field-wide"><span>Observaciones</span><textarea name="observations" rows="4" maxlength="5000" placeholder="Ajustes pendientes, aprobación, aprendizajes o desempeño de la pieza"><?= e($editing['observations'] ?? '') ?></textarea></label>
        </div>
      </fieldset>

      <footer class="branding-form-actions">
        <button class="button ghost" type="button" data-close-branding-modal>Cancelar</button>
        <button type="submit" data-branding-submit><?= $isEditing ? 'Guardar cambios' : 'Guardar pieza' ?></button>
      </footer>
    </form>
  </section>
</div>

<div class="branding-modal branding-post-link-modal" data-post-link-modal data-auto-open-asset="<?= e($linkAssetId) ?>" hidden>
  <button class="branding-modal-backdrop" type="button" data-close-post-link tabindex="-1" aria-label="Cerrar selector de publicaciones"></button>
  <section class="branding-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="branding-post-link-title" aria-describedby="branding-post-link-description">
    <header class="branding-modal-head">
      <div>
        <span>Publicaciones anteriores</span>
        <h2 id="branding-post-link-title">Vincular publicaciones</h2>
        <p id="branding-post-link-description">Selecciona las publicaciones que utilizaron <strong data-post-link-asset-name>esta pieza</strong>. Sus KPIs se actualizarán desde Meta.</p>
      </div>
      <button class="branding-modal-close" type="button" data-close-post-link aria-label="Cerrar selector de publicaciones"></button>
    </header>

    <form method="post" class="branding-post-link-form" data-post-link-form>
      <input type="hidden" name="action" value="branding_replace_post_links">
      <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="id" value="0" data-post-link-asset-id>
      <?php if ($error && $linkAssetId > 0) : ?><div class="branding-alert is-error branding-post-link-alert" role="alert"><?= e($error) ?></div><?php endif; ?>

      <div class="branding-post-link-tools">
        <label>
          <span>Buscar publicación</span>
          <input type="search" placeholder="Texto, fecha o tipo de contenido" data-post-link-search autocomplete="off">
        </label>
        <label>
          <span>Red social</span>
          <select data-post-link-platform>
            <option value="">Facebook e Instagram</option>
            <option value="instagram">Instagram</option>
            <option value="facebook">Facebook</option>
          </select>
        </label>
      </div>

      <div class="branding-post-link-results-head">
        <strong><?= e(count($socialPosts)) ?> publicaciones disponibles</strong>
        <span data-post-link-visible-count><?= e(count($socialPosts)) ?> mostradas</span>
      </div>
      <div class="branding-post-link-list" data-post-link-list>
        <?php foreach ($socialPosts as $post) :
          $postId = (int) ($post['_ID'] ?? 0);
          $postPlatform = (string) ($post['platform'] ?? '');
          $postUrl = $safeExternalUrl($post['permalink'] ?? '');
          $thumbnailUrl = $safeExternalUrl($post['thumbnail_url'] ?? '');
          $linkedAssetId = (int) ($post['linked_asset_id'] ?? 0);
        ?>
          <article class="branding-post-option" data-post-option data-platform="<?= e($postPlatform) ?>">
            <input
              id="branding-post-<?= e($postId) ?>"
              type="checkbox"
              name="post_ids[]"
              value="<?= e($postId) ?>"
              data-post-checkbox
              data-linked-asset-id="<?= e($linkedAssetId) ?>"
              data-linked-asset-title="<?= e($post['linked_asset_title'] ?? '') ?>"
            >
            <label for="branding-post-<?= e($postId) ?>">
              <span class="branding-post-option-check" aria-hidden="true"></span>
              <span class="branding-post-option-preview">
                <?php if ($thumbnailUrl !== '') : ?><img src="<?= e($thumbnailUrl) ?>" alt="" loading="lazy" width="76" height="76"><?php else : ?><span class="branding-platform-mark is-<?= e($postPlatform) ?>" aria-hidden="true"><?= $postPlatform === 'instagram' ? 'IG' : 'FB' ?></span><?php endif; ?>
              </span>
              <span class="branding-post-option-copy">
                <span><b><?= e($platformLabels[$postPlatform] ?? 'Meta') ?></b><time datetime="<?= e($post['post_date'] ?? '') ?>"><?= e($formatDate($post['post_date'] ?? '')) ?></time><em><?= e($post['content_type'] ?? 'post') ?></em></span>
                <strong><?= e($post['title'] ?: 'Publicación sin texto') ?></strong>
                <small data-post-owner></small>
              </span>
              <span class="branding-post-option-kpis">
                <span><small>Alcance</small><strong><?= e($formatNumber($post['reach'] ?? 0)) ?></strong></span>
                <span><small>Vistas</small><strong><?= e($formatNumber($post['views'] ?? 0)) ?></strong></span>
                <span><small>Interacciones</small><strong><?= e($formatNumber($post['interactions'] ?? 0)) ?></strong></span>
              </span>
            </label>
            <?php if ($postUrl !== '') : ?><a class="branding-post-external-link" href="<?= e($postUrl) ?>" target="_blank" rel="noopener noreferrer">Ver publicación</a><?php endif; ?>
          </article>
        <?php endforeach; ?>
        <div class="branding-post-link-empty" data-post-link-empty <?= $socialPosts === [] ? '' : 'hidden' ?>>
          <strong>No hay publicaciones para mostrar</strong>
          <span><?= $socialPosts === [] ? 'Sincroniza Facebook o Instagram para cargar publicaciones anteriores.' : 'Prueba con otro texto o cambia la red social.' ?></span>
        </div>
      </div>

      <footer class="branding-form-actions branding-post-link-actions">
        <span><strong data-post-link-selected-count>0</strong> seleccionadas</span>
        <button class="button ghost" type="button" data-close-post-link>Cancelar</button>
        <button type="submit" data-post-link-submit>Guardar vínculos</button>
      </footer>
    </form>
  </section>
</div>
<?php endif; ?>
