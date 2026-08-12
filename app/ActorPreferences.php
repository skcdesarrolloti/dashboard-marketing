<?php

declare(strict_types=1);

namespace App;

final class ActorPreferences
{
    public const CANALES = ['email', 'sms', 'whatsapp'];

    public const CATEGORIAS = ['notif', 'solicitud', 'info', 'promo', 'marketing', 'cartera', 'aviso'];

    public const CATEGORIA_LABELS = [
        'notif' => 'Notificaciones',
        'solicitud' => 'Solicitud',
        'info' => 'Información',
        'promo' => 'Promoción',
        'marketing' => 'Marketing',
        'cartera' => 'Cartera',
        'aviso' => 'Aviso',
    ];

    public const CANAL_LABELS = [
        'email' => 'Email',
        'sms' => 'SMS',
        'whatsapp' => 'WhatsApp',
    ];

    public static function campos(): array
    {
        $campos = [
            ['id' => 'preferencias_header', 'label' => 'Preferencias de comunicación', 'type' => 'header', 'cols' => 12],
            ['id' => 'bloqueos_header', 'label' => 'Canales bloqueados', 'type' => 'subheader', 'cols' => 12],
            ['id' => 'bloqueo_email', 'label' => 'No enviar Email', 'type' => 'checkbox', 'cols' => 4],
            ['id' => 'bloqueo_sms', 'label' => 'No enviar SMS', 'type' => 'checkbox', 'cols' => 4],
            ['id' => 'bloqueo_whatsapp', 'label' => 'No enviar WhatsApp', 'type' => 'checkbox', 'cols' => 4],
        ];

        $catIcons = ['Notificaciones', 'Solicitud', 'Información', 'Promoción', 'Marketing', 'Cartera', 'Aviso'];
        $i = 0;
        foreach (self::CATEGORIAS as $cat) {
            $campos[] = ['id' => $cat . '_header', 'label' => 'Permisos: ' . $catIcons[$i], 'type' => 'subheader', 'cols' => 12];
            foreach (self::CANALES as $canal) {
                $campos[] = [
                    'id' => 'permite_' . $cat . '_' . $canal,
                    'label' => self::CANAL_LABELS[$canal],
                    'type' => 'checkbox',
                    'cols' => 4,
                ];
            }
            $i++;
        }

        $campos[] = ['id' => 'motivo_bloqueo', 'label' => 'Motivo del Bloqueo (opcional)', 'type' => 'textarea', 'cols' => 12, 'rows' => 2];

        return $campos;
    }

    public static function fieldIds(): array
    {
        $ids = [];
        foreach (self::campos() as $field) {
            $type = (string) ($field['type'] ?? '');
            $id = (string) ($field['id'] ?? '');
            if ($id === '' || in_array($type, ['header', 'subheader'], true)) {
                continue;
            }
            $ids[$id] = true;
        }

        return $ids;
    }

    public static function permite(array $actor, string $categoria, string $canal, string $tipoActor = ''): bool
    {
        $categoria = self::normalizeCategoria($categoria);
        $canal = strtolower(trim($canal));
        $tipo = strtolower(trim($tipoActor));
        if (in_array($tipo, ['funcionarios', 'contactos_funcionarios'], true)) {
            return true;
        }

        $bloqueoField = 'bloqueo_' . $canal;
        if (!empty($actor[$bloqueoField]) && (int) $actor[$bloqueoField] === 1) {
            return false;
        }

        $permiteField = 'permite_' . $categoria . '_' . $canal;
        if (array_key_exists($permiteField, $actor)) {
            return (int) $actor[$permiteField] === 1;
        }

        if (in_array($categoria, ['promo', 'marketing'], true)) {
            return false;
        }

        return true;
    }

    public static function normalizeCategoria(string $categoria): string
    {
        $categoria = strtolower(trim($categoria));
        $categoria = str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], $categoria);

        return match ($categoria) {
            'notificaciones', 'notificacion', 'notif' => 'notif',
            'solicitud', 'solicitudes' => 'solicitud',
            'informacion', 'informaciones', 'info' => 'info',
            'promocion', 'promociones', 'promo' => 'promo',
            'marketing' => 'marketing',
            'cartera' => 'cartera',
            'aviso', 'avisos' => 'aviso',
            default => $categoria,
        };
    }

    public static function blockColumnFor(string $canal): string
    {
        return match ($canal) {
            'sms' => 'bloqueo_sms',
            'whatsapp' => 'bloqueo_whatsapp',
            default => 'bloqueo_email',
        };
    }

    public static function isCheckboxField(string $fieldId): bool
    {
        return str_starts_with($fieldId, 'bloqueo_') || str_starts_with($fieldId, 'permite_');
    }
}
