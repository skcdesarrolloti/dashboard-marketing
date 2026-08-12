<?php

declare(strict_types=1);

namespace App;

return static function (array $options, array $preferencias): array {
    extract($options, EXTR_SKIP);
    return [
            'club_pph' => [
                'title' => 'Club PPH',
                'table' => Database::table('jet_cct_club_pph'),
                'name' => 'nombre',
                'email' => 'correo',
                'phone' => 'telefono',
                'rol_persona' => 'Miembro Club',
                'es_actor' => true,
                'permitir_llamada' => true,
                'require_edit_reason' => true,
                'auto_fecha_fondo' => true,
                'opciones_filtros' => ['cargo' => '__distinct__', 'barrio_copropiedad' => '__distinct__', 'nombre_funcionario' => '__distinct__', 'pertenece_fondo' => $opts_si_no, 'interesado_fondo' => $opts_si_no, 'informacion_completa' => $opts_si_no, 'tiene_tarjeta' => $opts_si_no],
                'campos' => array_merge([
                    ['id' => 'info_gen_header', 'label' => 'Información general', 'type' => 'header', 'cols' => 12],
                    ['id' => 'nombre', 'label' => 'Nombre', 'type' => 'text', 'cols' => 4],
                    ['id' => 'documento', 'label' => 'Documento', 'type' => 'text', 'cols' => 4],
                    ['id' => 'expedicion', 'label' => 'Expedición del documento', 'type' => 'text', 'cols' => 4],
                    ['id' => 'indicativo', 'label' => 'Indicativo', 'type' => 'select', 'opts' => $opts_indicativo, 'cols' => 2],
                    ['id' => 'pais', 'label' => 'País', 'type' => 'text', 'cols' => 4],
                    ['id' => 'telefono', 'label' => 'Teléfono', 'type' => 'text', 'cols' => 4],
                    ['id' => 'correo', 'label' => 'Correo', 'type' => 'email', 'cols' => 6],
                    ['id' => 'direccion', 'label' => 'Dirección', 'type' => 'text', 'cols' => 6],
                    ['id' => 'barrio_copropiedad', 'label' => 'Barrio de copropiedad', 'type' => 'text', 'cols' => 4],
                    ['id' => 'barrio_residencia', 'label' => 'Barrio de residencia', 'type' => 'text', 'cols' => 4],
                    ['id' => 'municipio', 'label' => 'Municipio de residencia', 'type' => 'text', 'cols' => 4],
                    ['id' => 'nombre_copropiedad', 'label' => 'Copropiedad', 'type' => 'text', 'cols' => 6],
                    ['id' => 'barrio_zona', 'label' => 'Barrio zona', 'type' => 'text', 'cols' => 6],

                    ['id' => 'info_add_header', 'label' => 'Información adicional', 'type' => 'header', 'cols' => 12],
                    ['id' => 'sexo', 'label' => 'Sexo', 'type' => 'select', 'opts' => $opts_sexo, 'cols' => 6],
                    ['id' => 'estado_civil', 'label' => 'Estado civil', 'type' => 'select', 'opts' => $opts_estado_civil, 'cols' => 6],
                    ['id' => 'cumpleanos', 'label' => 'Cumpleaños', 'type' => 'date', 'cols' => 12],
                    ['id' => 'cargo', 'label' => 'Cargo', 'type' => 'select', 'opts' => $opts_cargo, 'cols' => 6],
                    ['id' => 'membresia', 'label' => 'Membresía', 'type' => 'text', 'cols' => 6],
                    ['id' => 'total_puntos', 'label' => 'Total puntos', 'type' => 'number', 'cols' => 3],
                    ['id' => 'puntos_fondo', 'label' => 'Puntos fondo', 'type' => 'number', 'cols' => 3],
                    ['id' => 'tarjeta_bienvenida', 'label' => 'Tarjeta de bienvenida', 'type' => 'select', 'opts' => $opts_si_no, 'cols' => 6],

                    ['id' => 'info_veh_header', 'label' => 'Información de vehículo', 'type' => 'header', 'cols' => 12],
                    ['id' => 'tiene_vehiculo', 'label' => '¿Tiene vehículo?', 'type' => 'select', 'opts' => $opts_si_no, 'cols' => 6],
                    ['id' => 'tipo_vehiculo', 'label' => 'Tipo de vehículo', 'type' => 'select', 'opts' => ['Carro', 'Moto', 'Bicicleta', 'Otro'], 'cols' => 6, 'visible_if' => ['tiene_vehiculo' => 'Si']],

                    ['id' => 'info_conyugue_header', 'label' => 'Información del cónyuge', 'type' => 'header', 'cols' => 12],
                    ['id' => 'conyugue', 'label' => 'Nombre Cónyuge', 'type' => 'text', 'cols' => 6],
                    ['id' => 'celular_conyugue', 'label' => 'Celular Cónyuge', 'type' => 'text', 'cols' => 6],
                    ['id' => 'cumpleanos_conyugue', 'label' => 'Cumpleaños Cónyuge', 'type' => 'date', 'cols' => 6],
                    ['id' => 'actividad_conyugue', 'label' => 'Actividad económica', 'type' => 'select', 'opts' => ['Hogar', 'Independiente', 'Empleado'], 'cols' => 6],

                    ['id' => 'info_fam_header', 'label' => 'Información de grupo familiar', 'type' => 'header', 'cols' => 12],
                    ['id' => 'infancia_familia', 'label' => 'Infancia (0-7 años)', 'type' => 'number', 'cols' => 4, 'default' => 0],
                    ['id' => 'ninez_familia', 'label' => 'Niñez (8-10 años)', 'type' => 'number', 'cols' => 4, 'default' => 0],
                    ['id' => 'pubertad_familia', 'label' => 'Pubertad (10-14 años)', 'type' => 'number', 'cols' => 4, 'default' => 0],
                    ['id' => 'adolescencia_familia', 'label' => 'Adolescencia (15-19 años)', 'type' => 'number', 'cols' => 4, 'default' => 0],
                    ['id' => 'juventud_familia', 'label' => 'Juventud (20-28 años)', 'type' => 'number', 'cols' => 4, 'default' => 0],
                    ['id' => 'adulto_familia', 'label' => 'Adulto (29-45 años)', 'type' => 'number', 'cols' => 4, 'default' => 0],
                    ['id' => 'adulto_mayor_familia', 'label' => 'Adulto mayor (45-65 años)', 'type' => 'number', 'cols' => 4, 'default' => 0],
                    ['id' => 'ancianidad_familia', 'label' => 'Ancianidad (65+ años)', 'type' => 'number', 'cols' => 4, 'default' => 0],
                    ['id' => 'total_familia', 'label' => 'Total miembros', 'type' => 'text', 'cols' => 4, 'readonly' => true],

                    ['id' => 'info_fondo_header', 'label' => 'Fondo', 'type' => 'header', 'cols' => 12],
                    ['id' => 'interesado_fondo', 'label' => 'Interesado fondo', 'type' => 'select', 'opts' => $opts_si_no, 'cols' => 6],
                    ['id' => 'pertenece_fondo', 'label' => 'Pertenece fondo', 'type' => 'select', 'opts' => $opts_si_no, 'cols' => 6],
                    ['id' => 'tiene_prestamo', 'label' => 'Tiene préstamo', 'type' => 'select', 'opts' => $opts_si_no, 'cols' => 6],
                    ['id' => 'fecha_fondo', 'label' => 'Fecha de ingreso al fondo', 'type' => 'date', 'cols' => 6],

                    ['id' => 'info_ref_header', 'label' => 'Referidos y actividad', 'type' => 'header', 'cols' => 12],
                    ['id' => 'total_referidos', 'label' => 'Total referidos', 'type' => 'number', 'cols' => 4],
                    ['id' => 'referido_por', 'label' => 'Referido por', 'type' => 'text', 'cols' => 4],
                    ['id' => 'fue_referido', 'label' => 'Fue referido', 'type' => 'select', 'opts' => $opts_si_no, 'cols' => 4],
                    ['id' => 'tarjeta_referido', 'label' => 'Tarjeta referido', 'type' => 'select', 'opts' => $opts_si_no, 'cols' => 4],
                    ['id' => 'aliado_retoque', 'label' => 'Aliado retoque', 'type' => 'select', 'opts' => $opts_si_no, 'cols' => 4],
                    ['id' => 'fecha_actividad', 'label' => 'Fecha de actividad', 'type' => 'date', 'cols' => 4],

                    ['id' => 'info_bank_header', 'label' => 'Información bancaria', 'type' => 'header', 'cols' => 12],
                    ['id' => 'banco', 'label' => 'Banco', 'type' => 'text', 'cols' => 6],
                    ['id' => 'numero_cuenta', 'label' => 'Número de cuenta', 'type' => 'text', 'cols' => 6],

                    ['id' => 'info_aut_header', 'label' => 'Autorizaciones', 'type' => 'header', 'cols' => 12],
                    ['id' => 'autorizacion', 'label' => 'Autorización', 'type' => 'select', 'opts' => $opts_si_no, 'cols' => 6],
                    ['id' => 'uso_imagen', 'label' => 'Uso de imagen', 'type' => 'select', 'opts' => $opts_si_no, 'cols' => 6],
                    ['id' => 'autorizado', 'label' => 'Autorizado', 'type' => 'select', 'opts' => $opts_si_no, 'cols' => 6],
                    ['id' => 'tiene_rut', 'label' => 'Tiene RUT', 'type' => 'select', 'opts' => $opts_si_no, 'cols' => 6],
                    ['id' => 'tiene_tarjeta', 'label' => '¿Tiene tarjeta?', 'type' => 'select', 'opts' => $opts_si_no, 'cols' => 6],
                    ['id' => 'activo', 'label' => 'Activo', 'type' => 'select', 'opts' => $opts_si_no, 'cols' => 6],
                    ['id' => 'informacion_completa', 'label' => 'Información completa', 'type' => 'select', 'opts' => $opts_si_no, 'cols' => 6],

                    ['id' => 'info_obs_header', 'label' => 'Observaciones', 'type' => 'header', 'cols' => 12],
                    ['id' => 'observaciones', 'label' => 'Observaciones', 'type' => 'textarea', 'cols' => 12, 'rows' => 4],
                ], $preferencias),
                'vista' => ['nombre', 'cargo', 'telefono', 'correo', 'nombre_copropiedad', 'barrio_copropiedad', 'nombre_funcionario', 'pertenece_fondo', 'interesado_fondo', 'informacion_completa', 'tiene_tarjeta'],
                'labels' => ['nombre' => 'Nombre', 'cargo' => 'Tipo', 'telefono' => 'Teléfono', 'correo' => 'Correo', 'membresia' => 'Membresía', 'nombre_copropiedad' => 'Copropiedad', 'barrio_copropiedad' => 'Barrio Coprop.', 'nombre_funcionario' => 'Funcionario', 'pertenece_fondo' => 'Pertenece fondo', 'interesado_fondo' => 'Interesado fondo', 'informacion_completa' => 'Información completa', 'tiene_tarjeta' => 'Tarjeta bienvenida'],
                'fields' => [
                    'nombre' => 'Nombre',
                    'documento' => 'Documento',
                    'indicativo' => 'Indicativo',
                    'celular' => 'Celular',
                    'correo' => 'Correo',
                    'estado' => 'Estado',
                    'ciudad' => 'Ciudad',
                ],
            ],
    ];
};
