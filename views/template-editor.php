<?php
$templateType = strtolower((string) ($edit['tipo'] ?? 'email'));
$whatsappConfig = $templateType === 'whatsapp' ? \App\TemplateRepository::whatsappConfig($edit ?: ['tipo' => 'whatsapp']) : [];
$editorContent = $templateType === 'whatsapp'
  ? (string) ($whatsappConfig['body_text'] ?? '')
  : (string) ($edit['contenido'] ?? '');
?>
<header class="page-head hero-head compact-template-head">
  <div><span class="eyebrow"><?=$edit?'Edición':'Creación'?></span><h1><?=$edit?'Editar plantilla':'Nueva plantilla'?></h1><p>Configura el mensaje y comprueba el resultado sin salir del editor.</p></div>
  <a class="button ghost" href="<?=e(url(['page'=>'plantillas']))?>">← Regresar a plantillas</a>
</header>
<?php if(isset($_GET['saved'])||isset($_GET['duplicated'])):?><div class="notice">Plantilla guardada correctamente.</div><?php endif;?>
<section class="grid two template-editor-workspace">
  <div class="panel compact-editor-panel">
    <div class="panel-head"><h2>Contenido</h2><span data-current-template-channel><?=e(strtoupper($templateType))?></span></div>
    <form method="post" class="stack template-editor">
      <input type="hidden" name="action" value="save_template"><input type="hidden" name="_token" value="<?=e(csrf_token())?>"><input type="hidden" name="_ID" value="<?=e((int)($edit['_ID']??0))?>">
      <label>Nombre<input name="nombre" value="<?=e($edit['nombre']??'')?>" required></label>
      <label>Canal<select name="tipo" data-template-type><?php foreach(['email','sms','whatsapp'] as $type):?><option value="<?=e($type)?>" <?=$templateType===$type?'selected':''?>><?=e(strtoupper($type))?></option><?php endforeach;?></select></label>
      <div data-email-only><label>Asunto<input name="asunto" value="<?=e($edit['asunto']??'')?>" placeholder="Admite {{nombre}} y otras variables"></label></div>
      <div class="whatsapp-official-fields" data-whatsapp-only hidden>
        <label>Nombre oficial en Meta<input name="whatsapp_template_name" value="<?=e($whatsappConfig['official_template'] ?? '')?>" placeholder="skc_marketing_texto_generico_v1"></label>
        <label>Idioma<input name="whatsapp_language" value="<?=e($whatsappConfig['language'] ?? 'es_CO')?>" placeholder="es_CO"></label>
        <label>Categoría<select name="whatsapp_category"><?php foreach(['MARKETING','UTILITY','AUTHENTICATION'] as $category):?><option value="<?=e($category)?>" <?=($whatsappConfig['category'] ?? 'MARKETING')===$category?'selected':''?>><?=e($category)?></option><?php endforeach;?></select></label>
        <label>Header<select name="whatsapp_header_type" data-whatsapp-header-type><?php foreach(['none'=>'Sin header','image'=>'Imagen','video'=>'Video','document'=>'Documento'] as $value=>$label):?><option value="<?=e($value)?>" <?=($whatsappConfig['header_type'] ?? 'none')===$value?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></label>
        <label data-whatsapp-header-url>URL pública del header<input name="whatsapp_header_url" value="<?=e($whatsappConfig['header_url'] ?? '')?>" placeholder="https://..."></label>
        <label data-whatsapp-header-filename>Nombre del documento<input name="whatsapp_header_filename" value="<?=e($whatsappConfig['header_filename'] ?? '')?>" placeholder="brochure-skc.pdf"></label>
        <label>Botón<select name="whatsapp_button_type" data-whatsapp-button-type><?php foreach(['none'=>'Sin botón dinámico','url_dynamic'=>'URL dinámica aprobada en Meta'] as $value=>$label):?><option value="<?=e($value)?>" <?=($whatsappConfig['button_type'] ?? 'none')===$value?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></label>
        <label data-whatsapp-button-parameter>Parámetro del botón<input name="whatsapp_button_url_parameter" value="<?=e($whatsappConfig['button_url_parameter'] ?? '')?>" placeholder="{{link}} o ruta-dinamica"></label>
      </div>
      <label>Contenido</label>
      <div class="code-editor-shell" data-email-editor>
        <div class="code-editor-bar"><span>HTML</span><small>Emmet · Tab para expandir · Ctrl+F para buscar · Ctrl+Espacio para sugerencias</small></div>
        <div id="html-code-editor" data-html-code-editor aria-label="Editor avanzado de código HTML"></div>
      </div>
      <div data-plain-editor hidden>
        <textarea rows="9" data-plain-template-content placeholder="Escribe el mensaje en texto plano"></textarea>
      </div>
      <textarea name="contenido" required data-template-content class="code-editor-source" aria-hidden="true" tabindex="-1"><?=e($editorContent)?></textarea>
      <div class="editor-toolbar">
        <div class="editor-tool-group variable-picker"><span>Variables</span><select data-token-select aria-label="Insertar variable"><option value="">Selecciona una variable…</option><?php foreach(['{{nombre}}'=>'Nombre','{{rol_persona}}'=>'Rol de la persona','{{correo}}'=>'Correo','{{celular}}'=>'Celular','{{documento}}'=>'Documento','{{ciudad}}'=>'Ciudad','{{indicativo}}'=>'Indicativo','{{tipo_documento}}'=>'Tipo de documento','{{link}}'=>'Enlace'] as $token=>$label):?><option value="<?=e($token)?>"><?=e($label)?> · <?=e($token)?></option><?php endforeach;?></select></div>
        <div class="editor-tool-group" data-whatsapp-only hidden><span>Formato WhatsApp</span><div><button type="button" data-wrap-plain="*">Negrita</button><button type="button" data-wrap-plain="_">Cursiva</button><button type="button" data-wrap-plain="~">Tachado</button></div></div>
        <div class="editor-tool-group" data-email-only><span>Edición HTML</span><div><button type="button" data-format-html>Formatear</button><button type="button" data-wrap-template="b">Negrita</button><button type="button" data-wrap-template="i">Cursiva</button><button type="button" data-wrap-template="a">Enlace</button></div></div>
        <div class="editor-tool-group" data-email-only><span>Bases</span><div><button type="button" data-insert-template-base="corporate">Corporativa</button><button type="button" data-insert-template-base="newsletter" data-banner-url="<?=e(system_image('banner','https://sucasainmobiliaria.com.co/wp-content/uploads/jet-form-builder/890b2cf35d4e966d9ecf579e80554389/2026/01/banner-sitio-web-.png'))?>" data-revista-url="<?=e(system_image('link_revista','https://sucasainmobiliaria.com.co/esencia-inmobiliaria-edicion-enero-2026?utm_source=Notificaciones%20pagina%20web&utm_medium=Email'))?>">Newsletter</button></div></div>
      </div>
      <div class="template-meta"><span><strong data-char-count>0</strong> caracteres <em data-sms-limit hidden>· máximo 160</em></span></div><p class="form-message" data-template-guidance></p>
      <p class="form-message whatsapp-meta-guide" data-whatsapp-only hidden>Para Meta, cada variable del cuerpo se aprueba como {{1}}, {{2}}, etc. Aquí puedes escribirlas como {{nombre}}, {{ciudad}}, {{link}}; el envío las convierte en componentes oficiales.</p>
      <div class="campaign-actions"><button class="primary">Guardar plantilla</button><?php if($edit):?><a class="button ghost" href="<?=e(url(['page'=>'plantilla-editor']))?>">Crear otra</a><?php endif;?></div>
    </form>
  </div>
  <aside class="panel compact-preview-panel"><div class="panel-head"><h2>Vista previa</h2><span>Datos de ejemplo</span></div><div class="template-preview" data-template-preview>Escribe contenido para visualizarlo.</div></aside>
</section>
