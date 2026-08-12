<?php

declare(strict_types=1);

$meetings = (array) ($meetings ?? []);
$summary = (array) ($summary ?? []);
$selected = (array) ($selected ?? []);
$employees = (array) ($employees ?? []);
$filters = (array) ($filters ?? []);
$mode = (string) ($mode ?? 'dashboard');
$canManage = (bool) ($canManage ?? false);
$canReopen = (bool) ($canReopen ?? false);
$canDelete = (bool) ($canDelete ?? false);
$meetingStatuses = [
  'draft' => 'Borrador',
  'in_progress' => 'En curso',
  'completed' => 'Finalizada',
  'archived' => 'Archivada',
];
$filterStatuses = ['upcoming' => 'Próximas reuniones', 'history' => 'Historial completo'] + $meetingStatuses;
$monthNames = ['01' => 'ENE', '02' => 'FEB', '03' => 'MAR', '04' => 'ABR', '05' => 'MAY', '06' => 'JUN', '07' => 'JUL', '08' => 'AGO', '09' => 'SEP', '10' => 'OCT', '11' => 'NOV', '12' => 'DIC'];
$actionStatuses = [
  'pending' => 'Pendiente',
  'in_progress' => 'En proceso',
  'completed' => 'Realizada',
  'not_completed' => 'No realizada',
];
$sectionMeta = [
  'achievement' => ['title' => 'Logros y trabajo realizado', 'short' => 'Logro', 'help' => 'Registra lo que se ejecutó y el resultado alcanzado.'],
  'improvement' => ['title' => 'Puntos de mejora', 'short' => 'Mejora', 'help' => 'Documenta el diagnóstico y la oportunidad de optimización.'],
  'action' => ['title' => 'Plan de acción', 'short' => 'Acción', 'help' => 'Define responsables, fechas y compromisos verificables.'],
];
$statusClass = static fn (string $status): string => preg_replace('/[^a-z_]/', '', $status) ?: 'draft';
$formatDate = static function (?string $date, bool $withTime = false): string {
  if (!$date) return 'Sin fecha';
  $timestamp = strtotime($date);
  return $timestamp ? date($withTime ? 'd/m/Y H:i' : 'd/m/Y', $timestamp) : (string) $date;
};
$selectedId = (int) ($selected['id'] ?? 0);
$selectedStatus = (string) ($selected['status'] ?? '');
$editable = $canManage && $selectedId > 0 && in_array($selectedStatus, ['draft', 'in_progress'], true);
$items = (array) ($selected['items'] ?? ['achievement' => [], 'improvement' => [], 'action' => []]);
$progress = (array) ($selected['progress'] ?? ['total' => 0, 'reviewed' => 0, 'completed' => 0, 'not_completed' => 0, 'review_percent' => 0, 'compliance_percent' => 0]);
$csrf = csrf_token();
$renderParticipantPicker = static function (string $id, array $employeeOptions, array $participants = [], string $help = ''): void {
  $selectedNames = [];
  foreach ($participants as $participant) {
    $participantId = (int) ($participant['id'] ?? 0);
    if ($participantId > 0) $selectedNames[$participantId] = (string) ($participant['name'] ?? 'Funcionario');
  }
  $options = [];
  foreach ($employeeOptions as $employee) {
    $employeeId = (int) ($employee['id'] ?? 0);
    if ($employeeId > 0) $options[$employeeId] = ['id' => $employeeId, 'name' => (string) ($employee['nombre'] ?? 'Funcionario'), 'historical' => false];
  }
  foreach ($selectedNames as $participantId => $name) {
    if (!isset($options[$participantId])) $options[$participantId] = ['id' => $participantId, 'name' => $name, 'historical' => true];
  }
  uasort($options, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));
  ?>
  <div class="participant-picker span-2" data-participant-picker>
    <span class="participant-field-label">Participantes</span>
    <div class="participant-selected" data-participant-selected aria-live="polite">
      <?php foreach ($options as $option) : ?><?php if (isset($selectedNames[$option['id']])) : ?><span data-participant-chip="<?= e($option['id']) ?>"><?= e($option['name']) ?><button type="button" data-participant-remove="<?= e($option['id']) ?>" aria-label="Quitar a <?= e($option['name']) ?>">×</button></span><?php endif; ?><?php endforeach; ?>
      <small data-participant-placeholder <?= $selectedNames ? 'hidden' : '' ?>>Ningún participante seleccionado</small>
    </div>
    <div class="participant-combobox">
      <input type="search" id="<?= e($id) ?>" data-participant-search autocomplete="off" placeholder="Buscar funcionario por nombre…" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="<?= e($id) ?>-options">
      <div class="participant-options" id="<?= e($id) ?>-options" data-participant-options role="listbox" hidden>
        <?php foreach ($options as $option) : ?>
          <label data-participant-option data-search="<?= e(mb_strtolower($option['name'])) ?>">
            <input type="checkbox" name="participant_ids[]" value="<?= e($option['id']) ?>" data-participant-checkbox data-participant-name="<?= e($option['name']) ?>" <?= isset($selectedNames[$option['id']]) ? 'checked' : '' ?>>
            <span><strong><?= e($option['name']) ?></strong><?php if ($option['historical']) : ?><small>Registro histórico</small><?php else : ?><small>Funcionario activo</small><?php endif; ?></span>
          </label>
        <?php endforeach; ?>
        <p class="participant-no-results" data-participant-empty <?= $options ? 'hidden' : '' ?>>No hay funcionarios disponibles.</p>
      </div>
    </div>
    <small class="participant-help"><?= e($help !== '' ? $help : 'Busca y marca uno o varios funcionarios.') ?></small>
  </div>
  <?php
};
?>

<section class="meetings-module <?= $mode === 'guide' ? 'is-guide-mode' : '' ?>"
  data-meetings-root
  data-token="<?= e($csrf) ?>"
  data-meeting-id="<?= e($selectedId) ?>">

  <div class="meetings-live" role="status" aria-live="polite" data-meetings-live></div>

  <?php if (!empty($error)) : ?>
    <div class="meeting-notice is-error" role="alert"><?= e((string) $error) ?></div>
  <?php endif; ?>
  <?php if (!empty($success)) : ?>
    <div class="meeting-notice is-success" role="status"><?= e((string) $success) ?></div>
  <?php endif; ?>

  <?php if ($selected && $mode === 'guide') : ?>
    <?php
      $previousActions = array_values(array_filter((array) ($items['action'] ?? []), static fn (array $item): bool => (int) ($item['carried_from_item_id'] ?? 0) > 0));
      $newActions = array_values(array_filter((array) ($items['action'] ?? []), static fn (array $item): bool => (int) ($item['carried_from_item_id'] ?? 0) === 0));
      $guideSteps = [
        ['key' => 'achievements', 'label' => 'Logros'],
        ['key' => 'improvements', 'label' => 'Mejoras'],
        ['key' => 'previous', 'label' => 'Seguimiento anterior'],
        ['key' => 'actions', 'label' => 'Plan nuevo'],
        ['key' => 'conclusions', 'label' => 'Conclusiones'],
      ];
    ?>
    <header class="guide-header">
      <div>
        <a class="meeting-back-link" href="<?= e(url_page('reuniones', ['id' => $selectedId])) ?>">← Volver al detalle</a>
        <p class="meeting-kicker">Modo guía · <?= e($formatDate((string) ($selected['meeting_date'] ?? ''))) ?></p>
        <h1><?= e((string) ($selected['title'] ?? 'Reunión')) ?></h1>
        <p><?= e((string) ($selected['objective'] ?? 'Conduce la reunión paso a paso y registra los acuerdos.')) ?></p>
      </div>
      <div class="guide-progress-card">
        <span>Avance de revisión</span>
        <strong data-progress-reviewed><?= e((int) ($progress['reviewed'] ?? 0)) ?>/<?= e((int) ($progress['total'] ?? 0)) ?></strong>
        <div class="meeting-progress" aria-label="Avance de acciones"><span data-progress-bar style="width:<?= e((int) ($progress['review_percent'] ?? 0)) ?>%"></span></div>
      </div>
    </header>

    <nav class="guide-step-nav" aria-label="Pasos de la reunión">
      <?php foreach ($guideSteps as $index => $step) : ?>
        <button type="button" data-guide-jump="<?= e($index) ?>" class="<?= $index === 0 ? 'is-active' : '' ?>">
          <span><?= e($index + 1) ?></span><?= e($step['label']) ?>
        </button>
      <?php endforeach; ?>
    </nav>

    <div class="guide-stage" data-guide-stage>
      <article class="guide-step is-active" data-guide-step="0" tabindex="-1">
        <p class="meeting-kicker">Paso 1 de 5</p><h2>Logros y trabajo realizado</h2>
        <p class="guide-lead">Empieza reconociendo qué se ejecutó y qué resultado produjo.</p>
        <div class="guide-points">
          <?php foreach ((array) ($items['achievement'] ?? []) as $item) : ?>
            <section><span class="guide-point-number"><?= e((int) ($item['sort_order'] ?? 0) / 10) ?></span><div><h3><?= e((string) $item['title']) ?></h3><p><?= nl2br(e((string) ($item['content'] ?? ''))) ?></p></div></section>
          <?php endforeach; ?>
          <?php if (empty($items['achievement'])) : ?><div class="meeting-empty compact"><strong>Sin logros registrados</strong><p>Puede continuar y agregarlos posteriormente si la reunión sigue abierta.</p></div><?php endif; ?>
        </div>
      </article>

      <article class="guide-step" data-guide-step="1" tabindex="-1" hidden>
        <p class="meeting-kicker">Paso 2 de 5</p><h2>Puntos de mejora</h2>
        <p class="guide-lead">Revisa el diagnóstico antes de convertirlo en compromisos.</p>
        <div class="guide-points">
          <?php foreach ((array) ($items['improvement'] ?? []) as $item) : ?>
            <section><span class="guide-point-number"><?= e((int) ($item['sort_order'] ?? 0) / 10) ?></span><div><h3><?= e((string) $item['title']) ?></h3><p><?= nl2br(e((string) ($item['content'] ?? ''))) ?></p><?php if (!empty($item['observation'])) : ?><small>Observación: <?= e((string) $item['observation']) ?></small><?php endif; ?></div></section>
          <?php endforeach; ?>
          <?php if (empty($items['improvement'])) : ?><div class="meeting-empty compact"><strong>Sin puntos de mejora</strong><p>No hay diagnósticos registrados para esta reunión.</p></div><?php endif; ?>
        </div>
      </article>

      <?php foreach ([2 => ['items' => $previousActions, 'title' => 'Seguimiento de la reunión anterior', 'lead' => 'Evalúa los compromisos heredados antes de definir el plan nuevo.'], 3 => ['items' => $newActions, 'title' => 'Plan de acción nuevo', 'lead' => 'Confirma responsables y registra el estado de cada compromiso.']] as $stepIndex => $stepData) : ?>
        <article class="guide-step" data-guide-step="<?= e($stepIndex) ?>" tabindex="-1" hidden>
          <p class="meeting-kicker">Paso <?= e($stepIndex + 1) ?> de 5</p><h2><?= e($stepData['title']) ?></h2>
          <p class="guide-lead"><?= e($stepData['lead']) ?></p>
          <div class="guide-actions">
            <?php foreach ($stepData['items'] as $item) : ?>
              <?php $itemStatus = (string) ($item['status'] ?? 'pending'); ?>
              <section class="guide-action status-<?= e($statusClass($itemStatus)) ?>" data-action-card data-item-id="<?= e((int) $item['id']) ?>">
                <div class="guide-action-copy">
                  <div class="meeting-card-topline"><span class="meeting-status status-<?= e($statusClass($itemStatus)) ?>" data-action-label><?= e($actionStatuses[$itemStatus] ?? $itemStatus) ?></span><?php if ($stepIndex === 2) : ?><span class="carried-chip">Arrastre anterior</span><?php endif; ?></div>
                  <h3><?= e((string) $item['title']) ?></h3>
                  <?php if (!empty($item['content'])) : ?><p><?= nl2br(e((string) $item['content'])) ?></p><?php endif; ?>
                  <dl><div><dt>Responsable</dt><dd><?= e((string) ($item['responsible_name'] ?: 'Sin asignar')) ?></dd></div><div><dt>Fecha límite</dt><dd><?= e($formatDate($item['due_date'] ?? null)) ?></dd></div></dl>
                  <p class="failure-copy" data-failure-copy <?= empty($item['failure_reason']) ? 'hidden' : '' ?>>Motivo: <span><?= e((string) ($item['failure_reason'] ?? '')) ?></span></p>
                </div>
                <?php if ($editable) : ?>
                  <div class="quick-status" aria-label="Actualizar estado de <?= e((string) $item['title']) ?>">
                    <button type="button" class="button status-progress" data-action-status="in_progress">En proceso</button>
                    <button type="button" class="button status-done" data-action-status="completed">Hecho</button>
                    <button type="button" class="button status-failed" data-action-reason-open>No realizado</button>
                  </div>
                  <div class="failure-editor" data-action-reason hidden>
                    <label>Motivo obligatorio<textarea rows="3" maxlength="12000" data-action-reason-text placeholder="Explica qué impidió completar la acción"></textarea></label>
                    <div><button type="button" class="button ghost" data-action-reason-cancel>Cancelar</button><button type="button" class="button status-failed" data-action-status="not_completed">Guardar como no realizada</button></div>
                  </div>
                <?php endif; ?>
              </section>
            <?php endforeach; ?>
            <?php if (empty($stepData['items'])) : ?><div class="meeting-empty"><strong>No hay acciones en este bloque</strong><p>Continúa al siguiente paso de la reunión.</p></div><?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>

      <article class="guide-step" data-guide-step="4" tabindex="-1" hidden>
        <p class="meeting-kicker">Paso 5 de 5</p><h2>Conclusiones y cierre</h2>
        <p class="guide-lead">Resume las decisiones tomadas. Al finalizar, la reunión quedará bloqueada.</p>
        <?php if ($editable) : ?>
          <form method="post" class="guide-conclusions" data-confirm="¿Finalizar la reunión? Después solo un administrador podrá reabrirla.">
            <input type="hidden" name="action" value="meeting_complete"><input type="hidden" name="_token" value="<?= e($csrf) ?>"><input type="hidden" name="meeting_id" value="<?= e($selectedId) ?>">
            <label>Conclusiones<textarea name="conclusions" rows="9" maxlength="12000" placeholder="Decisiones, acuerdos y asuntos que deben quedar registrados"><?= e((string) ($selected['conclusions'] ?? '')) ?></textarea></label>
            <div class="guide-summary"><span>Acciones revisadas <strong data-progress-reviewed><?= e((int) ($progress['reviewed'] ?? 0)) ?>/<?= e((int) ($progress['total'] ?? 0)) ?></strong></span><span>Cumplimiento <strong data-progress-compliance><?= e((int) ($progress['compliance_percent'] ?? 0)) ?>%</strong></span></div>
            <button class="button primary meeting-complete" type="submit">Finalizar y bloquear reunión</button>
          </form>
        <?php else : ?>
          <div class="meeting-readonly-conclusions"><p><?= nl2br(e((string) ($selected['conclusions'] ?: 'No se registraron conclusiones.'))) ?></p></div>
        <?php endif; ?>
      </article>
    </div>

    <footer class="guide-controls">
      <button type="button" class="button ghost" data-guide-prev disabled>Anterior</button>
      <span><strong data-guide-current>1</strong> de 5</span>
      <button type="button" class="button primary" data-guide-next>Siguiente</button>
    </footer>

  <?php elseif ($selected) : ?>
    <header class="meeting-detail-hero">
      <div>
        <a class="meeting-back-link" href="<?= e(url_page('reuniones')) ?>">← Todas las reuniones</a>
        <div class="meeting-card-topline"><span class="meeting-status status-<?= e($statusClass($selectedStatus)) ?>"><?= e($meetingStatuses[$selectedStatus] ?? $selectedStatus) ?></span><span><?= e($formatDate((string) ($selected['meeting_date'] ?? ''))) ?></span></div>
        <h1><?= e((string) ($selected['title'] ?? 'Reunión')) ?></h1>
        <p><?= e((string) ($selected['objective'] ?: 'Sin objetivo registrado.')) ?></p>
      </div>
      <div class="meeting-detail-actions">
        <?php if ($editable && $selectedStatus === 'draft') : ?><form method="post"><input type="hidden" name="action" value="meeting_start"><input type="hidden" name="_token" value="<?= e($csrf) ?>"><input type="hidden" name="meeting_id" value="<?= e($selectedId) ?>"><button class="button primary">Iniciar modo guía</button></form><?php endif; ?>
        <?php if ($selectedStatus === 'in_progress') : ?><a class="button primary" href="<?= e(url_page('reuniones', ['id' => $selectedId, 'mode' => 'guide'])) ?>">Continuar modo guía</a><?php endif; ?>
        <?php if ($selectedStatus === 'completed' && $canReopen) : ?><form method="post" data-confirm="¿Reabrir esta reunión para permitir cambios?"><input type="hidden" name="action" value="meeting_reopen"><input type="hidden" name="_token" value="<?= e($csrf) ?>"><input type="hidden" name="meeting_id" value="<?= e($selectedId) ?>"><button class="button ghost">Reabrir</button></form><?php endif; ?>
      </div>
    </header>

    <div class="meeting-overview-grid">
      <article><span>Participantes</span><strong><?= e(count((array) ($selected['participants'] ?? []))) ?></strong><small><?= e(implode(', ', array_column((array) ($selected['participants'] ?? []), 'name')) ?: 'Sin participantes') ?></small></article>
      <article><span>Acciones revisadas</span><strong data-progress-reviewed><?= e((int) ($progress['reviewed'] ?? 0)) ?>/<?= e((int) ($progress['total'] ?? 0)) ?></strong><div class="meeting-progress"><span data-progress-bar style="width:<?= e((int) ($progress['review_percent'] ?? 0)) ?>%"></span></div></article>
      <article><span>Cumplimiento</span><strong data-progress-compliance><?= e((int) ($progress['compliance_percent'] ?? 0)) ?>%</strong><small>Solo acciones evaluadas</small></article>
    </div>

    <?php if ($editable) : ?>
      <details class="meeting-editor-panel">
        <summary>Editar datos de la reunión</summary>
        <form method="post" class="meeting-form meeting-grid-form">
          <input type="hidden" name="action" value="meeting_save"><input type="hidden" name="_token" value="<?= e($csrf) ?>"><input type="hidden" name="id" value="<?= e($selectedId) ?>">
          <label>Título <input name="title" maxlength="220" required value="<?= e((string) $selected['title']) ?>"></label>
          <label>Fecha <input type="date" name="meeting_date" required value="<?= e((string) $selected['meeting_date']) ?>"></label>
          <label class="span-2">Objetivo <textarea name="objective" rows="3" maxlength="8000"><?= e((string) ($selected['objective'] ?? '')) ?></textarea></label>
          <?php $renderParticipantPicker('meeting-edit-participants', $employees, (array) ($selected['participants'] ?? []), 'Los nombres seleccionados se conservarán en el historial de la reunión.'); ?>
          <label class="span-2">Conclusiones provisionales <textarea name="conclusions" rows="4" maxlength="12000"><?= e((string) ($selected['conclusions'] ?? '')) ?></textarea></label>
          <div class="span-2 form-actions"><button class="button primary">Guardar cambios</button></div>
        </form>
      </details>
    <?php endif; ?>

    <?php foreach ($sectionMeta as $section => $meta) : ?>
      <section class="meeting-content-section" id="meeting-section-<?= e($section) ?>">
        <header><div><p class="meeting-kicker">0<?= e(array_search($section, array_keys($sectionMeta), true) + 1) ?></p><h2><?= e($meta['title']) ?></h2><p><?= e($meta['help']) ?></p></div><?php if ($editable) : ?><button type="button" class="button ghost" data-item-open data-section="<?= e($section) ?>">Agregar <?= e(mb_strtolower($meta['short'])) ?></button><?php endif; ?></header>
        <div class="meeting-item-list">
          <?php foreach ((array) ($items[$section] ?? []) as $item) : ?>
            <?php $itemStatus = (string) ($item['status'] ?? 'recorded'); ?>
            <article class="meeting-item <?= $section === 'action' ? 'status-' . e($statusClass($itemStatus)) : '' ?>" data-action-card data-item-id="<?= e((int) $item['id']) ?>">
              <div class="meeting-item-order"><?= e((int) ($item['sort_order'] ?? 0) / 10) ?></div>
              <div class="meeting-item-body">
                <div class="meeting-card-topline">
                  <?php if ($section === 'action') : ?><span class="meeting-status status-<?= e($statusClass($itemStatus)) ?>" data-action-label><?= e($actionStatuses[$itemStatus] ?? $itemStatus) ?></span><?php endif; ?>
                  <?php if ((int) ($item['carried_from_item_id'] ?? 0) > 0) : ?><span class="carried-chip">Seguimiento anterior</span><?php endif; ?>
                </div>
                <h3><?= e((string) $item['title']) ?></h3>
                <?php if (!empty($item['content'])) : ?><p><?= nl2br(e((string) $item['content'])) ?></p><?php endif; ?>
                <?php if ($section === 'improvement' && !empty($item['observation'])) : ?><small class="item-observation">Observación: <?= e((string) $item['observation']) ?></small><?php endif; ?>
                <?php if ($section === 'action') : ?>
                  <dl><div><dt>Responsable</dt><dd><?= e((string) ($item['responsible_name'] ?: 'Sin asignar')) ?></dd></div><div><dt>Fecha límite</dt><dd><?= e($formatDate($item['due_date'] ?? null)) ?></dd></div><?php if (!empty($item['observation'])) : ?><div><dt>Observación</dt><dd><?= e((string) $item['observation']) ?></dd></div><?php endif; ?></dl>
                  <p class="failure-copy" data-failure-copy <?= empty($item['failure_reason']) ? 'hidden' : '' ?>>Motivo: <span><?= e((string) ($item['failure_reason'] ?? '')) ?></span></p>
                  <?php if ($editable) : ?>
                    <div class="inline-action-status"><button type="button" data-action-status="pending">Pendiente</button><button type="button" data-action-status="in_progress">En proceso</button><button type="button" data-action-status="completed">Hecho</button><button type="button" data-action-reason-open>No realizado</button></div>
                    <div class="failure-editor" data-action-reason hidden><label>Motivo obligatorio<textarea rows="3" maxlength="12000" data-action-reason-text></textarea></label><div><button type="button" class="button ghost" data-action-reason-cancel>Cancelar</button><button type="button" class="button status-failed" data-action-status="not_completed">Guardar</button></div></div>
                  <?php endif; ?>
                <?php endif; ?>
              </div>
              <?php if ($editable) : ?>
                <div class="meeting-item-tools">
                  <form method="post"><input type="hidden" name="action" value="meeting_item_move"><input type="hidden" name="_token" value="<?= e($csrf) ?>"><input type="hidden" name="meeting_id" value="<?= e($selectedId) ?>"><input type="hidden" name="item_id" value="<?= e((int) $item['id']) ?>"><input type="hidden" name="direction" value="up"><button type="submit" aria-label="Subir punto">↑</button></form>
                  <form method="post"><input type="hidden" name="action" value="meeting_item_move"><input type="hidden" name="_token" value="<?= e($csrf) ?>"><input type="hidden" name="meeting_id" value="<?= e($selectedId) ?>"><input type="hidden" name="item_id" value="<?= e((int) $item['id']) ?>"><input type="hidden" name="direction" value="down"><button type="submit" aria-label="Bajar punto">↓</button></form>
                  <button type="button" data-item-open data-section="<?= e($section) ?>" data-item='<?= e(json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}') ?>'>Editar</button>
                  <form method="post" data-confirm="¿Archivar este punto? Su historial se conservará."><input type="hidden" name="action" value="meeting_item_archive"><input type="hidden" name="_token" value="<?= e($csrf) ?>"><input type="hidden" name="meeting_id" value="<?= e($selectedId) ?>"><input type="hidden" name="item_id" value="<?= e((int) $item['id']) ?>"><button type="submit" class="danger-text">Archivar</button></form>
                </div>
              <?php endif; ?>
            </article>
          <?php endforeach; ?>
          <?php if (empty($items[$section])) : ?><div class="meeting-empty compact"><strong>Aún no hay <?= e(mb_strtolower($meta['title'])) ?></strong><p><?= e($editable ? 'Usa el botón Agregar para preparar este bloque.' : 'No se registró contenido en este bloque.') ?></p></div><?php endif; ?>
        </div>
      </section>
    <?php endforeach; ?>

    <?php if ($selectedStatus === 'completed' || !empty($selected['conclusions'])) : ?><section class="meeting-conclusions"><p class="meeting-kicker">Cierre</p><h2>Conclusiones</h2><p><?= nl2br(e((string) ($selected['conclusions'] ?: 'Sin conclusiones registradas.'))) ?></p></section><?php endif; ?>

    <?php if (!empty($selected['history'])) : ?>
      <details class="meeting-history"><summary>Historial de estados (<?= e(count((array) $selected['history'])) ?>)</summary><div><?php foreach ((array) $selected['history'] as $entry) : ?><p><strong><?= e((string) ($entry['action_title'] ?? 'Acción')) ?></strong> cambió de <?= e($actionStatuses[(string) ($entry['from_status'] ?? '')] ?? (string) ($entry['from_status'] ?? '')) ?> a <?= e($actionStatuses[(string) ($entry['to_status'] ?? '')] ?? (string) ($entry['to_status'] ?? '')) ?> · <?= e((string) ($entry['changed_by_name'] ?? 'Funcionario')) ?> · <?= e($formatDate($entry['changed_at'] ?? null, true)) ?><?php if (!empty($entry['reason'])) : ?><br><span>Motivo: <?= e((string) $entry['reason']) ?></span><?php endif; ?></p><?php endforeach; ?></div></details>
    <?php endif; ?>

    <?php if (($canManage && $selectedStatus !== 'archived') || $canDelete) : ?>
      <div class="meeting-danger-zone">
        <div><strong>Zona administrativa</strong><small>Archivar conserva el historial. Eliminar borra definitivamente todo el contenido de esta reunión.</small></div>
        <div class="meeting-danger-actions">
          <?php if ($canManage && $selectedStatus !== 'archived') : ?><form method="post" data-confirm="¿Archivar esta reunión? No se eliminará y podrá consultarse desde el historial."><input type="hidden" name="action" value="meeting_archive"><input type="hidden" name="_token" value="<?= e($csrf) ?>"><input type="hidden" name="meeting_id" value="<?= e($selectedId) ?>"><button class="button ghost danger-text">Archivar reunión</button></form><?php endif; ?>
          <?php if ($canDelete) : ?><form method="post" data-confirm="¿Eliminar definitivamente esta reunión? Se borrarán sus logros, puntos de mejora, acciones e historial. Esta acción no se puede deshacer."><input type="hidden" name="action" value="meeting_delete"><input type="hidden" name="_token" value="<?= e($csrf) ?>"><input type="hidden" name="meeting_id" value="<?= e($selectedId) ?>"><button class="button meeting-delete-button">Eliminar definitivamente</button></form><?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

  <?php else : ?>
    <header class="meetings-hero">
      <div><p class="meeting-kicker">Seguimiento interno</p><h1>Reuniones</h1><p>Prepara los temas, conduce la conversación y deja compromisos verificables para el siguiente encuentro.</p></div>
      <?php if ($canManage) : ?><button type="button" class="button primary" data-dialog-open="meeting-create-dialog">Nueva reunión</button><?php endif; ?>
    </header>

    <div class="meeting-summary-grid">
      <article><span>Total activas</span><strong><?= e((int) ($summary['total'] ?? 0)) ?></strong><small>Sin incluir archivadas</small></article>
      <article><span>Próximas</span><strong><?= e((int) ($summary['upcoming'] ?? 0)) ?></strong><small>En borrador con fecha vigente</small></article>
      <article><span>En curso</span><strong><?= e((int) ($summary['in_progress'] ?? 0)) ?></strong><small>Reuniones abiertas</small></article>
      <article><span>Finalizadas</span><strong><?= e((int) ($summary['completed'] ?? 0)) ?></strong><small>Historial bloqueado</small></article>
      <article class="is-accent"><span>Cumplimiento</span><strong><?= e((int) ($summary['compliance'] ?? 0)) ?>%</strong><small>Acciones realizadas / evaluadas</small></article>
    </div>

    <form method="get" class="meeting-filters">
      <input type="hidden" name="page" value="reuniones">
      <label>Buscar <input name="q" value="<?= e((string) ($filters['q'] ?? '')) ?>" placeholder="Título, objetivo o conclusión"></label>
      <label>Vista <select name="status"><option value="">Activas y finalizadas</option><?php foreach ($filterStatuses as $value => $label) : ?><option value="<?= e($value) ?>" <?= ($filters['status'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
      <button class="button primary">Aplicar filtros</button>
      <?php if (!empty($filters['q']) || !empty($filters['status'])) : ?><a class="button ghost" href="<?= e(url_page('reuniones')) ?>">Limpiar</a><?php endif; ?>
    </form>

    <div class="meeting-list">
      <?php foreach ($meetings as $meeting) : ?>
        <?php $rowStatus = (string) ($meeting['status'] ?? 'draft'); ?>
        <article class="meeting-list-card">
          <div class="meeting-date-block"><strong><?= e(date('d', strtotime((string) $meeting['meeting_date']))) ?></strong><span><?= e($monthNames[date('m', strtotime((string) $meeting['meeting_date']))] ?? '') ?></span><small><?= e(date('Y', strtotime((string) $meeting['meeting_date']))) ?></small></div>
          <div class="meeting-list-copy"><div class="meeting-card-topline"><span class="meeting-status status-<?= e($statusClass($rowStatus)) ?>"><?= e($meetingStatuses[$rowStatus] ?? $rowStatus) ?></span><?php if (!empty($meeting['carried_from_meeting_id'])) : ?><span class="carried-chip">Con seguimiento anterior</span><?php endif; ?></div><h2><a href="<?= e(url_page('reuniones', ['id' => (int) $meeting['id']])) ?>"><?= e((string) $meeting['title']) ?></a></h2><p><?= e((string) ($meeting['objective'] ?: 'Sin objetivo registrado.')) ?></p><small><?= e(count((array) ($meeting['participants'] ?? []))) ?> participantes · Creada por <?= e((string) ($meeting['created_by_name'] ?? 'Funcionario')) ?></small></div>
          <div class="meeting-list-metrics"><div><span>Acciones</span><strong><?= e((int) ($meeting['action_total'] ?? 0)) ?></strong></div><div><span>Abiertas</span><strong><?= e((int) ($meeting['action_open'] ?? 0)) ?></strong></div><div><span>Cumplimiento</span><strong><?= e((int) ($meeting['compliance'] ?? 0)) ?>%</strong></div><a class="button ghost" href="<?= e(url_page('reuniones', ['id' => (int) $meeting['id']])) ?>">Ver reunión</a></div>
        </article>
      <?php endforeach; ?>
      <?php if (!$meetings) : ?><div class="meeting-empty"><strong>No encontramos reuniones</strong><p><?= e($canManage ? 'Crea la primera reunión o ajusta los filtros.' : 'No hay reuniones disponibles para consultar.') ?></p></div><?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if ($canManage) : ?>
    <dialog class="meeting-dialog" id="meeting-create-dialog" aria-labelledby="meeting-create-title">
      <form method="post" class="meeting-form meeting-grid-form">
        <input type="hidden" name="action" value="meeting_save"><input type="hidden" name="_token" value="<?= e($csrf) ?>">
        <div class="dialog-heading span-2"><div><p class="meeting-kicker">Nueva reunión</p><h2 id="meeting-create-title">Preparar encuentro</h2><p>Al guardar, se copiarán una sola vez las acciones abiertas de la reunión anterior.</p></div><button type="button" class="dialog-close" data-dialog-close aria-label="Cerrar">×</button></div>
        <label>Título <input name="title" maxlength="220" required placeholder="Ej. Seguimiento de promoción · agosto"></label>
        <label>Fecha <input type="date" name="meeting_date" required value="<?= e(date('Y-m-d')) ?>"></label>
        <label class="span-2">Objetivo <textarea name="objective" rows="4" maxlength="8000" placeholder="Qué debe resolverse o definirse en esta reunión"></textarea></label>
        <?php $renderParticipantPicker('meeting-create-participants', $employees, [], 'Se conservará el nombre histórico aunque el funcionario cambie después.'); ?>
        <div class="span-2 form-actions"><button type="button" class="button ghost" data-dialog-close>Cancelar</button><button class="button primary">Crear reunión</button></div>
      </form>
    </dialog>

    <?php if ($selected && $editable) : ?>
      <dialog class="meeting-dialog" id="meeting-item-dialog" aria-labelledby="meeting-item-title">
        <form method="post" class="meeting-form meeting-grid-form" data-item-form>
          <input type="hidden" name="action" value="meeting_item_save"><input type="hidden" name="_token" value="<?= e($csrf) ?>"><input type="hidden" name="meeting_id" value="<?= e($selectedId) ?>"><input type="hidden" name="id" value=""><input type="hidden" name="section" value="achievement">
          <div class="dialog-heading span-2"><div><p class="meeting-kicker" data-item-kicker>Nuevo punto</p><h2 id="meeting-item-title" data-item-title>Agregar contenido</h2><p data-item-help>Completa la información que servirá como guía.</p></div><button type="button" class="dialog-close" data-dialog-close aria-label="Cerrar">×</button></div>
          <label class="span-2"><span data-field-title-label>Título</span> <input name="title" maxlength="300" required></label>
          <label class="span-2" data-field-content><span data-field-content-label>Resultado o diagnóstico</span> <textarea name="content" rows="4" maxlength="12000"></textarea></label>
          <label class="span-2" data-field-observation>Observación <textarea name="observation" rows="3" maxlength="12000"></textarea></label>
          <label data-action-field>Mejora relacionada <select name="related_improvement_id"><option value="">Sin relación</option><?php foreach ((array) ($items['improvement'] ?? []) as $improvement) : ?><option value="<?= e((int) $improvement['id']) ?>"><?= e((string) $improvement['title']) ?></option><?php endforeach; ?></select></label>
          <label data-action-field>Responsable <select name="responsible_employee_id"><option value="">Sin asignar</option><?php foreach ($employees as $employee) : ?><option value="<?= e((int) $employee['id']) ?>"><?= e((string) $employee['nombre']) ?></option><?php endforeach; ?></select></label>
          <label data-action-field>Fecha límite <input type="date" name="due_date"></label>
          <div class="span-2 form-actions"><button type="button" class="button ghost" data-dialog-close>Cancelar</button><button class="button primary">Guardar punto</button></div>
        </form>
      </dialog>
    <?php endif; ?>
  <?php endif; ?>
</section>
