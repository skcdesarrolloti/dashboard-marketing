<?php

declare(strict_types=1);

namespace App;

return static function (array $options, array $preferencias): array {
    extract($options, EXTR_SKIP);
    return [
            'proveedores' => [
                'title' => 'Proveedores',
                'table' => Database::table('jet_cct_proveedores'),
                'name' => 'proveedor',
                'email' => 'correo_proveedor',
                'phone' => 'celular_proveedor',
                'rol_persona' => 'Proveedor',
                'es_actor' => true,
                'permitir_llamada' => true,
                'opciones_filtros' => [],
                'campos' => array_merge([
                    ['id' => 'proveedor', 'label' => 'Nombre / Razón Social', 'type' => 'text', 'cols' => 12],
                    ['id' => 'tipo_identificacion_proveedor', 'label' => 'Tipo Doc.', 'type' => 'select', 'opts' => $opts_tipo_doc, 'cols' => 4],
                    ['id' => 'identificacion_proveedor', 'label' => 'No. Identificación', 'type' => 'text', 'cols' => 8],
                    ['id' => 'titular_proveedor', 'label' => 'Titular (Si aplica)', 'type' => 'text', 'cols' => 12],
                    ['id' => 'identificacion_cuenta_proveedor', 'label' => 'Identificación titular de cuenta', 'type' => 'text', 'cols' => 6],
                    ['id' => 'indicativo', 'label' => 'Indicativo', 'type' => 'select', 'opts' => $opts_indicativo, 'cols' => 2],
                    ['id' => 'pais', 'label' => 'País', 'type' => 'text', 'cols' => 4],
                    ['id' => 'celular_proveedor', 'label' => 'Celular', 'type' => 'text', 'cols' => 4],
                    ['id' => 'correo_proveedor', 'label' => 'Correo Contacto', 'type' => 'email', 'cols' => 6],
                    ['id' => 'direccion_proveedor', 'label' => 'Dirección', 'type' => 'text', 'cols' => 12],
                    ['id' => 'banco_proveedor', 'label' => 'Banco', 'type' => 'text', 'cols' => 4],
                    ['id' => 'tipo_cuenta_proveedor', 'label' => 'Tipo Cuenta', 'type' => 'select', 'opts' => $opts_cuenta, 'cols' => 4],
                    ['id' => 'cuenta_proveedor', 'label' => 'No. Cuenta', 'type' => 'text', 'cols' => 4],
                    ['id' => 'correo_pago_proveedor', 'label' => 'Correo Pagos', 'type' => 'email', 'cols' => 12],
                ], $preferencias),
                'vista' => ['proveedor', 'indicativo', 'celular_proveedor', 'correo_proveedor'],
                'labels' => ['proveedor' => 'Proveedor', 'indicativo' => 'Ind.', 'celular_proveedor' => 'Celular', 'correo_proveedor' => 'Correo'],
                'fields' => [
                    'proveedor' => 'Proveedor',
                    'tipo_identificacion_proveedor' => 'Tipo identificacion',
                    'identificacion_proveedor' => 'Identificacion',
                    'indicativo' => 'Indicativo',
                    'celular_proveedor' => 'Celular',
                    'correo_proveedor' => 'Correo',
                    'direccion_proveedor' => 'Direccion',
                ],
            ],
    ];
};
