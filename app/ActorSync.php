<?php

declare(strict_types=1);

namespace App;

final class ActorSync
{
    public static function afterSave(string $type, int $id, array $data): void
    {
        if ($id <= 0) {
            return;
        }
        try {
            switch ($type) {
                case 'propietarios':
                    self::syncPropietario($id, $data);
                    break;
                case 'arrendatarios':
                    self::syncArrendatario($id, $data);
                    break;
                case 'copropiedades':
                    self::syncCopropiedad($id, $data);
                    break;
                case 'funcionarios':
                    self::syncFuncionario($id, $data);
                    break;
            }
        } catch (Throwable) {
        }
    }

    private static function syncPropietario(int $id, array $data): void
    {
        $inmuebles = Database::table('jet_cct_inmuebles');
        $contratos = Database::table('jet_cct_contratos_arrendamiento');

        $updateData = array_filter([
            'id_propietario' => $id,
            'nombre_propietario' => $data['nombre'] ?? null,
            'documento_propietario' => $data['documento'] ?? null,
            'celular_propietario' => $data['celular'] ?? null,
            'correo_propietario' => $data['correo'] ?? null,
            'direccion_propietario' => $data['direccion'] ?? null,
        ], static fn ($v) => $v !== null && $v !== '');

        if ($updateData === []) {
            return;
        }

        if (Database::tableExists($inmuebles) && Database::columnExists($inmuebles, 'id_propietario')) {
            Database::execute(
                "UPDATE {$inmuebles} SET " . self::buildSet($updateData) . " WHERE id_propietario = ?",
                self::buildTypes($updateData) . 'i',
                array_merge(array_values($updateData), [$id])
            );
        }

        if (Database::tableExists($contratos) && Database::columnExists($contratos, 'id_propietario')) {
            Database::execute(
                "UPDATE {$contratos} SET " . self::buildSet($updateData) . " WHERE id_propietario = ?",
                self::buildTypes($updateData) . 'i',
                array_merge(array_values($updateData), [$id])
            );
        }
    }

    private static function syncArrendatario(int $id, array $data): void
    {
        $inmuebles = Database::table('jet_cct_inmuebles');
        $contratos = Database::table('jet_cct_contratos_arrendamiento');

        $updateData = array_filter([
            'nombre_arrendatario' => $data['nombre'] ?? null,
            'documento_arrendatario' => $data['documento'] ?? null,
            'celular_arrendatario' => $data['celular'] ?? null,
            'correo_arrendatario' => $data['correo'] ?? null,
        ], static fn ($v) => $v !== null && $v !== '');

        if ($updateData === []) {
            return;
        }

        if (Database::tableExists($inmuebles) && Database::columnExists($inmuebles, 'id_arrendatario')) {
            Database::execute(
                "UPDATE {$inmuebles} SET " . self::buildSet($updateData) . " WHERE id_arrendatario = ?",
                self::buildTypes($updateData) . 'i',
                array_merge(array_values($updateData), [$id])
            );
        }

        if (Database::tableExists($contratos) && Database::columnExists($contratos, 'id_arrendatario')) {
            Database::execute(
                "UPDATE {$contratos} SET " . self::buildSet($updateData) . " WHERE id_arrendatario = ?",
                self::buildTypes($updateData) . 'i',
                array_merge(array_values($updateData), [$id])
            );
        }
    }

    private static function syncCopropiedad(int $id, array $data): void
    {
        $inmuebles = Database::table('jet_cct_inmuebles');
        $contratos = Database::table('jet_cct_contratos_arrendamiento');

        $updateData = array_filter([
            'nombre_copropiedad' => $data['copropiedad'] ?? null,
            'nit_copropiedad' => $data['nit'] ?? null,
            'correo_copropiedad' => $data['correo'] ?? null,
        ], static fn ($v) => $v !== null && $v !== '');

        if ($updateData === []) {
            return;
        }

        if (Database::tableExists($inmuebles) && Database::columnExists($inmuebles, 'id_copropiedad')) {
            Database::execute(
                "UPDATE {$inmuebles} SET " . self::buildSet($updateData) . " WHERE id_copropiedad = ?",
                self::buildTypes($updateData) . 'i',
                array_merge(array_values($updateData), [$id])
            );
        }

        if (Database::tableExists($contratos) && Database::columnExists($contratos, 'id_copropiedad')) {
            Database::execute(
                "UPDATE {$contratos} SET " . self::buildSet($updateData) . " WHERE id_copropiedad = ?",
                self::buildTypes($updateData) . 'i',
                array_merge(array_values($updateData), [$id])
            );
        }
    }

    private static function syncFuncionario(int $id, array $data): void
    {
        $wpUsers = Database::table('users');
        $wpUserMeta = Database::table('usermeta');

        if (!Database::tableExists($wpUsers) || empty($data['id_empleado'])) {
            return;
        }

        $wpUserId = (int) $data['id_empleado'];
        $displayName = trim((string) ($data['nombre'] ?? ''));
        $userEmail = trim((string) ($data['correo'] ?? ''));

        if ($displayName !== '' || $userEmail !== '') {
            $parts = [];
            $params = [];
            $types = '';
            if ($displayName !== '') {
                $parts[] = 'display_name = ?';
                $params[] = $displayName;
                $types .= 's';
            }
            if ($userEmail !== '') {
                $parts[] = 'user_email = ?';
                $params[] = $userEmail;
                $types .= 's';
            }
            if ($parts !== []) {
                $params[] = $wpUserId;
                $types .= 'i';
                Database::execute(
                    "UPDATE {$wpUsers} SET " . implode(', ', $parts) . " WHERE ID = ?",
                    $types,
                    $params
                );
            }
        }

        if (Database::tableExists($wpUserMeta)) {
            $metaMap = [
                'id_sucursal' => 'sucursal',
                'id_cargo' => 'id_cargo',
                'celular' => 'celular',
                'activo' => 'activo',
            ];
            foreach ($metaMap as $field => $metaKey) {
                if (!empty($data[$field])) {
                    self::updateUserMeta($wpUserMeta, $wpUserId, $metaKey, (string) $data[$field]);
                }
            }
            $permKeys = [
                'mercado_libre_destacados' => 'mercado_libre_destacados',
                'proppit_promocionados' => 'proppit_promocionados',
                'ciencuadras_ascendidos' => 'ciencuadras_ascendidos',
                'ciencuadras_destacados' => 'ciencuadras_destacados',
                'finca_raiz_silver' => 'finca_raiz_silver',
                'finca_raiz_gold' => 'finca_raiz_gold',
                'finca_raiz_black' => 'finca_raiz_black',
            ];
            foreach ($permKeys as $field => $metaKey) {
                if (array_key_exists($field, $data)) {
                    self::updateUserMeta($wpUserMeta, $wpUserId, $metaKey, (string) $data[$field]);
                }
            }
        }
    }

    private static function updateUserMeta(string $table, int $userId, string $metaKey, string $value): void
    {
        $exists = Database::value(
            "SELECT umeta_id FROM {$table} WHERE user_id = ? AND meta_key = ? LIMIT 1",
            'is',
            [$userId, $metaKey]
        );
        if ($exists) {
            Database::execute(
                "UPDATE {$table} SET meta_value = ? WHERE user_id = ? AND meta_key = ?",
                'sis',
                [$value, $userId, $metaKey]
            );
        } else {
            Database::execute(
                "INSERT INTO {$table} (user_id, meta_key, meta_value) VALUES (?, ?, ?)",
                'iss',
                [$userId, $metaKey, $value]
            );
        }
    }

    private static function buildSet(array $data): string
    {
        $parts = [];
        foreach (array_keys($data) as $column) {
            $parts[] = "`{$column}` = ?";
        }
        return implode(', ', $parts);
    }

    private static function buildTypes(array $data): string
    {
        return str_repeat('s', count($data));
    }
}