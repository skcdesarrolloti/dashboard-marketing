<?php
$rows = $data['config_rows'] ?? [];
$promotion = $data['promotion'] ?? [];
$canEditPromotionConfig = (bool) ($canEditPromotionConfig ?? false);
$canManageSystemConfig = (bool) ($canManageSystemConfig ?? false);
$defaultRevistaLink = 'https://sucasainmobiliaria.com.co/esencia-inmobiliaria-edicion-enero-2026?utm_source=Notificaciones%20pagina%20web&utm_medium=Email';
$revistaLink = trim((string) ($promotion['link_revista'] ?? '')) ?: $defaultRevistaLink;
$filterFuncion = trim((string) ($_GET['funcion'] ?? ''));
$filterValor = trim((string) ($_GET['valor'] ?? ''));
$rows = array_values(array_filter($rows, static fn(array $row): bool => !in_array((string) ($row['funcion'] ?? ''), ['banner', 'link_revista'], true)));
if ($filterFuncion !== '' || $filterValor !== '') {
    $rows = array_values(array_filter($rows, static function (array $row) use ($filterFuncion, $filterValor): bool {
        $funcion = strtolower((string) ($row['funcion'] ?? ''));
        $valor = strtolower((string) ($row['valor'] ?? ''));
        return ($filterFuncion === '' || str_contains($funcion, strtolower($filterFuncion)))
            && ($filterValor === '' || str_contains($valor, strtolower($filterValor)));
    }));
}
?>
<header class="page-head hero-head">
  <div>
    <span class="eyebrow">Administración</span>
    <h1>Configuración</h1>
    <p>Llaves del sistema y configuración de promoción.</p>
  </div>
</header>

<?php if(isset($_GET['saved'])):?><div class="notice" role="status">Configuración actualizada.</div><?php endif;?>

<?php if ($canEditPromotionConfig): ?>
<section class="panel config-promotion-panel">
  <div class="panel-head"><h2>Promoción</h2><span>Banner y revista</span></div>
  <form class="config-promotion-form" method="post" enctype="multipart/form-data" data-compress-banner-form>
    <input type="hidden" name="_token" value="<?=e(csrf_token())?>">
    <input type="hidden" name="action" value="admin_save_promotion">
    <div class="config-promo-card">
      <div class="panel-head"><h2>Imagen banner</h2></div>
      <div class="config-banner-preview">
        <?php if (!empty($promotion['banner'])): ?>
          <img src="<?= e($promotion['banner']) ?>" alt="Banner actual">
        <?php else: ?>
          <span>Sin banner configurado</span>
        <?php endif; ?>
      </div>
      <div class="config-banner-actions">
        <?php if (!empty($promotion['banner'])): ?>
          <a class="button ghost" href="<?= e($promotion['banner']) ?>" target="_blank" rel="noopener">Ver completo</a>
        <?php endif; ?>
      </div>
      <label class="config-upload-field">Subir banner
        <span class="config-upload-box">
          <strong>Seleccionar imagen</strong>
          <small>JPG, PNG o WEBP. Se comprime antes de guardar.</small>
          <input type="file" name="banner_image" accept="image/jpeg,image/png,image/webp" data-compress-banner-input>
        </span>
      </label>
      <label>URL banner
        <input name="banner_url" value="<?= e($promotion['banner'] ?? '') ?>" placeholder="https://...">
      </label>
    </div>
    <div class="config-promo-card">
      <div class="panel-head"><h2>Link revista</h2></div>
      <label>URL revista
        <input name="link_revista" value="<?= e($revistaLink) ?>" placeholder="<?= e($defaultRevistaLink) ?>">
      </label>
    </div>
    <div class="config-promotion-submit">
      <button class="primary">Guardar banner y link</button>
    </div>
  </form>
</section>
<?php endif; ?>

<?php if ($canManageSystemConfig): ?>
<section class="panel config-table-panel">
  <div class="panel-head"><h2>Configuración general</h2><span><?= e(count($rows)) ?> registros</span></div>

  <form class="filter-panel compact-filters" method="get" data-autosubmit>
    <input type="hidden" name="page" value="configuracion">
    <div><label>Función</label><input name="funcion" value="<?= e($filterFuncion) ?>" placeholder="Filtrar función"></div>
    <div><label>Valor</label><input name="valor" value="<?= e($filterValor) ?>" placeholder="Filtrar valor"></div>
    <div class="filter-actions"><button>Filtrar</button><a class="button ghost" href="<?= e(url(['page'=>'configuracion'])) ?>">Limpiar</a></div>
  </form>

  <form class="filter-panel compact-filters config-create-form" method="post">
    <input type="hidden" name="_token" value="<?=e(csrf_token())?>">
    <input type="hidden" name="action" value="admin_save_config_row">
    <div><label>Llave / Función</label><input name="funcion" required></div>
    <div><label>Valor / URL</label><input name="valor"></div>
    <div class="filter-actions"><button class="primary">Agregar</button></div>
  </form>

  <div class="table-wrap config-table-wrap">
    <table>
      <thead><tr><th>Función</th><th>Valor</th><th>Acciones</th></tr></thead>
      <tbody>
      <?php foreach($rows as $row): ?>
        <?php $formId = 'config-row-' . (int) $row['_ID']; ?>
        <tr>
          <td>
            <input form="<?= e($formId) ?>" name="funcion" value="<?=e($row['funcion'] ?? '')?>" aria-label="Función" required>
          </td>
          <td>
            <input form="<?= e($formId) ?>" name="valor" value="<?=e($row['valor'] ?? '')?>" aria-label="Valor">
          </td>
          <td>
            <form id="<?= e($formId) ?>" method="post">
              <input type="hidden" name="_token" value="<?=e(csrf_token())?>">
              <input type="hidden" name="action" value="admin_save_config_row">
              <input type="hidden" name="id" value="<?=e($row['_ID'])?>">
            </form>
            <div class="campaign-actions">
              <button form="<?= e($formId) ?>">Guardar</button>
            <form method="post" onsubmit="return confirm('¿Eliminar esta configuración?')">
              <input type="hidden" name="_token" value="<?=e(csrf_token())?>">
              <input type="hidden" name="action" value="admin_delete_config_row">
              <input type="hidden" name="id" value="<?=e($row['_ID'])?>">
              <button class="button danger">Eliminar</button>
            </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="3">Sin registros para los filtros actuales.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php endif; ?>
