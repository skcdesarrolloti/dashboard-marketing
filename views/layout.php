<?php

use App\Auth;

$user = Auth::user();
$title = app_config('app.name', 'Dashboard Marketing');
$navItems = [
  ['page' => '',             'label' => 'Dashboard',        'icon' => 'overview'],
  ['page' => 'actores',     'label' => 'Actores',          'icon' => 'spark'],
  ['page' => 'plantillas',  'label' => 'Plantillas',       'icon' => 'requests'],
  ['page' => 'campanas',    'label' => 'Campañas',         'icon' => 'history'],
  ['page' => 'envios',      'label' => 'Envíos y colas',   'icon' => 'queue'],
  ['page' => 'inventario',  'label' => 'Inventario',       'icon' => 'inventory'],
  ['page' => 'actividad',   'label' => 'Actividad',        'icon' => 'chart'],
  ['page' => 'analiticas',  'label' => 'Analíticas',       'icon' => 'stats'],
  ['page' => 'redes',       'label' => 'Redes sociales',   'icon' => 'stats'],
  ['page' => 'branding',    'label' => 'Banco de piezas',  'icon' => 'branding'],
  ['page' => 'planificador','label' => 'Planificador',     'icon' => 'history'],
  ['page' => 'reuniones',   'label' => 'Reuniones',        'icon' => 'meetings'],
  ['page' => 'herramienta', 'label' => 'Generador UTM',    'icon' => 'link'],
  ['page' => 'configuracion','label' => 'Configuración',    'icon' => 'queue'],
];
$navItems = array_values(array_filter($navItems, static function(array $item): bool {
  $module = [''=>'dashboard','actores'=>'contactos','configuracion'=>'confi_sistema'][$item['page']] ?? $item['page'];
  return \App\PermissionService::canModule($module);
}));
$currentPage = (string) ($_GET['page'] ?? '');
if ($currentPage === '' && isset($_SERVER['REQUEST_URI'])) {
  $path = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '');
  if ($path !== '' && $path !== 'index.php' && $path !== 'index.html') {
    $currentPage = $path;
  }
}
if ($currentPage === 'plantilla-editor') $currentPage = 'plantillas';
$currentLabel = 'Dashboard';
foreach ($navItems as $navItem) {
  if ($navItem['page'] === $currentPage) {
    $currentLabel = $navItem['label'];
    break;
  }
}
$userName = (string) ($user['nombre'] ?? 'Funcionario');
$userRole = (string) ($user['rol'] ?? $user['cargo'] ?? 'Equipo de promoción');
$userInitials = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $userName), 0, 2));
if ($userInitials === '') {
  $userInitials = 'SK';
}

$scriptFile = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
$base = clean_url_base();
$assetBase = str_contains($scriptFile, '/public/')
  ? $base
  : ($base === '' ? '/public' : $base . '/public');
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?></title>
  <link rel="shortcut icon" href="<?= e(system_image('portal_favicon_url')) ?>" type="image/x-icon">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e($assetBase) ?>/assets/app.css?v=<?= e((string) @filemtime(dirname(__DIR__) . '/public/assets/app.css')) ?>">
  <link rel="stylesheet" href="<?= e($assetBase) ?>/assets/layout.css?v=<?= e((string) @filemtime(dirname(__DIR__) . '/public/assets/layout.css')) ?>">
  <link rel="stylesheet" href="<?= e($assetBase) ?>/assets/nav-icons.css?v=<?= e((string) @filemtime(dirname(__DIR__) . '/public/assets/nav-icons.css')) ?>">
  <?php if ($currentPage === 'actores') : ?><link rel="stylesheet" href="<?= e($assetBase) ?>/assets/actors.css?v=<?= e((string) @filemtime(dirname(__DIR__) . '/public/assets/actors.css')) ?>"><?php endif; ?>
  <?php if ($currentPage === 'campanas') : ?><link rel="stylesheet" href="<?= e($assetBase) ?>/assets/campaigns.css?v=<?= e((string) @filemtime(dirname(__DIR__) . '/public/assets/campaigns.css')) ?>"><?php endif; ?>
  <?php if ($currentPage === 'actividad') : ?><link rel="stylesheet" href="<?= e($assetBase) ?>/assets/activity.css?v=<?= e((string) @filemtime(dirname(__DIR__) . '/public/assets/activity.css')) ?>"><?php endif; ?>
  <?php if ($currentPage === 'analiticas') : ?><link rel="stylesheet" href="<?= e($assetBase) ?>/assets/analytics.css?v=<?= e((string) @filemtime(dirname(__DIR__) . '/public/assets/analytics.css')) ?>"><?php endif; ?>
  <?php if ($currentPage === 'redes') : ?><link rel="stylesheet" href="<?= e($assetBase) ?>/assets/social.css?v=<?= e((string) @filemtime(dirname(__DIR__) . '/public/assets/social.css')) ?>"><?php endif; ?>
  <?php if ($currentPage === 'branding') : ?><link rel="stylesheet" href="<?= e($assetBase) ?>/assets/branding.css?v=<?= e((string) @filemtime(dirname(__DIR__) . '/public/assets/branding.css')) ?>"><?php endif; ?>
  <?php if ($currentPage === 'planificador') : ?><link rel="stylesheet" href="<?= e($assetBase) ?>/assets/planner.css?v=<?= e((string) @filemtime(dirname(__DIR__) . '/public/assets/planner.css')) ?>"><?php endif; ?>
  <?php if ($currentPage === 'reuniones') : ?><link rel="stylesheet" href="<?= e($assetBase) ?>/assets/meetings.css?v=<?= e((string) @filemtime(dirname(__DIR__) . '/public/assets/meetings.css')) ?>"><?php endif; ?>
  <?php if ($currentPage === 'herramienta') : ?><link rel="stylesheet" href="<?= e($assetBase) ?>/assets/utm.css?v=<?= e((string) @filemtime(dirname(__DIR__) . '/public/assets/utm.css')) ?>"><?php endif; ?>
  <?php if ($currentPage === 'inventario') : ?><link rel="stylesheet" href="<?= e($assetBase) ?>/assets/inventory.css?v=<?= e((string) @filemtime(dirname(__DIR__) . '/public/assets/inventory.css')) ?>"><?php endif; ?>
  <?php if (in_array($currentPage, ['envios', 'configuracion'], true)) : ?><link rel="stylesheet" href="<?= e($assetBase) ?>/assets/operations.css?v=<?= e((string) @filemtime(dirname(__DIR__) . '/public/assets/operations.css')) ?>"><?php endif; ?>
  <link rel="stylesheet" href="<?= e($assetBase) ?>/assets/editor.css?v=<?= e((string) @filemtime(dirname(__DIR__) . '/public/assets/editor.css')) ?>">
</head>
<body class="<?= Auth::check() ? ($currentPage === 'reuniones' && ($_GET['mode'] ?? '') === 'guide' ? 'meeting-guide-page' : '') : 'login-page' ?>">

<?php if (Auth::check()) : ?>
  <div class="app-shell">
    <aside class="sidebar app-sidebar">
      <a class="brand" href="<?= e(url_page()) ?>">
        <span class="brand-logo"><img src="<?= e(system_image('portal_logo_url')) ?>" alt="Su Casa Inmobiliaria"></span>
        <span class="brand-copy">
          <strong>Dashboard Marketing</strong>
          <small>Operación de portales</small>
        </span>
      </a>

      <div class="sidebar-section">
        <span class="sidebar-label">Menú</span>
        <nav aria-label="Navegación principal">
          <?php foreach ($navItems as $navItem) : ?>
            <a class="nav-link <?= $currentPage === $navItem['page'] ? 'active' : '' ?>" href="<?= e(url_page($navItem['page'])) ?>">
              <span class="nav-icon" data-icon="<?= e($navItem['icon']) ?>" aria-hidden="true"></span>
              <span><?= e($navItem['label']) ?></span>
            </a>
          <?php endforeach; ?>
        </nav>
      </div>

    </aside>

    <div class="app-main">
      <header class="topbar">
        <div class="topbar-intro">
          <p class="topbar-kicker">Panel de promoción</p>
          <h2><?= e($currentLabel) ?></h2>
        </div>

        <form class="global-search" method="get">
          <input type="hidden" name="page" value="actores">
          <input name="q" placeholder="Buscar en actores y campañas..." aria-label="Buscar">
          <button class="primary" type="submit">Buscar</button>
        </form>

        <div class="topbar-meta">
          <div class="active-view-chip">
            <small>Vista activa</small>
            <strong><?= e($currentLabel) ?></strong>
          </div>
          <div class="user-chip">
            <span class="user-avatar" aria-hidden="true"><?= e($userInitials) ?></span>
            <span class="user-copy">
              <strong><?= e($userName) ?></strong>
              <small><?= e($userRole) ?></small>
            </span>
          </div>
          <a class="button ghost logout-chip" href="<?= e(url(['action' => 'logout'])) ?>">Salir</a>
        </div>
      </header>

      <main class="shell">
        <?php require $view; ?>
      </main>
    </div>
  </div>
<?php else : ?>
  <main class="shell">
    <?php require $view; ?>
  </main>
<?php endif; ?>

<div class="app-loader" data-app-loader aria-hidden="true">
  <div class="loader-card">
    <span class="loader-ring"></span>
    <strong>Cargando módulo</strong>
    <small>Preparando datos y filtros...</small>
  </div>
</div>
<?php if (($template ?? '') === 'template-editor') : ?>
<script src="<?= e($assetBase) ?>/assets/vendor/ace/ace.min.js"></script>
<script>ace.config.set('basePath', <?= json_encode($assetBase . '/assets/vendor/ace') ?>);</script>
<script src="<?= e($assetBase) ?>/assets/vendor/ace/emmet.js"></script>
<script src="<?= e($assetBase) ?>/assets/vendor/ace/ext-language_tools.js"></script>
<script src="<?= e($assetBase) ?>/assets/vendor/ace/ext-emmet.js"></script>
<script src="<?= e($assetBase) ?>/assets/vendor/ace/ext-beautify.js"></script>
<?php endif; ?>
<script src="<?= e($assetBase) ?>/assets/app.js?v=<?= e((string) @filemtime(dirname(__DIR__) . '/public/assets/app.js')) ?>"></script>
<?php if ($currentPage === 'planificador') : ?>
<script src="<?= e($assetBase) ?>/assets/planner.js?v=<?= e((string) @filemtime(dirname(__DIR__) . '/public/assets/planner.js')) ?>"></script>
<?php endif; ?>
<?php if ($currentPage === 'reuniones') : ?>
<script src="<?= e($assetBase) ?>/assets/meetings.js?v=<?= e((string) @filemtime(dirname(__DIR__) . '/public/assets/meetings.js')) ?>"></script>
<?php endif; ?>
<?php if ($currentPage === 'redes') : ?>
<script src="<?= e($assetBase) ?>/assets/social.js?v=<?= e((string) @filemtime(dirname(__DIR__) . '/public/assets/social.js')) ?>"></script>
<?php endif; ?>
<?php if ($currentPage === 'branding') : ?>
<script src="<?= e($assetBase) ?>/assets/branding.js?v=<?= e((string) @filemtime(dirname(__DIR__) . '/public/assets/branding.js')) ?>"></script>
<?php endif; ?>
<?php if ($currentPage === 'actores' || ($template ?? '') === 'actors') : ?>
<script src="<?= e($assetBase) ?>/assets/actors.js?v=<?= e((string) @filemtime(dirname(__DIR__) . '/public/assets/actors.js')) ?>"></script>
<?php endif; ?>
<?php if ($currentPage === 'inventario') : ?>
<script src="<?= e($assetBase) ?>/assets/inventory.js?v=<?= e((string) @filemtime(dirname(__DIR__) . '/public/assets/inventory.js')) ?>"></script>
<?php endif; ?>
</body>
</html>
