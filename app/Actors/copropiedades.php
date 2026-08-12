<?php

declare(strict_types=1);

namespace App;

return static function (array $options, array $preferencias): array {
    extract($options, EXTR_SKIP);
    return [
            'copropiedades' => [
                'title' => 'Copropiedades',
                'table' => Database::table('jet_cct_copropiedades'),
                'name' => 'copropiedad',
                'email' => 'correo',
                'phone' => 'contacto',
                'rol_persona' => 'Copropiedad',
                'es_actor' => true,
                'permitir_llamada' => true,
                'sync_on_save' => 'copropiedad',
                'opciones_filtros' => [],
                'campos' => array_merge([
                    ['id' => 'copropiedad', 'label' => 'Nombre Copropiedad', 'type' => 'text', 'cols' => 12],
                    ['id' => 'nit', 'label' => 'NIT', 'type' => 'text', 'cols' => 6],
                    ['id' => 'administrador', 'label' => 'Administrador', 'type' => 'text', 'cols' => 6],
                    ['id' => 'barrio', 'label' => 'Barrio', 'type' => 'text', 'cols' => 6],
                    ['id' => 'zona', 'label' => 'Zona', 'type' => 'text', 'cols' => 6],
                    ['id' => 'direccion', 'label' => 'Dirección', 'type' => 'text', 'cols' => 12],
                    ['id' => 'indicativo', 'label' => 'Indicativo', 'type' => 'select', 'opts' => $opts_indicativo, 'cols' => 2],
                    ['id' => 'pais', 'label' => 'País', 'type' => 'text', 'cols' => 4],
                    ['id' => 'contacto', 'label' => 'Celular/Contacto', 'type' => 'text', 'cols' => 4],
                    ['id' => 'correo', 'label' => 'Correo', 'type' => 'email', 'cols' => 6],
                    ['id' => 'banco', 'label' => 'Banco', 'type' => 'text', 'cols' => 4],
                    ['id' => 'tipo_cuenta', 'label' => 'Tipo Cuenta', 'type' => 'select', 'opts' => $opts_cuenta, 'cols' => 4],
                    ['id' => 'numero_cuenta', 'label' => 'No. Cuenta', 'type' => 'text', 'cols' => 4],
                ], $preferencias),
                'vista' => ['copropiedad', 'indicativo', 'contacto', 'correo', 'v_inmuebles'],
                'labels' => ['copropiedad' => 'Copropiedad', 'indicativo' => 'Ind.', 'contacto' => 'Contacto', 'correo' => 'Correo', 'v_inmuebles' => 'Inmuebles Vinculados'],
                'fields' => [
                    'copropiedad' => 'Copropiedad',
                    'nit' => 'NIT',
                    'administrador' => 'Administrador',
                    'barrio' => 'Barrio',
                    'zona' => 'Zona',
                    'direccion' => 'Direccion',
                    'contacto' => 'Contacto',
                    'correo' => 'Correo',
                ],
            ],
    ];
};
