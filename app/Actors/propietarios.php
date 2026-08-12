<?php

declare(strict_types=1);

namespace App;

return static function (array $options, array $preferencias): array {
    extract($options, EXTR_SKIP);
    return [
            'propietarios' => [
                'title' => 'Propietarios',
                'table' => Database::table('jet_cct_propietarios'),
                'name' => 'nombre',
                'email' => 'correo',
                'phone' => 'celular',
                'rol_persona' => 'Propietario',
                'es_actor' => true,
                'permitir_llamada' => true,
                'sync_on_save' => 'propietario',
                'allow_documentos' => true,
                'opciones_filtros' => ['v_estado' => ['Activo', 'Pendiente', 'Inactivo']],
                'campos' => array_merge([
                    ['id' => 'nombre', 'label' => 'Nombre Completo', 'type' => 'text', 'cols' => 12],
                    ['id' => 'tipo_persona', 'label' => 'Tipo Persona', 'type' => 'select', 'opts' => $opts_tipo_per, 'cols' => 4],
                    ['id' => 'tipo_documento', 'label' => 'Tipo Documento', 'type' => 'select', 'opts' => $opts_tipo_doc, 'cols' => 4],
                    ['id' => 'documento', 'label' => 'No. Documento', 'type' => 'text', 'cols' => 4],
                    ['id' => 'expedicion', 'label' => 'Lugar Expedición', 'type' => 'text', 'cols' => 6],
                    ['id' => 'cumpleanos', 'label' => 'Fecha Cumpleaños', 'type' => 'date', 'cols' => 6],
                    ['id' => 'sexo', 'label' => 'Sexo', 'type' => 'select', 'opts' => $opts_sexo, 'cols' => 6],
                    ['id' => 'nombre_juridico', 'label' => 'Nombre Jurídico (Empresa)', 'type' => 'text', 'cols' => 6],
                    ['id' => 'documento_juridico', 'label' => 'NIT', 'type' => 'text', 'cols' => 6],
                    ['id' => 'indicativo', 'label' => 'Indicativo', 'type' => 'select_indicativo', 'opts' => $opts_indicativo, 'cols' => 2],
                    ['id' => 'celular', 'label' => 'Celular', 'type' => 'text', 'cols' => 4],
                    ['id' => 'correo', 'label' => 'Correo Electrónico', 'type' => 'email', 'cols' => 6],
                    ['id' => 'pais', 'label' => 'País', 'type' => 'text', 'cols' => 4],
                    ['id' => 'departamento', 'label' => 'Departamento', 'type' => 'text', 'cols' => 4],
                    ['id' => 'ciudad', 'label' => 'Ciudad', 'type' => 'text', 'cols' => 4],
                    ['id' => 'direccion', 'label' => 'Dirección Residencia', 'type' => 'text', 'cols' => 12],
                    ['id' => 'sucursal', 'label' => 'Sucursal', 'type' => 'text', 'cols' => 4],
                    ['id' => 'tipo_giro', 'label' => 'Tipo de giro', 'type' => 'text', 'cols' => 4],
                    ['id' => 'nombre_giro', 'label' => 'Nombre del giro', 'type' => 'text', 'cols' => 4],
                    ['id' => 'banco', 'label' => 'Banco', 'type' => 'text', 'cols' => 4],
                    ['id' => 'tipo_cuenta', 'label' => 'Tipo Cuenta', 'type' => 'select', 'opts' => $opts_cuenta, 'cols' => 4],
                    ['id' => 'numero_cuenta', 'label' => 'No. Cuenta', 'type' => 'text', 'cols' => 4],
                    ['id' => 'titular', 'label' => 'Titular Cuenta', 'type' => 'text', 'cols' => 6],
                    ['id' => 'documento_cuenta', 'label' => 'Doc. Titular', 'type' => 'text', 'cols' => 6],
                    ['id' => 'correo_cuenta', 'label' => 'Correo Pagos', 'type' => 'email', 'cols' => 12],
                ], $preferencias),
                'vista' => ['nombre', 'indicativo', 'celular', 'correo', 'v_estado', 'v_contratos', 'v_inmuebles'],
                'labels' => ['nombre' => 'Propietario', 'indicativo' => 'Ind.', 'celular' => 'Celular', 'correo' => 'Correo', 'v_estado' => 'Estado', 'v_contratos' => 'Contratos', 'v_inmuebles' => 'Inmuebles'],
                'fields' => [
                    'nombre' => 'Nombre completo',
                    'tipo_persona' => 'Tipo persona',
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
