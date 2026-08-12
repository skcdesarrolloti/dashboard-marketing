<header class="page-head hero-head">
  <div><span class="eyebrow">Contenido reutilizable</span><h1>Plantillas</h1><p>Biblioteca de mensajes para email, SMS y WhatsApp.</p></div>
  <a class="button primary" href="<?=e(url(['page'=>'plantilla-editor']))?>">Crear nueva plantilla</a>
</header>
<?php if(isset($_GET['deleted'])):?><div class="notice">Plantilla eliminada.</div><?php endif;?>
<?php if(isset($_GET['test_sent'])):?><div class="notice">Correo de prueba encolado correctamente.</div><?php endif;?>
<section class="panel">
  <div class="panel-head"><h2>Biblioteca</h2><span><?=e((int)$data['total'])?> plantillas</span></div>
  <form class="filter-panel compact-filters" method="get" data-autosubmit>
    <input type="hidden" name="page" value="plantillas">
    <div><label>Buscar</label><input name="q" value="<?=e($filters['q']??'')?>" placeholder="Nombre, asunto o contenido"></div>
    <div><label>Tipo</label><select name="tipo"><option value="">Todos</option><?php foreach(['email','sms','whatsapp'] as $type):?><option value="<?=e($type)?>" <?=($filters['tipo']??'')===$type?'selected':''?>><?=e(strtoupper($type))?></option><?php endforeach;?></select></div>
    <div class="filter-actions"><button>Aplicar</button><a class="button ghost" href="<?=e(url(['page'=>'plantillas']))?>">Limpiar</a></div>
  </form>
  <div class="table-wrap"><table><thead><tr><th>Nombre</th><th>Tipo</th><th>Asunto</th><th>Contenido</th><th>Acciones</th></tr></thead><tbody>
  <?php foreach($data['items'] as $row):?><tr>
    <td><strong><?=e($row['nombre']??'')?></strong></td><td><span class="badge"><?=e(strtoupper((string)($row['tipo']??'')))?></span></td><td><?=e($row['asunto']??'')?></td><td><?=e(mb_strimwidth(strip_tags((string)($row['contenido']??'')),0,110,'…'))?></td>
    <td class="actions"><a class="button ghost" href="<?=e(url(['page'=>'plantilla-editor','id'=>(int)$row['_ID']]))?>">Editar</a><button form="dup-template-<?=e((int)$row['_ID'])?>">Duplicar</button><?php if(($row['tipo']??'email')==='email'):?><button form="test-template-<?=e((int)$row['_ID'])?>">Enviar prueba</button><?php endif;?><button class="danger" form="delete-template-<?=e((int)$row['_ID'])?>" onclick="return confirm('¿Eliminar esta plantilla?')">Eliminar</button></td>
  </tr><?php endforeach;?>
  <?php if(!$data['items']):?><tr><td colspan="5" class="empty-state">No hay plantillas para estos filtros.</td></tr><?php endif;?>
  </tbody></table></div>
  <?php foreach($data['items'] as $row):?>
    <form method="post" id="dup-template-<?=e((int)$row['_ID'])?>"><input type="hidden" name="action" value="duplicate_template"><input type="hidden" name="_token" value="<?=e(csrf_token())?>"><input type="hidden" name="id" value="<?=e((int)$row['_ID'])?>"></form>
    <form method="post" id="delete-template-<?=e((int)$row['_ID'])?>"><input type="hidden" name="action" value="delete_template"><input type="hidden" name="_token" value="<?=e(csrf_token())?>"><input type="hidden" name="id" value="<?=e((int)$row['_ID'])?>"></form>
    <?php if(($row['tipo']??'email')==='email'):?><form method="post" id="test-template-<?=e((int)$row['_ID'])?>" onsubmit="const v=prompt('Correo de destino','<?=e((string)(\App\Auth::user()['correo']??''))?>');if(!v)return false;this.email.value=v;"><input type="hidden" name="action" value="send_test_template"><input type="hidden" name="_token" value="<?=e(csrf_token())?>"><input type="hidden" name="id" value="<?=e((int)$row['_ID'])?>"><input type="hidden" name="email"></form><?php endif;?>
  <?php endforeach;?>
  <div class="pagination"><?php if($pageNumber>1):?><a href="<?=e(url(['page'=>'plantillas','q'=>$filters['q'],'tipo'=>$filters['tipo'],'p'=>$pageNumber-1]))?>">Anterior</a><?php endif;?><span>Página <?=e($pageNumber)?> de <?=e((int)$data['pages'])?></span><?php if($pageNumber<$data['pages']):?><a href="<?=e(url(['page'=>'plantillas','q'=>$filters['q'],'tipo'=>$filters['tipo'],'p'=>$pageNumber+1]))?>">Siguiente</a><?php endif;?></div>
</section>
