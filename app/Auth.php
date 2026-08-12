<?php

declare(strict_types=1);

namespace App;

final class Auth
{
    public static function check(): bool
    {
        return !empty($_SESSION['marketing_user']);
    }

    public static function user(): array
    {
        return $_SESSION['marketing_user'] ?? [];
    }

    public static function attempt(string $username, string $password): bool
    {
        $table = Database::table('jet_cct_funcionarios');
        $row = Database::one(
            "SELECT _ID, id_empleado, nombre, correo, rol, id_cargo, activo, user_others_apss, pass_others_apss
             FROM {$table}
             WHERE user_others_apss = ?
             LIMIT 1",
            's',
            [$username]
        );

        $stored = trim((string) ($row['pass_others_apss'] ?? ''));
        if (!$row || $stored === '' || !hash_equals($stored, $password)) {
            return false;
        }

        if (strtolower(trim((string) ($row['activo'] ?? 'Si'))) === 'no') {
            return false;
        }

        self::loginFromFuncionario($row, $username);

        return true;
    }

    public static function attemptMagicLink(string $token, int $employeeId): bool
    {
        $configuredToken = trim((string) app_config('auth.magic_login_token', ''));

        if ($configuredToken === '' || strlen($configuredToken) < 32 || $token === '' || $employeeId <= 0) {
            return false;
        }

        if (!hash_equals($configuredToken, $token)) {
            return false;
        }

        $table = Database::table('jet_cct_funcionarios');
        $row = Database::one(
            "SELECT _ID, id_empleado, nombre, correo, rol, id_cargo, activo, user_others_apss, pass_others_apss
             FROM {$table}
             WHERE id_empleado = ?
             LIMIT 1",
            'i',
            [$employeeId]
        );

        if (!$row || strtolower(trim((string) ($row['activo'] ?? 'Si'))) === 'no') {
            return false;
        }

        self::loginFromFuncionario($row, (string) $employeeId);

        return true;
    }

    public static function authorId(): int
    {
        $user = self::user();
        if (!empty($user['actor_record_id'])) return (int) ($user['id'] ?? 0);
        $legacyId = (int) ($user['id'] ?? 0);
        if ($legacyId <= 0) return 0;
        $table = Database::table('jet_cct_funcionarios');
        $employeeId = Database::value("SELECT id_empleado FROM {$table} WHERE _ID=? LIMIT 1", 'i', [$legacyId]);
        return (int) ($employeeId ?: $legacyId);
    }

    public static function logout(): void
    {
        unset($_SESSION['marketing_user']);
    }

    private static function loginFromFuncionario(array $row, string $fallbackName): void
    {
        session_regenerate_id(true);
        $_SESSION['marketing_user'] = [
            'id' => (int) ($row['id_empleado'] ?: $row['_ID']),
            'actor_record_id' => (int) $row['_ID'],
            'nombre' => (string) ($row['nombre'] ?? $fallbackName),
            'correo' => (string) ($row['correo'] ?? ''),
            'rol' => (string) ($row['rol'] ?? 'promocion'),
            'id_cargo' => (string) ($row['id_cargo'] ?? ''),
        ];
    }
}
