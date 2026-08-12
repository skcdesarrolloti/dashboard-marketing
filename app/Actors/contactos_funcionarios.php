<?php

declare(strict_types=1);

namespace App;

return static function (array $options, array $preferencias): array {
    extract($options, EXTR_SKIP);
    return [
            'contactos_funcionarios' => [
                'title' => 'Contactos de Funcionarios',
                'table' => Database::table('jet_cct_contactos'),
                'name' => 'nombre',
                'email' => 'correo',
                'phone' => 'celular',
                'rol_persona' => 'Contacto',
                'es_actor' => true,
                'permitir_llamada' => true,
                'permitir_import' => false,
                'readonly' => true,
                'restrict_by_author' => false,
                'opciones_filtros' => ['tipo_contacto' => $opts_tipo_contacto],
                'campos' => [
                    ['id' => 'nombre', 'label' => 'Nombre Completo', 'type' => 'text', 'cols' => 12],
                    ['id' => 'tipo_contacto', 'label' => 'Tipo de Contacto', 'type' => 'select', 'opts' => $opts_tipo_contacto, 'cols' => 6],
                    ['id' => 'indicativo', 'label' => 'Indicativo', 'type' => 'select', 'opts' => $opts_indicativo, 'cols' => 2],
                    ['id' => 'celular', 'label' => 'Celular', 'type' => 'text', 'cols' => 4],
                    ['id' => 'correo', 'label' => 'Correo Electrónico', 'type' => 'email', 'cols' => 12],
                ],
                'vista' => ['nombre', 'tipo_contacto', 'indicativo', 'celular', 'correo', 'author_name'],
                'labels' => ['nombre' => 'Nombre', 'tipo_contacto' => 'Tipo', 'indicativo' => 'Ind.', 'celular' => 'Celular', 'correo' => 'Correo', 'author_name' => 'Funcionario'],
                'fields' => [
                    'nombre' => 'Nombre completo',
                    'tipo_contacto' => 'Tipo de contacto',
                    'indicativo' => 'Indicativo',
                    'celular' => 'Celular',
                    'correo' => 'Correo',
                ],
            ],
    ];
};
