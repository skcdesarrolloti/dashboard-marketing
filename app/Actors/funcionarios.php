<?php

declare(strict_types=1);

namespace App;

return static function (array $options, array $preferencias): array {
    extract($options, EXTR_SKIP);

    $tableOptions = static function (string $tableName, string $labelColumn): array {
        $table = Database::table($tableName);
        if (!Database::tableExists($table) || !Database::columnExists($table, $labelColumn)) {
            return [];
        }

        $rows = Database::rows(
            "SELECT _ID, `{$labelColumn}` AS label
               FROM {$table}
              WHERE `{$labelColumn}` IS NOT NULL AND TRIM(`{$labelColumn}`) != ''
              ORDER BY `{$labelColumn}` ASC
              LIMIT 500"
        );

        $out = [];
        foreach ($rows as $row) {
            $id = (int) ($row['_ID'] ?? 0);
            $label = trim((string) ($row['label'] ?? ''));
            if ($id <= 0 || $label === '') {
                continue;
            }
            $out[(string) $id] = '#' . $id . ' ' . $label;
        }

        return $out;
    };

    $areaOptions = $tableOptions('jet_cct_areas_organizacion', 'nombre');
    $cargoOptions = $tableOptions('jet_cct_cargos', 'nombre_cargo');
    $sucursalOptions = $tableOptions('jet_cct_sucursales', 'nombre');

    return [
            'funcionarios' => [
                'title' => 'Funcionarios',
                'table' => Database::table('jet_cct_funcionarios'),
                'name' => 'nombre',
                'email' => 'correo',
                'phone' => 'celular',
                'rol_persona' => 'Funcionario',
                'es_actor' => true,
                'permitir_llamada' => true,
                'sync_on_save' => 'funcionario',
                'opciones_filtros' => ['cargo_nombre' => '__cargos__', 'activo' => $opts_si_no],
                'campos' => [
                    ['id' => 'nombre', 'label' => 'Nombre', 'type' => 'text', 'cols' => 6],
                    ['id' => 'id_empleado', 'label' => 'ID Empleado', 'type' => 'text', 'cols' => 6, 'readonly' => true],
                    ['id' => 'correo', 'label' => 'Correo', 'type' => 'email', 'cols' => 6],
                    ['id' => 'indicativo', 'label' => 'Indicativo', 'type' => 'select', 'opts' => $opts_indicativo, 'cols' => 3],
                    ['id' => 'pais', 'label' => 'País', 'type' => 'text', 'cols' => 3],
                    ['id' => 'celular', 'label' => 'Celular', 'type' => 'text', 'cols' => 6],
                    ['id' => 'rol', 'label' => 'Rol', 'type' => 'text', 'cols' => 6],
                    ['id' => 'gestion', 'label' => 'Gestión', 'type' => 'text', 'cols' => 6],
                    ['id' => 'activo', 'label' => 'Activo', 'type' => 'select', 'opts' => $opts_si_no, 'cols' => 6, 'default' => 'Si'],
                    ['id' => 'id_sucursal', 'label' => 'Sucursal', 'type' => 'select', 'opts' => $sucursalOptions, 'placeholder' => 'Sin sucursal', 'cols' => 6],
                    ['id' => 'id_area', 'label' => 'Áreas', 'type' => 'multiselect', 'opts' => $areaOptions, 'cols' => 6],
                    ['id' => 'id_cargo', 'label' => 'Cargos', 'type' => 'multiselect', 'opts' => $cargoOptions, 'cols' => 6],
                    ['id' => 'tipo', 'label' => 'Tipo', 'type' => 'text', 'cols' => 6],
                    ['id' => 'documento', 'label' => 'Documento', 'type' => 'text', 'cols' => 6],
                    ['id' => 'correo_dian', 'label' => 'Correo Dian', 'type' => 'email', 'cols' => 6],
                    ['id' => 'proposito', 'label' => 'Propósito', 'type' => 'textarea', 'rows' => 3, 'cols' => 12],
                    ['id' => 'crecimiento', 'label' => 'Crecimiento', 'type' => 'textarea', 'rows' => 3, 'cols' => 12],
                    ['id' => 'valores', 'label' => 'Valores', 'type' => 'textarea', 'rows' => 3, 'cols' => 12],
                    ['id' => 'actitud', 'label' => 'Actitud', 'type' => 'textarea', 'rows' => 3, 'cols' => 6],
                    ['id' => 'trabajo_equipo', 'label' => 'Trabajo en equipo', 'type' => 'textarea', 'rows' => 3, 'cols' => 6],
                    ['id' => 'rol_academia', 'label' => 'Rol academia', 'type' => 'text', 'cols' => 6],
                    ['id' => 'mercado_libre_destacados', 'label' => 'Mercado Libre destacados', 'type' => 'number', 'cols' => 4],
                    ['id' => 'proppit_promocionados', 'label' => 'Proppit promocionados', 'type' => 'number', 'cols' => 4],
                    ['id' => 'ciencuadras_ascendidos', 'label' => 'Ciencuadras ascendidos', 'type' => 'number', 'cols' => 4],
                    ['id' => 'ciencuadras_destacados', 'label' => 'Ciencuadras destacados', 'type' => 'number', 'cols' => 4],
                    ['id' => 'finca_raiz_silver', 'label' => 'Finca Raíz Silver', 'type' => 'number', 'cols' => 4],
                    ['id' => 'finca_raiz_gold', 'label' => 'Finca Raíz Gold', 'type' => 'number', 'cols' => 4],
                    ['id' => 'finca_raiz_black', 'label' => 'Finca Raíz Black', 'type' => 'number', 'cols' => 4],
                ],
                'vista' => ['nombre', 'cargo_nombre', 'activo', 'correo', 'celular'],
                'labels' => ['nombre' => 'Nombre', 'cargo_nombre' => 'Cargo', 'activo' => 'Activo', 'correo' => 'Correo', 'celular' => 'Celular'],
                'fields' => [
                    'nombre' => 'Nombre',
                    'cargo' => 'Cargo',
                    'indicativo' => 'Indicativo',
                    'celular' => 'Celular',
                    'correo' => 'Correo',
                    'estado' => 'Estado',
                    'sede' => 'Sede',
                ],
            ],
    ];
};
