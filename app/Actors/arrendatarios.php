<?php

declare(strict_types=1);

namespace App;

return static function (array $options, array $preferencias): array {
    extract($options, EXTR_SKIP);
    return [
            'arrendatarios' => [
                'title' => 'Arrendatarios',
                'table' => Database::table('jet_cct_arrendatarios'),
                'name' => 'nombre',
                'email' => 'correo',
                'phone' => 'celular',
                'rol_persona' => 'Arrendatario',
                'es_actor' => true,
                'permitir_llamada' => true,
                'sync_on_save' => 'arrendatario',
                'opciones_filtros' => ['v_estado' => ['Activo', 'Pendiente', 'Inactivo']],
                'campos' => array_merge([
                    ['id' => 'nombre', 'label' => 'Nombre Completo', 'type' => 'text', 'cols' => 12],
                    ['id' => 'tipo_persona', 'label' => 'Tipo Persona', 'type' => 'select', 'opts' => $opts_tipo_per, 'cols' => 4],
                    ['id' => 'tipo_documento', 'label' => 'Tipo Documento', 'type' => 'select', 'opts' => $opts_tipo_doc, 'cols' => 4],
                    ['id' => 'documento', 'label' => 'No. Documento', 'type' => 'text', 'cols' => 4],
                    ['id' => 'expedicion', 'label' => 'Lugar Expedición', 'type' => 'text', 'cols' => 6],
                    ['id' => 'nombre_juridico', 'label' => 'Nombre Jurídico', 'type' => 'text', 'cols' => 6],
                    ['id' => 'documento_juridico', 'label' => 'NIT', 'type' => 'text', 'cols' => 6],
                    ['id' => 'indicativo', 'label' => 'Indicativo', 'type' => 'select', 'opts' => $opts_indicativo, 'cols' => 2],
                    ['id' => 'celular', 'label' => 'Celular', 'type' => 'text', 'cols' => 4],
                    ['id' => 'correo', 'label' => 'Correo', 'type' => 'email', 'cols' => 6],
                    ['id' => 'pais', 'label' => 'País', 'type' => 'text', 'cols' => 4],
                    ['id' => 'departamento', 'label' => 'Departamento', 'type' => 'text', 'cols' => 4],
                    ['id' => 'ciudad', 'label' => 'Ciudad', 'type' => 'text', 'cols' => 4],
                    ['id' => 'direccion', 'label' => 'Dirección', 'type' => 'text', 'cols' => 12],
                    ['id' => 'sucursal', 'label' => 'Sucursal', 'type' => 'text', 'cols' => 4],
                    ['id' => 'cumpleanos', 'label' => 'Cumpleaños', 'type' => 'date', 'cols' => 4],
                    ['id' => 'sexo', 'label' => 'Sexo', 'type' => 'select', 'opts' => $opts_sexo, 'cols' => 4],
                ], $preferencias),
                'vista' => ['nombre', 'indicativo', 'celular', 'correo', 'v_estado', 'v_contratos', 'v_inmuebles'],
                'labels' => ['nombre' => 'Arrendatario', 'indicativo' => 'Ind.', 'celular' => 'Celular', 'correo' => 'Correo', 'v_estado' => 'Estado', 'v_contratos' => 'Contratos', 'v_inmuebles' => 'Inmuebles'],
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
