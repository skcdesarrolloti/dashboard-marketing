<?php
$utmAnalyticsOptions = static function (array $rows): array {
    $values = [];
    foreach ($rows as $row) {
        $value = trim((string) ($row['value'] ?? ''));
        if ($value !== '') {
            $values[] = $value;
        }
    }
    return array_values(array_unique($values));
};
$utmOptions = [
    'source' => $utmAnalyticsOptions($analyticsOptions['sources'] ?? []),
    'medium' => $utmAnalyticsOptions($analyticsOptions['mediums'] ?? []),
    'campaign' => $utmAnalyticsOptions($analyticsOptions['campaigns'] ?? []),
];
?>
<script>
window.GDAUtmOptions = <?= json_encode($utmOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
</script>

<header class="page-head hero-head">
  <div>
    <span class="eyebrow">Herramienta de medición</span>
    <h1>Generador UTM</h1>
    <p>Crea enlaces listos para campañas con fuente, medio y nombre de campaña.</p>
  </div>
  <a class="button ghost" href="<?= e(url(['page' => 'analiticas'])) ?>">Ver analíticas</a>
</header>

<section class="utm-workspace">
  <div class="panel tool-panel utm-builder" data-utm-builder>
    <div class="utm-section-head">
      <div>
        <h2>Datos del enlace</h2>
        <p>Los nuevos medios y fuentes quedan guardados en este navegador.</p>
      </div>
    </div>

    <label for="utm-url">URL destino</label>
    <input id="utm-url" placeholder="https://sucasainmobiliaria.com.co/...">

    <div class="utm-grid two">
      <div class="utm-combo" data-utm-combo="medium">
        <label for="utm-medium">Medio / canal</label>
        <div class="utm-inline-control">
          <select id="utm-medium"></select>
          <button class="button slim" type="button" data-add-utm-option="medium">Agregar</button>
        </div>
        <div class="utm-new-option" data-utm-new-option="medium" hidden>
          <input id="utm-medium-new" placeholder="Nuevo medio / canal">
          <button class="button slim" type="button" data-save-utm-option="medium">Guardar</button>
        </div>
      </div>

      <div class="utm-combo" data-utm-combo="source">
        <label for="utm-source">Fuente / recurso</label>
        <div class="utm-inline-control">
          <select id="utm-source"></select>
          <button class="button slim" type="button" data-add-utm-option="source">Agregar</button>
        </div>
        <div class="utm-new-option" data-utm-new-option="source" hidden>
          <input id="utm-source-new" placeholder="Nueva fuente / recurso">
          <button class="button slim" type="button" data-save-utm-option="source">Guardar</button>
        </div>
      </div>
    </div>

    <label for="utm-campaign">Campaña</label>
    <input id="utm-campaign" list="utm-campaign-options" placeholder="feria_vivienda">
    <datalist id="utm-campaign-options">
      <?php foreach ($utmOptions['campaign'] as $campaignOption) : ?>
        <option value="<?= e($campaignOption) ?>"></option>
      <?php endforeach; ?>
    </datalist>

    <div class="utm-actions">
      <button class="primary" type="button" data-generate-utm>Generar y copiar</button>
      <button class="button ghost" type="button" data-reset-utm>Limpiar</button>
    </div>

    <label for="utm-result">Enlace generado</label>
    <textarea id="utm-result" readonly placeholder="El enlace aparecerá aquí."></textarea>
    <p class="form-message" id="utm-message" aria-live="polite"></p>
  </div>

</section>
