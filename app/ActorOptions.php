<?php

declare(strict_types=1);

namespace App;

final class ActorOptions
{
    public static function all(): array
    {
        return [
            'opts_tipo_doc' => ['Cédula de Ciudadania', 'Cédula de Extranjería', 'Pasaporte', 'NIT', 'Tarjeta de Identidad', 'Permiso Especial'],
            'opts_tipo_per' => ['Natural', 'Juridica'],
            'opts_cuenta' => ['Ahorros', 'Corriente'],
            'opts_sexo' => ['Masculino', 'Femenino'],
            'opts_cargo' => ['Auxiliar de Servicios Inmobiliarios', 'Proveedor de Servicios de Entrega', 'Recibo e Inspección de Inmuebles', 'Consultor de Arriendo', 'Consultor de Venta', 'Proveedor de Servicios de Consultoría Comercial Inmobiliaria'],
            'opts_tipo_contacto' => ['Amigo', 'Familiar', 'Colega', 'Universidad', 'Trabajo', 'Referido', 'Cliente Potencial', 'Vecino', 'Socio', 'Conocido', 'Proveedor', 'Otro'],
            'opts_indicativo' => ['+57', '+1', '+34', '+52', '+54', '+55', '+56', '+58', '+593', '+51', '+44', '+49'],
            'opts_si_no' => ['Si', 'No'],
            'opts_estado_civil' => ['Soltero', 'Casado', 'Union Libre', 'Divorciado', 'Viudo'],
        ];
    }
}
