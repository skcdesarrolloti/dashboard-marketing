<?php
$canManage = in_array(\App\PermissionService::currentRole(), [\App\PermissionService::ROLE_ADMIN, \App\PermissionService::ROLE_PROMOCION], true);
$showImport = $type === 'contactos' && !empty($config['permitir_import']) && $canManage;
$showBulkToolbar = !empty($config['es_actor']);
$vista = $config['vista'] ?? [];
$labels = $config['labels'] ?? [];
$categorias = \App\ActorPreferences::CATEGORIA_LABELS;
?>
<header class="page-head">
  <div><h1>Gestor de actores</h1><p>Audiencias, preferencias y campañas en un solo lugar.</p></div>
  <div class="page-head-actions">
    <?php if ($showImport) : ?><button type="button" class="button ghost" data-modal-open="import-modal">Importar CSV</button><?php endif; ?>
    <button type="button" class="button ghost" data-open-my-deliveries>Mis envíos</button>
  </div>
</header>

<?php if (!empty($error)) : ?><div class="alert" role="alert"><?= e($error) ?></div><?php endif; ?>
<?php if (isset($_GET['queued'])) : $nothingQueued = (int) $_GET['queued'] === 0; ?>
  <div class="<?= $nothingQueued ? 'alert' : 'notice' ?>" aria-live="polite">
    <?= $nothingQueued ? 'No se agregó ningún destinatario. ' : '' ?><?= e((int) $_GET['queued']) ?> en cola · <?= e((int) ($_GET['filtered'] ?? 0)) ?> bloqueados por preferencias · <?= e((int) ($_GET['duplicates'] ?? 0)) ?> duplicados · <?= e((int) ($_GET['excluded'] ?? 0)) ?> excluidos · <?= e((int) ($_GET['failed'] ?? 0)) ?> sin contacto válido/error de cola · <?= e((int) ($_GET['not_found'] ?? 0)) ?> no encontrados.
  </div>
<?php endif; ?>
<?php if (isset($_GET['saved'])) : ?><div class="notice">Actor guardado correctamente.</div><?php endif; ?>
<?php if (isset($_GET['deleted'])) : ?><div class="notice">Actor eliminado.</div><?php endif; ?>

<nav class="module-tabs" aria-label="Tipos de actor">
  <?php foreach ($catalog as $key => $actorConfig) : ?>
    <?php if (!\App\PermissionService::canView($key)) continue; ?>
    <a class="<?= $key === $type ? 'active' : '' ?>" href="<?= e(url(['page' => 'actores', 'type' => $key])) ?>"><?= e($actorConfig['title']) ?></a>
  <?php endforeach; ?>
</nav>

<section class="grid actors-grid">
  <article class="panel full-width">
    <div class="panel-title-row">
      <div><p class="eyebrow">Audiencia</p><h2><?= e($config['title']) ?></h2><p><?= e((int) $data['total']) ?> registros</p></div>
      <?php if ($showBulkToolbar) : ?>
        <div class="button-row actor-campaign-actions" aria-label="Acciones de campaña">
          <button type="button" class="button" data-select-visible>Seleccionar visibles</button>
          <button type="button" class="button ghost" data-clear-selection>Limpiar</button>
          <button type="button" class="primary" data-modal-open="mass-send-modal" data-campaign-channel="email" data-requires-selection disabled>Campaña Email</button>
          <button type="button" class="button campaign-channel" data-modal-open="mass-send-modal" data-campaign-channel="sms" data-requires-selection disabled>Campaña SMS</button>
          <button type="button" class="button campaign-channel" data-modal-open="mass-send-modal" data-campaign-channel="whatsapp" data-requires-selection disabled>Campaña WhatsApp</button>
        </div>
      <?php endif; ?>
    </div>

    <?php require __DIR__ . '/actors/_filters.php'; ?>
    <?php require __DIR__ . '/actors/_table.php'; ?>
  </article>
</section>

<?php require __DIR__ . '/actors/_modals.php'; ?>
