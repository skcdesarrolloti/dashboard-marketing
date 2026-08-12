<?php

declare(strict_types=1);

namespace App;

final class ProjectInventory
{
    public function all(): array
    {
        $root = dirname(__DIR__, 2);
        $items = [
            ['name' => 'Gestor Actores', 'path' => $root . '/gestor-actores', 'status' => 'Integrado parcial', 'detail' => 'Actores, campañas, plantillas, envíos y llamadas.'],
            ['name' => 'Admin Actividad', 'path' => __DIR__ . '/ActivityRepository.php', 'status' => 'Integrado', 'detail' => 'Sesiones, accesos, actores, menús y navegación reciente dentro de la app.'],
            ['name' => 'Analíticas Plugin', 'path' => dirname(__DIR__) . '/analiticas.php', 'status' => 'Integrado', 'detail' => 'Eventos, logs, inmuebles, UTM y navegación.'],
            ['name' => 'Herramienta Links', 'path' => dirname(__DIR__) . '/herramienta-links-analiticas.php', 'status' => 'Integrado', 'detail' => 'Generación de enlaces UTM para campañas.'],
            ['name' => 'Gestor Destacados', 'path' => $root . '/gestor-destacados', 'status' => 'Detectado', 'detail' => 'Solicitudes, cupos y consolidado de destacados.'],
            ['name' => 'Portales SuCasa', 'path' => $root . '/portales-sucasa', 'status' => 'Detectado', 'detail' => 'Publicación y sincronización con portales inmobiliarios.'],
            ['name' => 'Calendario', 'path' => $root . '/calendario', 'status' => 'Detectado', 'detail' => 'Agenda operativa para seguimiento comercial.'],
            ['name' => 'WhatsApp y Servicios', 'path' => $root . '/whatsapp-bot-control-servicios', 'status' => 'Detectado', 'detail' => 'Control de conversaciones, tickets y servicios.'],
        ];

        foreach ($items as &$item) {
            $item['exists'] = file_exists($item['path']);
        }

        return $items;
    }
}
