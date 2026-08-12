<div data-actor-table>
  <?php if (!empty($filters['campaign_tag'])) : ?>
    <?php
      $campaignChannelLabel = match ((string) ($filters['campaign_channel'] ?? '')) {
        'email' => ' por Email',
        'sms' => ' por SMS',
        'whatsapp' => ' por WhatsApp',
        default => '',
      };
    ?>
    <div class="notice" aria-live="polite"><strong><?= e((int) $data['total']) ?> disponibles</strong> después de ocultar quienes ya fueron agregados<?= e($campaignChannelLabel) ?> a “<?= e($filters['campaign_tag']) ?>”.</div>
  <?php endif; ?>
  <form id="actor-selection-form">
    <div class="table-wrap"><table><thead><tr>
      <?php if ($showBulkToolbar) : ?><th><input type="checkbox" data-check-all aria-label="Seleccionar todos"></th><?php endif; ?>
      <?php foreach ($vista as $column) : ?><th><?= e($labels[$column] ?? ucfirst((string) $column)) ?></th><?php endforeach; ?><th>Acciones</th>
    </tr></thead><tbody>
    <?php if (!$data['items']) : ?><tr><td colspan="<?= e(count($vista) + ($showBulkToolbar ? 2 : 1)) ?>"><div class="empty-state"><h3>Sin resultados</h3><p>Ajusta los filtros o importa nuevos registros.</p></div></td></tr><?php endif; ?>
    <?php foreach ($data['items'] as $row) :
      $actorId = (int) $row['_ID'];
      $authorId = (int) ($row['cct_author_id'] ?? 0);
      $canEdit = \App\PermissionService::canEdit($type, $actorId, $authorId);
      $canFullEdit = \App\PermissionService::canFullEdit($type, $actorId, $authorId);
      $canDeleteRow = \App\PermissionService::canDelete($type, $actorId, $authorId);
      $phone = (string) ($row[$config['phone'] ?? 'celular'] ?? '');
      $email = (string) ($row[$config['email'] ?? 'correo'] ?? '');
      $actorName = (string) ($row[$config['name'] ?? 'nombre'] ?? 'Actor');
    ?><tr>
      <?php if ($showBulkToolbar) : ?><td><input type="checkbox" name="ids[]" value="<?= e($actorId) ?>" aria-label="Seleccionar <?= e($actorName) ?>"></td><?php endif; ?>
      <?php foreach ($vista as $column) : ?><td><?= e((string) ($row[$column] ?? '—')) ?></td><?php endforeach; ?>
      <td class="actions">
        <?php if ($canEdit) : ?><button type="button" class="button slim ghost" data-open-actor="<?= e($actorId) ?>" data-actor-type="<?= e($type) ?>"><?= $canFullEdit ? 'Editar' : 'Preferencias de comunicación' ?></button><?php endif; ?>
        <?php if ($email !== '') : ?><button type="button" class="button slim ghost" data-open-single-send="<?= e($actorId) ?>" data-send-channel="email" data-actor-name="<?= e($actorName) ?>">Email</button><?php endif; ?>
        <?php if ($phone !== '') : ?><button type="button" class="button slim ghost" data-open-single-send="<?= e($actorId) ?>" data-send-channel="sms" data-actor-name="<?= e($actorName) ?>">SMS</button><?php endif; ?>
        <?php if ($phone !== '') : ?><button type="button" class="button slim ghost" data-open-single-send="<?= e($actorId) ?>" data-send-channel="whatsapp" data-actor-name="<?= e($actorName) ?>">WhatsApp</button><?php endif; ?>
        <?php if ($canDeleteRow) : ?><button type="submit" class="button slim danger" form="delete-<?= e($actorId) ?>" onclick="return confirm('¿Eliminar este actor?')">Eliminar</button><?php endif; ?>
      </td>
    </tr><?php endforeach; ?>
    </tbody></table></div>
  </form>
  <?php foreach ($data['items'] as $row) : ?><form method="post" id="delete-<?= e((int) $row['_ID']) ?>"><input type="hidden" name="action" value="delete_actor"><input type="hidden" name="_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="type" value="<?= e($type) ?>"><input type="hidden" name="id" value="<?= e((int) $row['_ID']) ?>"></form><?php endforeach; ?>
  <nav class="pagination"><?php if ($pageNumber > 1) : ?><a href="<?= e(url(['page'=>'actores','type'=>$type,'q'=>$search,'p'=>$pageNumber-1,'f'=>$filters])) ?>">Anterior</a><?php endif; ?><span>Página <?= e($pageNumber) ?> de <?= e($data['pages']) ?></span><?php if ($pageNumber < $data['pages']) : ?><a href="<?= e(url(['page'=>'actores','type'=>$type,'q'=>$search,'p'=>$pageNumber+1,'f'=>$filters])) ?>">Siguiente</a><?php endif; ?></nav>
</div>
