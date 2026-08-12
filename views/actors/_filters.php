<form class="filter-panel compact-filters" method="get" data-autosubmit>
  <input type="hidden" name="page" value="actores">
  <input type="hidden" name="type" value="<?= e($type) ?>">
  <label>Buscar
    <input name="q" value="<?= e($search) ?>" placeholder="Nombre, correo, celular…">
  </label>
  <?php
  $filterColumns = array_values(array_filter($vista, static fn ($column) => !str_starts_with((string) $column, 'v_') && !in_array($column, ['author_name', 'total_familia'], true)));
  $filterLimit = $type === 'club_pph' ? 7 : 6;
  foreach (array_slice($filterColumns, 0, $filterLimit) as $column) :
    $options = $config['opciones_filtros'][$column] ?? [];
  ?>
    <label><?= e($labels[$column] ?? ucfirst($column)) ?>
      <?php if ($options) : ?>
        <select name="f[<?= e($column) ?>]"><option value="">Todos</option><?php foreach ($options as $option) : ?><option value="<?= e($option) ?>" <?= ($filters[$column] ?? '') === (string) $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select>
      <?php else : ?>
        <input name="f[<?= e($column) ?>]" value="<?= e($filters[$column] ?? '') ?>" placeholder="Filtrar">
      <?php endif; ?>
    </label>
  <?php endforeach; ?>
  <?php if (in_array('v_estado', $vista, true)) : ?>
    <label>Estado
      <select name="f[v_estado]">
        <option value="">Todos</option>
        <?php foreach (($config['opciones_filtros']['v_estado'] ?? ['Activo', 'Pendiente', 'Inactivo']) as $estado) : ?>
          <option value="<?= e($estado) ?>" <?= ($filters['v_estado'] ?? '') === (string) $estado ? 'selected' : '' ?>><?= e($estado) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  <?php endif; ?>
  <?php if (in_array($type, ['suscriptores', 'club_pph'], true)) : ?>
    <label>Fecha de registro
      <input name="f[fecha]" value="<?= e($filters['fecha'] ?? '') ?>" placeholder="AAAA-MM-DD - AAAA-MM-DD" inputmode="numeric" autocomplete="off" data-date-range-filter>
    </label>
  <?php endif; ?>
  <?php if ($showBulkToolbar && $campaigns !== []) : ?>
    <label>Campaña
      <select name="f[campaign_tag]"><option value="">No excluir campaña</option><?php foreach ($campaigns as $campaign) : $tag = (string) ($campaign['tag'] ?? ''); ?><option value="<?= e($tag) ?>" <?= ($filters['campaign_tag'] ?? '') === $tag ? 'selected' : '' ?>>Ocultar ya agregados a: <?= e($tag) ?></option><?php endforeach; ?></select>
    </label>
    <label>Canal campaña
      <select name="f[campaign_channel]">
        <option value="">Todos los canales</option>
        <?php foreach (['email' => 'Email', 'sms' => 'SMS', 'whatsapp' => 'WhatsApp'] as $channelKey => $channelLabel) : ?>
          <option value="<?= e($channelKey) ?>" <?= ($filters['campaign_channel'] ?? '') === $channelKey ? 'selected' : '' ?>>Solo <?= e($channelLabel) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <input type="hidden" name="f[campaign_delivery]" value="exclude_any">
  <?php endif; ?>
  <div class="filter-actions"><button class="primary">Aplicar</button><a class="button ghost" href="<?= e(url(['page' => 'actores', 'type' => $type])) ?>">Limpiar</a></div>
</form>
