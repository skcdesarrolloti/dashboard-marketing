<div class="modal-content">
  <h3 class="modal-title">Mis envíos</h3>
  <p class="modal-subtitle">Resumen rápido de tu cola y tus últimos mensajes.</p>
  <section class="metrics mini-metrics">
    <article><span>Email pendientes</span><strong><?=e((int)$myQueue['email'])?></strong></article>
    <article><span>SMS pendientes</span><strong><?=e((int)$myQueue['sms'])?></strong></article>
    <article><span>Estado de mi cola</span><strong><?=!empty($myQueue['paused'])?'Pausada':'Activa'?></strong></article>
  </section>
  <div class="table-wrap"><table><thead><tr><th>Canal</th><th>Estado</th><th>Destinatario</th><th>Asunto</th><th>Fecha</th></tr></thead><tbody>
  <?php foreach($recent as $row):?><tr><td><span class="badge"><?=e(strtoupper((string)($row['channel']??'')))?></span></td><td><span class="status <?=e(strtolower((string)($row['status']??'')))?>"><?=e($row['status']??'')?></span></td><td><?=e($row['destination_name']?:$row['destination']??'')?></td><td><?=e($row['subject']??'')?></td><td><?=e($row['sent_at']?:$row['created_at']??'')?></td></tr><?php endforeach;?>
  <?php if(!$recent):?><tr><td colspan="5">Aún no tienes envíos registrados.</td></tr><?php endif;?></tbody></table></div>
  <div class="campaign-actions"><button type="button" class="button ghost" data-modal-close>Cerrar</button><a class="button primary" href="<?=e(url(['page'=>'envios']))?>">Ir a Envíos y colas</a></div>
</div>
