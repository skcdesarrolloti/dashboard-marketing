<?php

declare(strict_types=1);

namespace App;

final class PermissionService
{
    public const ROLE_ADMIN = 'admin';
    public const ROLE_PROMOCION = 'promocion';
    public const ROLE_PROPIETARIO = 'propietario';
    public const ROLE_ARRENDATARIO = 'arrendatario';
    public const ROLE_FUNCIONARIO = 'funcionario';
    public const ROLE_RESTRICTED = 'restricted';
    public const ROLE_SUBSCRIBER = 'subscriber';
    private const CONFIG_PROMOTION_CARGO_IDS = [6];
    private const CONFIG_SYSTEM_CARGO_IDS = [11, 12, 13, 14];

    private const CARGO_ROLES = [
        'Gerencia General' => self::ROLE_ADMIN,
        'Asistente de Desarrollo' => self::ROLE_ADMIN,
        'Gerencia Comercial' => self::ROLE_ADMIN,
        'Gerencia Administrativa' => self::ROLE_ADMIN,
        'Asistente de Promocion y Colocacion' => self::ROLE_PROMOCION,
        'Asistente de Captacion y Actualizacion de Inmuebles' => self::ROLE_PROMOCION,
        'Asistente de Servicios al Propietario' => self::ROLE_PROPIETARIO,
        'Asistente de Servicios al Arrendatario' => self::ROLE_ARRENDATARIO,
        'Cordinador Contractual' => self::ROLE_FUNCIONARIO,
        'Mantenimiento y Servicios Publicos' => self::ROLE_FUNCIONARIO,
    ];
    private const FULL_EDIT_TYPES = [
        'contactos', 'suscriptores', 'clientes', 'club_pph', 'propietarios',
        'arrendatarios', 'codeudores', 'copropiedades', 'proveedores', 'funcionarios',
    ];

    public static function detectRole(array $user): string
    {
        $stored = strtolower(trim((string) ($user['rol'] ?? '')));
        if (in_array($stored, ['admin', 'administrator'], true)) {
            return self::ROLE_ADMIN;
        }
        foreach (self::CARGO_ROLES as $cargo => $role) {
            if (strtolower($cargo) === $stored) {
                return $role;
            }
        }
        if ($stored === 'promocion') {
            return self::ROLE_PROMOCION;
        }
        return self::ROLE_ADMIN;
    }

    public static function canView(string $actorType): bool
    {
        $role = self::currentRole();
        $stored = (new AdminRepository())->getOption('gda_role_permissions', []);
        $rawRole = (string) (Auth::user()['rol'] ?? '');
        if (is_array($stored) && isset($stored[$rawRole])) {
            return !empty($stored[$rawRole][$actorType]);
        }
        if (in_array($role, [self::ROLE_ADMIN, self::ROLE_PROMOCION], true)) {
            return true;
        }
        $allowed = [
            'contactos', 'suscriptores', 'clientes', 'propietarios',
            'arrendatarios', 'codeudores', 'copropiedades', 'club_pph',
            'funcionarios', 'proveedores',
        ];
        if ($role === self::ROLE_PROPIETARIO || $role === self::ROLE_ARRENDATARIO) {
            return in_array($actorType, $allowed, true);
        }
        if ($role === self::ROLE_FUNCIONARIO || $role === self::ROLE_RESTRICTED) {
            return in_array($actorType, ['contactos', 'suscriptores', 'clientes'], true);
        }
        return false;
    }

    public static function canDelete(string $actorType, int $actorId, int $authorId): bool
    {
        if (!in_array($actorType, self::FULL_EDIT_TYPES, true)) {
            return false;
        }

        if ($actorType === 'contactos') {
            return $authorId > 0 && $authorId === Auth::authorId();
        }
        $role = self::currentRole();
        if (in_array($role, [self::ROLE_ADMIN, self::ROLE_PROMOCION], true)) {
            return true;
        }
        return false;
    }

    public static function canEdit(string $actorType, int $actorId, int $authorId): bool
    {
        return self::canFullEdit($actorType, $actorId, $authorId) || self::canEditPreferences($actorType, $actorId, $authorId);
    }

    public static function canFullEdit(string $actorType, int $actorId, int $authorId): bool
    {
        if (!in_array($actorType, self::FULL_EDIT_TYPES, true)) {
            return false;
        }
        $role = self::currentRole();
        if (!in_array($role, [self::ROLE_ADMIN, self::ROLE_PROMOCION], true)) {
            return false;
        }
        if ($actorType === 'contactos') {
            return $authorId > 0 && $authorId === Auth::authorId();
        }
        return true;
    }

    public static function canEditPreferences(string $actorType, int $actorId, int $authorId): bool
    {
        if (self::canFullEdit($actorType, $actorId, $authorId)) {
            return true;
        }

        if (in_array($actorType, ['funcionarios', 'contactos_funcionarios'], true)) {
            return false;
        }

        return in_array(self::currentRole(), [self::ROLE_ADMIN, self::ROLE_PROMOCION], true);
    }

    public static function currentRole(): string
    {
        return self::detectRole(Auth::user());
    }

    public static function currentCargoIds(): array
    {
        $user = Auth::user();
        $raw = trim((string) ($user['id_cargo'] ?? ''));

        if ($raw === '') {
            $recordId = (int) ($user['actor_record_id'] ?? 0);
            $employeeId = (int) ($user['id'] ?? 0);
            $table = Database::table('jet_cct_funcionarios');
            if (Database::tableExists($table)) {
                if ($recordId > 0) {
                    $raw = trim((string) Database::value("SELECT id_cargo FROM {$table} WHERE _ID = ? LIMIT 1", 'i', [$recordId]));
                }
                if ($raw === '' && $employeeId > 0) {
                    $raw = trim((string) Database::value("SELECT id_cargo FROM {$table} WHERE id_empleado = ? LIMIT 1", 'i', [$employeeId]));
                }
            }
        }

        preg_match_all('/\d+/', $raw, $matches);
        return array_values(array_unique(array_map('intval', $matches[0] ?? [])));
    }

    public static function hasCargoId(array $allowed): bool
    {
        return array_intersect(self::currentCargoIds(), $allowed) !== [];
    }

    public static function canEditPromotionConfig(): bool
    {
        return self::hasCargoId(array_merge(self::CONFIG_PROMOTION_CARGO_IDS, self::CONFIG_SYSTEM_CARGO_IDS));
    }

    public static function canManageSystemConfig(): bool
    {
        return self::hasCargoId(self::CONFIG_SYSTEM_CARGO_IDS);
    }

    public static function canManageInventory(): bool
    {
        return in_array(self::currentRole(), [self::ROLE_ADMIN, self::ROLE_PROMOCION], true);
    }

    public static function canManageBranding(): bool
    {
        return in_array(self::currentRole(), [self::ROLE_ADMIN, self::ROLE_PROMOCION], true);
    }

    public static function canManageMeetings(): bool
    {
        return in_array(self::currentRole(), [self::ROLE_ADMIN, self::ROLE_PROMOCION], true);
    }

    public static function canDeleteMeetings(): bool
    {
        return self::currentRole() === self::ROLE_ADMIN;
    }

    public static function canAccessSettings(): bool
    {
        return self::canEditPromotionConfig() || self::canManageSystemConfig();
    }

    public static function canModule(string $module): bool
    {
        if ($module === 'confi_sistema') return self::canAccessSettings();
        if (self::currentRole() === self::ROLE_ADMIN) return true;
        $rawRole = (string) (Auth::user()['rol'] ?? '');
        $stored = (new AdminRepository())->getOption('gda_role_permissions', []);
        if ($module === 'reuniones'
            && self::currentRole() === self::ROLE_PROMOCION
            && (!is_array($stored) || !isset($stored[$rawRole]) || !array_key_exists('reuniones', (array) $stored[$rawRole]))) {
            return true;
        }
        return !is_array($stored) || !isset($stored[$rawRole]) || !empty($stored[$rawRole][$module]);
    }
}
