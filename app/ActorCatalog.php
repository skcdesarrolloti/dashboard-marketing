<?php

declare(strict_types=1);

namespace App;

final class ActorCatalog
{
    private const TYPES = [
        'contactos', 'suscriptores', 'clientes', 'club_pph',
        'propietarios', 'arrendatarios', 'codeudores', 'copropiedades', 'proveedores', 'funcionarios',
    ];

    public static function all(): array
    {
        static $catalog;
        if (is_array($catalog)) {
            return $catalog;
        }

        $options = ActorOptions::all();
        $preferences = ActorPreferences::campos();
        $catalog = [];
        foreach (self::TYPES as $type) {
            $factory = require __DIR__ . '/Actors/' . $type . '.php';
            $catalog += $factory($options, $preferences);
        }
        return $catalog;
    }

    public static function get(string $type): ?array
    {
        return self::all()[$type] ?? null;
    }

    public static function fieldMap(string $type): array
    {
        $config = self::get($type) ?? [];
        $map = [];
        foreach (($config['campos'] ?? []) as $field) {
            $id = trim((string) ($field['id'] ?? ''));
            if ($id !== '' && !in_array((string) ($field['type'] ?? ''), ['header', 'subheader'], true)) {
                $map[$id] = $id;
            }
        }
        foreach (array_keys($config['fields'] ?? []) as $id) {
            $map[(string) $id] = (string) $id;
        }
        return $map;
    }
}
