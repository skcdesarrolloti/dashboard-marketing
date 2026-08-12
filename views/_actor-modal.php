<?php
/** @var string $type */
/** @var array $config */
/** @var array $actor */

$nameColumn = $config['name'] ?? 'nombre';
$nameValue = (string) ($actor[$nameColumn] ?? '');
$actorId = (int) ($actor['_ID'] ?? 0);
$campos = $config['campos'] ?? [];
$showEditReason = !empty($config['require_edit_reason']);
$countries = \App\CountryCatalog::all();
$selectedCountryId = \App\CountryCatalog::selectedId(
    (string) ($actor['pais'] ?? ''),
    (string) ($actor['indicativo'] ?? '')
);
$countryControlRendered = false;
$contextSummary = [];
$fieldOptions = static function (array $field): array {
    $opts = $field['opts'] ?? [];
    if (!is_array($opts)) {
        return [];
    }

    $out = [];
    foreach ($opts as $key => $label) {
        $value = is_int($key) ? (string) $label : (string) $key;
        $text = (string) $label;
        if ($value === '' && $text === '') {
            continue;
        }
        $out[] = ['value' => $value, 'label' => $text !== '' ? $text : $value];
    }

    return $out;
};
$selectedValues = static function (string $value): array {
    $parts = preg_split('/[,\s;|]+/', trim($value)) ?: [];
    $selected = [];
    foreach ($parts as $part) {
        $part = trim((string) $part);
        if ($part === '') {
            continue;
        }
        $selected[$part] = true;
    }
    return $selected;
};

if ($type === 'club_pph') {
    $contextSummary = [
        'Membresía' => (string) ($actor['membresia'] ?? '—'),
        'Copropiedad' => (string) ($actor['nombre_copropiedad'] ?? '—'),
        'Funcionario' => (string) ($actor['nombre_funcionario'] ?? '—'),
        'Grupo familiar' => (string) ($actor['total_familia'] ?? '0'),
        'Fondo' => (string) ($actor['pertenece_fondo'] ?? 'No'),
        'Tarjeta' => (string) ($actor['tiene_tarjeta'] ?? 'No'),
    ];
} elseif (in_array($type, ['propietarios', 'arrendatarios'], true)) {
    $contextSummary = [
        'Estado' => (string) ($actor['v_estado'] ?? '—'),
        'Contratos' => (string) ($actor['v_contratos_total'] ?? $actor['v_contratos'] ?? '0'),
        'Inmuebles' => (string) ($actor['v_inmuebles'] ?? '0'),
        'Documento' => (string) ($actor['documento'] ?? '—'),
        'Ciudad' => (string) ($actor['ciudad'] ?? '—'),
    ];
} elseif ($type === 'codeudores') {
    $contextSummary = [
        'Contrato asociado' => (string) ($actor['v_contratos_detalle'] ?? $actor['v_contratos'] ?? '—'),
        'Documento' => (string) ($actor['documento'] ?? '—'),
        'Ciudad' => (string) ($actor['ciudad'] ?? '—'),
    ];
} elseif ($type === 'copropiedades') {
    $contextSummary = [
        'Administrador' => (string) ($actor['administrador'] ?? '—'),
        'Inmuebles' => (string) ($actor['v_inmuebles'] ?? '0'),
        'Barrio' => (string) ($actor['barrio'] ?? '—'),
        'Zona' => (string) ($actor['zona'] ?? '—'),
    ];
} elseif ($type === 'funcionarios') {
    $contextSummary = [
        'Rol' => (string) ($actor['rol'] ?? '—'),
        'Activo' => (string) ($actor['activo'] ?? 'No'),
        'Gestión' => (string) ($actor['gestion'] ?? '—'),
        'Sucursal' => (string) ($actor['sucursal_nombre'] ?? $actor['id_sucursal'] ?? '—'),
        'Área' => (string) ($actor['area_nombre'] ?? $actor['id_area'] ?? '—'),
        'Cargo' => (string) ($actor['cargo_nombre'] ?? $actor['id_cargo'] ?? '—'),
    ];
}
?>
<div class="modal-content">
  <form method="post" id="actor-form" action="">
    <input type="hidden" name="action" value="save_actor">
    <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="type" value="<?= e($type) ?>">
    <input type="hidden" name="_ID" value="<?= e($actorId) ?>">

    <div class="modal-grid modal-grid-premium">
      <div class="stack compact-stack">
        <h3 class="modal-title">Editar <?= e($config['title']) ?></h3>
        <p class="modal-subtitle"><?= e($nameValue !== '' ? $nameValue : 'Registro #' . $actorId) ?></p>

        <div class="modal-fields">
          <?php foreach ($campos as $field) :
              $ftype = $field['type'] ?? 'text';
              $fid = $field['id'] ?? '';
              if (!$fid) continue;
              $label = $field['label'] ?? ucfirst($fid);
              $value = (string) ($actor[$fid] ?? '');
              if ($ftype === 'date' && $value !== '') {
                  $dateTimestamp = ctype_digit($value) ? (int) $value : strtotime($value);
                  $value = $dateTimestamp > 0 ? date('Y-m-d', $dateTimestamp) : '';
              }
              $placeholder = (string) ($field['placeholder'] ?? '');
              $visibleIf = $field['visible_if'] ?? null;
              $hideStyle = '';
              if ($visibleIf && isset($actor[key($visibleIf)]) && (string) $actor[key($visibleIf)] !== reset($visibleIf)) {
                  $hideStyle = ' style="display:none"';
              }
              $isPreferenceField = str_starts_with($fid, 'bloqueo_') || str_starts_with($fid, 'permite_') || $fid === 'motivo_bloqueo';
              $fieldClasses = $isPreferenceField ? ' class="actor-pref-row"' : '';
              if (in_array($ftype, ['header', 'subheader'], true)) : ?>
                <h4 class="modal-section-title <?= $ftype === 'subheader' ? 'actor-pref-subtitle' : 'actor-pref-title' ?>"><?= e($label) ?></h4>
                <?php continue; endif; ?>
              <?php if (in_array($fid, ['pais', 'indicativo'], true)) :
                  if ($countryControlRendered) continue;
                  $countryControlRendered = true; ?>
                <div class="actor-country-field">
                  <label for="actor-country-id">País e indicativo</label>
                  <select id="actor-country-id" name="country_id" data-country-select>
                    <option value=""><?= $selectedCountryId === 0 && (($actor['pais'] ?? '') !== '' || ($actor['indicativo'] ?? '') !== '') ? e('Valor actual: ' . trim((string) ($actor['pais'] ?? '') . ' ' . (string) ($actor['indicativo'] ?? ''))) : 'Selecciona un país' ?></option>
                    <?php foreach ($countries as $country) : ?>
                      <option value="<?= e($country['id']) ?>" <?= $selectedCountryId === (int) $country['id'] ? 'selected' : '' ?>><?= e($country['name'] . ' (' . $country['calling_code'] . ')') ?></option>
                    <?php endforeach; ?>
                  </select>
                  <small class="actor-field-help">El país define el indicativo; el número se guardará sin prefijos duplicados.</small>
                </div>
                <?php continue; endif; ?>
            <div<?= $fieldClasses ?><?= $hideStyle ?>>
              <label><?= e($label) ?><?php if ($ftype === 'checkbox') : ?> <input type="checkbox" name="<?= e($fid) ?>" value="1" <?= ((int) ($actor[$fid] ?? 0) === 1) ? 'checked' : '' ?> style="width:auto;min-height:0;margin-left:8px;"><?php endif; ?></label>
              <?php if ($ftype === 'textarea') : ?>
                <textarea name="<?= e($fid) ?>" rows="<?= e((int) ($field['rows'] ?? 4)) ?>" placeholder="<?= e($placeholder) ?>"><?= e($value) ?></textarea>
              <?php elseif ($ftype === 'select') : ?>
                <?php $optionsForField = $fieldOptions($field); ?>
                <select name="<?= e($fid) ?>" <?= !empty($field['readonly']) ? 'disabled' : '' ?>>
                  <?php if (!empty($field['placeholder'])) : ?><option value=""><?= e($field['placeholder']) ?></option><?php endif; ?>
                  <?php foreach ($optionsForField as $opt) : ?>
                    <option value="<?= e($opt['value']) ?>" <?= $value === (string) $opt['value'] ? 'selected' : '' ?>><?= e($opt['label']) ?></option>
                  <?php endforeach; ?>
                </select>
              <?php elseif ($ftype === 'multiselect') : ?>
                <?php
                  $optionsForField = $fieldOptions($field);
                  $selectedForField = $selectedValues($value);
                  $optionValues = array_fill_keys(array_map(static fn (array $opt): string => (string) $opt['value'], $optionsForField), true);
                ?>
                <?php if ($optionsForField === []) : ?>
                  <div class="form-message">No hay opciones configuradas.</div>
                <?php else : ?>
                  <select class="actor-multiselect-select" name="<?= e($fid) ?>[]" multiple size="<?= e(min(8, max(4, count($optionsForField)))) ?>" <?= !empty($field['readonly']) ? 'disabled' : '' ?>>
                  <?php foreach ($optionsForField as $opt) : ?>
                    <option value="<?= e($opt['value']) ?>" <?= isset($selectedForField[(string) $opt['value']]) ? 'selected' : '' ?>><?= e($opt['label']) ?></option>
                  <?php endforeach; ?>
                  <?php foreach (array_keys($selectedForField) as $selectedRaw) : ?>
                    <?php if (!isset($optionValues[$selectedRaw])) : ?>
                      <option value="<?= e($selectedRaw) ?>" selected>Valor no catalogado: <?= e($selectedRaw) ?></option>
                    <?php endif; ?>
                  <?php endforeach; ?>
                  </select>
                <?php endif; ?>
              <?php elseif ($ftype === 'checkbox') : ?>
                <!-- label already contains input above -->
              <?php elseif ($ftype === 'date') : ?>
                <input type="date" name="<?= e($fid) ?>" value="<?= e($value) ?>">
              <?php elseif ($ftype === 'email') : ?>
                <input type="email" name="<?= e($fid) ?>" value="<?= e($value) ?>" placeholder="<?= e($placeholder) ?>">
              <?php elseif ($ftype === 'number') : ?>
                <input type="number" name="<?= e($fid) ?>" value="<?= e($value) ?>" <?= !empty($field['readonly']) ? 'readonly' : '' ?>>
              <?php elseif ($ftype === 'editor_ace') : ?>
                <textarea name="<?= e($fid) ?>" rows="10" placeholder="<?= e($placeholder) ?>"><?= e($value) ?></textarea>
              <?php else : ?>
                <input type="text" name="<?= e($fid) ?>" value="<?= e($value) ?>" placeholder="<?= e($placeholder) ?>" <?= !empty($field['readonly']) ? 'readonly' : '' ?>>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>

          <?php if ($showEditReason) : ?>
            <h4 class="modal-section-title">Motivo de edición (obligatorio)</h4>
            <select name="club_pph_edit_reason" required>
              <option value="">-- Selecciona motivo --</option>
              <?php foreach (['Actualización de datos', 'Corrección de información', 'Preferencias de comunicación', 'Gestión administrativa'] as $motivo) : ?>
                <option value="<?= e($motivo) ?>"><?= e($motivo) ?></option>
              <?php endforeach; ?>
            </select>
            <textarea name="club_pph_edit_detail" rows="2" placeholder="Detalle adicional"></textarea>
          <?php endif; ?>
        </div>
      </div>

      <aside class="modal-side-panel stack compact-stack">
        <div class="action-summary">
          <h3>Resumen</h3>
          <p class="form-message">Editando registro #<?= e($actorId) ?> de <?= e($config['title']) ?>.</p>
          <?php if ($contextSummary !== []) : ?>
          <div class="action-summary-list">
            <?php foreach ($contextSummary as $label => $summaryValue) : ?>
              <div>
                <span><?= e($label) ?></span>
                <strong><?= e($summaryValue !== '' ? $summaryValue : '—') ?></strong>
              </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
          <p class="form-message">Los IDs, relaciones técnicas, fechas del sistema y credenciales no se pueden modificar desde este editor.</p>
        </div>
        <div class="actor-preview" data-actor-preview hidden aria-live="polite">
          <h3>Revisar actualización</h3>
          <div class="actor-preview-message" data-preview-message></div>
          <div class="actor-diff-list" data-preview-changes></div>
          <div class="actor-impact-list" data-preview-impact></div>
          <div class="actor-preview-warnings" data-preview-warnings></div>
        </div>
        <input type="hidden" name="preview_token" value="">
        <input type="hidden" name="original_version" value="<?= e((string) ($actor['cct_modified'] ?? '')) ?>">
        <div class="campaign-actions actor-edit-actions" data-edit-actions>
          <button type="button" class="button ghost" data-modal-close>Cancelar</button>
          <button class="primary" type="button" data-preview-actor>Revisar cambios</button>
        </div>
        <div class="campaign-actions actor-confirm-actions" data-confirm-actions hidden>
          <button type="button" class="button ghost" data-back-to-edit>Volver a editar</button>
          <button class="primary" type="submit">Confirmar actualización</button>
        </div>
      </aside>
    </div>
  </form>
</div>
