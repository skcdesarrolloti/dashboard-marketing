<div class="modal" id="actor-modal" data-actor-modal hidden><div class="modal-backdrop" data-modal-close></div><section class="modal-panel" role="dialog" aria-modal="true" aria-label="Editar actor"><button type="button" class="modal-close" data-modal-close aria-label="Cerrar">&times;</button><div class="modal-content" data-actor-modal-content><div class="modal-loading">Cargando…</div></div></section></div>

<?php
$templateChannel = static function (array $template): string {
  $channel = strtolower(trim((string) ($template['tipo'] ?? 'email')));
  return $channel === 'wsp' ? 'whatsapp' : $channel;
};
$templateOptionAttrs = static function (array $template) use ($templateChannel): string {
  $attrs = [
    'data-template-channel' => $templateChannel($template),
    'data-subject' => (string) ($template['asunto'] ?? ''),
    'data-message' => $templateChannel($template) === 'whatsapp'
      ? \App\TemplateRepository::whatsappBody($template)
      : (string) ($template['contenido'] ?? ''),
  ];
  if ($templateChannel($template) === 'whatsapp') {
    $config = \App\TemplateRepository::whatsappConfig($template);
    $attrs['data-whatsapp-header-type'] = (string) ($config['header_type'] ?? 'none');
    $attrs['data-whatsapp-header-url'] = (string) ($config['header_url'] ?? '');
    $attrs['data-whatsapp-button-type'] = (string) ($config['button_type'] ?? 'none');
    $attrs['data-whatsapp-button-param'] = (string) ($config['button_url_parameter'] ?? '');
  }

  $html = '';
  foreach ($attrs as $name => $value) {
    $html .= ' ' . $name . '="' . e($value) . '"';
  }
  return $html;
};
?>

<?php if ($showImport) : ?>
<div class="modal" id="import-modal" hidden><div class="modal-backdrop" data-modal-close></div><section class="modal-panel" role="dialog" aria-modal="true" aria-label="Importar actores"><button type="button" class="modal-close" data-modal-close aria-label="Cerrar">&times;</button><div class="modal-content"><h3 class="modal-title">Importar <?= e($config['title']) ?></h3><p class="modal-subtitle">Archivo CSV con encabezados: <?= e(implode(', ', array_keys($config['fields'] ?? []))) ?>.</p><form method="post" enctype="multipart/form-data"><input type="hidden" name="action" value="import_actors"><input type="hidden" name="_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="type" value="<?= e($type) ?>"><label>Archivo CSV<input type="file" name="file" accept=".csv" required></label><div class="campaign-actions"><button type="button" class="button ghost" data-modal-close>Cancelar</button><button class="primary">Importar</button></div></form></div></section></div>
<?php endif; ?>

<?php if ($showBulkToolbar) : ?>
<div class="modal" id="mass-send-modal" hidden><div class="modal-backdrop" data-modal-close></div><section class="modal-panel" role="dialog" aria-modal="true" aria-label="Crear campaña"><button type="button" class="modal-close" data-modal-close aria-label="Cerrar">&times;</button><div class="modal-content"><h3 class="modal-title">Crear campaña</h3><p class="modal-subtitle">Los destinatarios se validan contra preferencias, exclusiones y duplicados antes de entrar a la cola.</p><form method="post" enctype="multipart/form-data" id="mass-send-form"><input type="hidden" name="action" value="send_campaign"><input type="hidden" name="_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="type" value="<?= e($type) ?>"><input type="hidden" name="q" value="<?= e($search) ?>"><?php foreach ($filters as $column => $value) : ?><input type="hidden" name="f[<?= e($column) ?>]" value="<?= e($value) ?>"><?php endforeach; ?><div id="mass-send-selected-ids"></div><div class="modal-fields">
  <label>Alcance<select name="mass_scope"><option value="selected">Seleccionados</option><option value="filtered_50">Primeros 50 filtrados</option><option value="filtered_100">Primeros 100 filtrados</option><option value="filtered_200">Primeros 200 filtrados</option><option value="filtered_500">Primeros 500 filtrados</option><option value="filtered">Todos los filtrados (máx. 5000)</option></select></label>
  <label>Canal<select name="channel" data-channel-select><option value="email">Email</option><option value="sms">SMS</option><option value="whatsapp">WhatsApp</option></select></label>
  <label>Categoría<select name="categoria"><?php foreach ($categorias as $key => $label) : ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?></select></label>
  <?php if ($campaigns !== []) : ?><label>Campaña existente<select data-campaign-picker><option value="">Nueva campaña</option><?php foreach ($campaigns as $campaign) : ?><option value="<?= e($campaign['tag'] ?? '') ?>"><?= e($campaign['tag'] ?? '') ?></option><?php endforeach; ?></select></label><?php endif; ?>
  <label>Nombre de campaña<input name="campaign_tag" required></label>
  <label>Plantilla<select name="template_id" data-template-picker><option value="">Mensaje manual</option><?php foreach ($templates as $template) : ?><option value="<?= e((int) $template['_ID']) ?>"<?= $templateOptionAttrs($template) ?>><?= e(($template['nombre'] ?? 'Plantilla') . ' · ' . ($template['tipo'] ?? '')) ?></option><?php endforeach; ?></select></label>
  <label data-subject-wrap>Asunto<input name="subject"></label><label>Mensaje<span class="sms-message-box" data-sms-message-box><span class="sms-fixed-prefix" data-sms-prefix hidden>SKC SuCasa Inmobiliaria </span><textarea name="message" rows="6" required></textarea></span><span class="sms-counter" data-sms-counter hidden>0/160 caracteres</span></label><label data-attachment-wrap>Adjunto<input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png"></label><label data-whatsapp-media-wrap hidden>URL documento/video<input name="whatsapp_media_url" placeholder="https://..."></label><label data-whatsapp-button-wrap hidden>Parámetro botón URL<input name="whatsapp_button_url_parameter" placeholder="{{link}} o ruta"></label>
</div><div class="campaign-actions"><button type="button" class="button ghost" data-modal-close>Cancelar</button><button class="primary">Agregar a la cola</button></div></form></div></section></div>
<?php endif; ?>

<div class="modal" id="single-send-modal" hidden><div class="modal-backdrop" data-modal-close></div><section class="modal-panel" role="dialog" aria-modal="true" aria-label="Enviar comunicación"><button type="button" class="modal-close" data-modal-close aria-label="Cerrar">&times;</button><div class="modal-content"><h3 class="modal-title">Enviar comunicación</h3><p class="modal-subtitle" id="single-send-subtitle"></p><form method="post" enctype="multipart/form-data"><input type="hidden" name="action" value="send_campaign"><input type="hidden" name="_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="type" value="<?= e($type) ?>"><input type="hidden" name="ids[]" id="single-send-actor-id"><div class="modal-fields"><label>Canal<select name="channel" id="single-send-channel" data-channel-select><option value="email">Email</option><option value="sms">SMS</option><option value="whatsapp">WhatsApp</option></select></label><label>Categoría<select name="categoria"><?php foreach ($categorias as $key => $label) : ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?></select></label><?php if ($campaigns !== []) : ?><label>Campaña existente<select data-campaign-picker><option value="">Nueva campaña</option><?php foreach ($campaigns as $campaign) : ?><option value="<?= e($campaign['tag'] ?? '') ?>"><?= e($campaign['tag'] ?? '') ?></option><?php endforeach; ?></select></label><?php endif; ?><label>Nombre de campaña<input name="campaign_tag" required></label><label>Plantilla<select name="template_id" data-template-picker><option value="">Mensaje manual</option><?php foreach ($templates as $template) : ?><option value="<?= e((int) $template['_ID']) ?>"<?= $templateOptionAttrs($template) ?>><?= e(($template['nombre'] ?? 'Plantilla') . ' · ' . ($template['tipo'] ?? '')) ?></option><?php endforeach; ?></select></label><label data-subject-wrap>Asunto<input name="subject"></label><label>Mensaje<span class="sms-message-box" data-sms-message-box><span class="sms-fixed-prefix" data-sms-prefix hidden>SKC SuCasa Inmobiliaria </span><textarea name="message" rows="5" required></textarea></span><span class="sms-counter" data-sms-counter hidden>0/160 caracteres</span></label><label data-whatsapp-media-wrap hidden>URL documento/video<input name="whatsapp_media_url" placeholder="https://..."></label><label data-whatsapp-button-wrap hidden>Parámetro botón URL<input name="whatsapp_button_url_parameter" placeholder="{{link}} o ruta"></label></div><div class="campaign-actions"><button type="button" class="button ghost" data-modal-close>Cancelar</button><button class="primary">Agregar a cola</button></div></form></div></section></div>

<div class="modal" id="my-deliveries-modal" hidden><div class="modal-backdrop" data-modal-close></div><section class="modal-panel" role="dialog" aria-modal="true" aria-label="Mis envíos"><button type="button" class="modal-close" data-modal-close aria-label="Cerrar">&times;</button><div id="my-deliveries-content" class="modal-loading">Cargando…</div></section></div>
