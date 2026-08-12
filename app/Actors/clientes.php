<?php

declare(strict_types=1);

namespace App;

return static function (array $options, array $preferencias): array {
    extract($options, EXTR_SKIP);
    return [
            'clientes' => [
                'title' => 'Clientes',
                'table' => Database::table('jet_cct_clientes'),
                'name' => 'nombre',
                'email' => 'correo',
                'phone' => 'celular',
                'rol_persona' => 'Cliente',
                'es_actor' => true,
                'permitir_llamada' => true,
                'permitir_import' => true,
                'opciones_filtros' => ['tipo_cliente' => '__distinct__'],
                'campos' => array_merge([
                    ['id' => 'nombre', 'label' => 'Nombre', 'type' => 'text', 'cols' => 12],
                    ['id' => 'indicativo', 'label' => 'Indicativo', 'type' => 'select', 'opts' => $opts_indicativo, 'cols' => 2],
                    ['id' => 'celular', 'label' => 'Celular', 'type' => 'text', 'cols' => 4],
                    ['id' => 'correo', 'label' => 'Correo', 'type' => 'email', 'cols' => 6],
                    ['id' => 'tipo_cliente', 'label' => 'Tipo Cliente', 'type' => 'select', 'opts' => [], 'cols' => 12],
                    ['id' => 'pais', 'label' => 'País', 'type' => 'text', 'cols' => 12],
                ], $preferencias),
                'vista' => ['nombre', 'indicativo', 'celular', 'correo', 'tipo_cliente'],
                'labels' => ['nombre' => 'Nombre', 'indicativo' => 'Ind.', 'celular' => 'Celular', 'correo' => 'Correo', 'tipo_cliente' => 'Tipo'],
                'fields' => [
                    'nombre' => 'Nombre',
                    'indicativo' => 'Indicativo',
                    'celular' => 'Celular',
                    'correo' => 'Correo',
                    'tipo_cliente' => 'Tipo cliente',
                    'pais' => 'Pais',
                ],
            ],
    ];
};
