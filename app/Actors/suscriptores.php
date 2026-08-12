<?php

declare(strict_types=1);

namespace App;

return static function (array $options, array $preferencias): array {
    extract($options, EXTR_SKIP);
    return [
            'suscriptores' => [
                'title' => 'Suscriptores',
                'table' => Database::table('jet_cct_newsletter'),
                'name' => 'nombre',
                'email' => 'correo',
                'phone' => 'celular',
                'rol_persona' => 'Suscriptor',
                'es_actor' => true,
                'permitir_llamada' => true,
                'permitir_import' => true,
                'force_tipo_actor' => 'Usuario voluntario',
                'opciones_filtros' => ['suscrito' => $opts_si_no, 'ciudad' => '__distinct__', 'estado' => '__distinct__'],
                'campos' => array_merge([
                    ['id' => 'nombre', 'label' => 'Nombre', 'type' => 'text', 'cols' => 6],
                    ['id' => 'documento', 'label' => 'Documento', 'type' => 'text', 'cols' => 6],
                    ['id' => 'indicativo', 'label' => 'Indicativo', 'type' => 'select', 'opts' => $opts_indicativo, 'cols' => 3],
                    ['id' => 'celular', 'label' => 'Celular', 'type' => 'text', 'cols' => 3],
                    ['id' => 'correo', 'label' => 'Correo', 'type' => 'email', 'cols' => 6],
                    ['id' => 'pais', 'label' => 'País', 'type' => 'text', 'cols' => 4],
                    ['id' => 'ciudad', 'label' => 'Ciudad', 'type' => 'text', 'cols' => 4],
                    ['id' => 'estado', 'label' => 'Estado', 'type' => 'text', 'cols' => 4],
                    ['id' => 'barrio', 'label' => 'Barrio', 'type' => 'text', 'cols' => 6],
                    ['id' => 'suscrito', 'label' => 'Suscrito', 'type' => 'select', 'opts' => $opts_si_no, 'cols' => 3],
                    ['id' => 'politica', 'label' => 'Acepta política', 'type' => 'select', 'opts' => $opts_si_no, 'cols' => 3],
                    ['id' => 'razon', 'label' => 'Razón', 'type' => 'textarea', 'cols' => 6, 'rows' => 3],
                    ['id' => 'cumpleanos', 'label' => 'Cumpleaños', 'type' => 'date', 'cols' => 6],
                    ['id' => 'sexo', 'label' => 'Sexo', 'type' => 'select', 'opts' => $opts_sexo, 'cols' => 6],
                ], $preferencias),
                'vista' => ['nombre', 'indicativo', 'celular', 'correo', 'ciudad', 'suscrito'],
                'labels' => ['nombre' => 'Nombre', 'indicativo' => 'Ind.', 'celular' => 'Celular', 'correo' => 'Correo', 'ciudad' => 'Ciudad', 'suscrito' => 'Suscrito'],
                'fields' => [
                    'nombre' => 'Nombre',
                    'documento' => 'Documento',
                    'indicativo' => 'Indicativo',
                    'celular' => 'Celular',
                    'correo' => 'Correo',
                    'ciudad' => 'Ciudad',
                    'estado' => 'Estado',
                    'suscrito' => 'Suscrito',
                ],
            ],
    ];
};
