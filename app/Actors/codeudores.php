<?php

declare(strict_types=1);

namespace App;

return static function (array $options, array $preferencias): array {
    extract($options, EXTR_SKIP);
    return [
            'codeudores' => [
                'title' => 'Codeudores',
                'table' => Database::table('jet_cct_codeudores'),
                'name' => 'nombre',
                'email' => 'correo',
                'phone' => 'celular',
                'rol_persona' => 'Codeudor',
                'es_actor' => true,
                'permitir_llamada' => true,
                'opciones_filtros' => [],
                'campos' => array_merge([
                    ['id' => 'nombre', 'label' => 'Nombre Completo', 'type' => 'text', 'cols' => 12],
                    ['id' => 'tipo_documento', 'label' => 'Tipo Documento', 'type' => 'select', 'opts' => $opts_tipo_doc, 'cols' => 6],
                    ['id' => 'documento', 'label' => 'No. Documento', 'type' => 'text', 'cols' => 6],
                    ['id' => 'expedicion', 'label' => 'Lugar de expedición', 'type' => 'text', 'cols' => 6],
                    ['id' => 'indicativo', 'label' => 'Indicativo', 'type' => 'select', 'opts' => $opts_indicativo, 'cols' => 2],
                    ['id' => 'celular', 'label' => 'Celular', 'type' => 'text', 'cols' => 4],
                    ['id' => 'correo', 'label' => 'Correo', 'type' => 'email', 'cols' => 6],
                    ['id' => 'pais', 'label' => 'País', 'type' => 'text', 'cols' => 4],
                    ['id' => 'departamento', 'label' => 'Departamento', 'type' => 'text', 'cols' => 4],
                    ['id' => 'ciudad', 'label' => 'Ciudad', 'type' => 'text', 'cols' => 6],
                    ['id' => 'direccion', 'label' => 'Dirección', 'type' => 'text', 'cols' => 12],
                    ['id' => 'sucursal', 'label' => 'Sucursal', 'type' => 'text', 'cols' => 4],
                    ['id' => 'cumpleanos', 'label' => 'Cumpleaños', 'type' => 'date', 'cols' => 4],
                    ['id' => 'sexo', 'label' => 'Sexo', 'type' => 'select', 'opts' => $opts_sexo, 'cols' => 4],
                ], $preferencias),
                'vista' => ['nombre', 'indicativo', 'celular', 'correo', 'v_contratos'],
                'labels' => ['nombre' => 'Codeudor', 'indicativo' => 'Ind.', 'celular' => 'Celular', 'correo' => 'Correo', 'v_contratos' => 'Contrato Asociado'],
                'fields' => [
                    'nombre' => 'Nombre completo',
                    'tipo_documento' => 'Tipo documento',
                    'documento' => 'Documento',
                    'indicativo' => 'Indicativo',
                    'celular' => 'Celular',
                    'correo' => 'Correo',
                    'ciudad' => 'Ciudad',
                    'direccion' => 'Direccion',
                ],
            ],
    ];
};
