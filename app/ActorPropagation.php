<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

final class ActorPropagation
{
    private const ACTIVE_CONTRACT = "estado IN ('Entregado','Por entregar','Por recibir')";
    private const ACTIVE_TICKET = "estado IN ('Nuevo','En proceso','Postergado')";
    private const ACTIVE_GENERIC = "cct_status = 'publish'";

    public function impact(string $type, int $id, array $before, array $changes): array
    {
        $impact = [];
        foreach ($this->resolvedTargets($type, $id, $before, $changes) as $target) {
            $table = $target['table'];
            $where = $target['where'];
            $types = $target['types'];
            $params = $target['params'];
            $active = (int) Database::value("SELECT COUNT(*) FROM {$table} WHERE {$where}", $types, $params);
            $allWhere = $target['all_where'];
            $allTypes = $target['all_types'];
            $allParams = $target['all_params'];
            $all = (int) Database::value("SELECT COUNT(*) FROM {$table} WHERE {$allWhere}", $allTypes, $allParams);
            $impact[] = [
                'table' => $table,
                'label' => $target['label'],
                'active' => $active,
                'excluded' => max(0, $all - $active),
                'fields' => array_keys($target['data']),
            ];
        }
        if ($type === 'funcionarios' && $this->hasMetaChanges($changes)) {
            $impact[] = [
                'table' => Database::table('usermeta'),
                'label' => 'Perfil del usuario WordPress',
                'active' => 1,
                'excluded' => 0,
                'fields' => array_values(array_intersect(array_keys($changes), ['id_sucursal','id_area','id_cargo','celular','activo'])),
            ];
        }
        return $impact;
    }

    public function apply(string $type, int $id, array $before, array $changes): array
    {
        $result = [];
        foreach ($this->resolvedTargets($type, $id, $before, $changes) as $target) {
            if ($target['data'] === []) {
                continue;
            }
            $sets = [];
            foreach (array_keys($target['data']) as $column) {
                $sets[] = "`{$column}` = ?";
            }
            $params = array_merge(array_values($target['data']), $target['params']);
            $types = str_repeat('s', count($target['data'])) . $target['types'];
            $ok = Database::execute(
                "UPDATE {$target['table']} SET " . implode(', ', $sets) . " WHERE {$target['where']}",
                $types,
                $params
            );
            if (!$ok) {
                throw new RuntimeException('Falló la actualización relacionada en ' . $target['label'] . '.');
            }
            $result[] = [
                'table' => $target['table'],
                'label' => $target['label'],
                'updated' => Database::affectedRows(),
                'fields' => array_keys($target['data']),
            ];
        }

        if ($type === 'funcionarios') {
            $metaUpdated = $this->syncWordPressMeta($before, $changes);
            if ($metaUpdated > 0) {
                $result[] = [
                    'table' => Database::table('usermeta'),
                    'label' => 'Perfil del usuario WordPress',
                    'updated' => $metaUpdated,
                    'fields' => array_values(array_intersect(array_keys($changes), ['id_sucursal','id_area','id_cargo','celular','activo'])),
                ];
            }
        }
        return $result;
    }

    private function resolvedTargets(string $type, int $id, array $before, array $changes): array
    {
        $targets = $this->targets($type);
        $resolved = [];
        foreach ($targets as $target) {
            $table = Database::table($target['table']);
            if (!Database::tableExists($table)) {
                continue;
            }
            $columns = Database::columns($table);
            $link = (string) $target['link'];
            if (!isset($columns[$link])) {
                continue;
            }
            $data = [];
            foreach ($target['map'] as $source => $destination) {
                if (array_key_exists($source, $changes) && isset($columns[$destination])) {
                    $data[$destination] = (string) $changes[$source]['after'];
                }
            }
            if ($data === []) {
                continue;
            }
            $active = (string) ($target['active'] ?? self::ACTIVE_GENERIC);
            if ($active === self::ACTIVE_GENERIC && isset($columns['estado'])) {
                $statePredicate = "LOWER(TRIM(COALESCE(estado, ''))) NOT IN ('cerrado','cerrada','resuelto','resuelta','finalizado','finalizada','completado','completada','cancelado','cancelada','desistido','desistida','recibido','recibida')";
                $active = isset($columns['cct_status'])
                    ? self::ACTIVE_GENERIC . ' AND ' . $statePredicate
                    : $statePredicate;
            }
            if (!isset($columns['estado']) && str_contains($active, 'estado ')) {
                $active = self::ACTIVE_GENERIC;
            }
            if (!isset($columns['cct_status']) && $active === self::ACTIVE_GENERIC) {
                $active = '1=1';
            }
            $linkValue = $id;
            if (($target['link_source'] ?? '') !== '') {
                $linkValue = (int) ($before[$target['link_source']] ?? 0);
            }
            if ($linkValue <= 0) {
                continue;
            }
            $allWhere = "`{$link}` = ?";
            $where = $allWhere . ' AND (' . $active . ')';
            $resolved[] = [
                'table' => $table,
                'label' => $target['label'],
                'data' => $data,
                'where' => $where,
                'types' => 'i',
                'params' => [$linkValue],
                'all_where' => $allWhere,
                'all_types' => 'i',
                'all_params' => [$linkValue],
            ];
        }

        if ($type === 'codeudores') {
            $special = $this->codeudorTarget($before, $changes);
            if ($special !== null) {
                $resolved[] = $special;
            }
        }
        return $resolved;
    }

    private function targets(string $type): array
    {
        $partyTargets = static function (string $link, string $party, array $tables): array {
            $map = [
                'nombre' => $party,
                'documento' => 'documento_' . $party,
                'celular' => 'celular_' . $party,
                'correo' => 'correo_' . $party,
                'indicativo' => 'indicativo_' . $party,
                'direccion' => 'direccion_' . $party,
            ];
            return array_map(static fn (array $item): array => [
                'table' => $item[0],
                'label' => $item[1],
                'link' => $link,
                'active' => $item[2],
                'map' => $map,
            ], $tables);
        };

        return match ($type) {
            'propietarios' => $partyTargets('id_propietario', 'propietario', [
                ['jet_cct_contratos_arrendamiento', 'Contratos vigentes', self::ACTIVE_CONTRACT],
                ['jet_cct_tickets', 'Tickets abiertos', self::ACTIVE_TICKET],
                ['jet_cct_cotizacion_mantenimiento', 'Cotizaciones de mantenimiento', self::ACTIVE_GENERIC],
                ['jet_cct_gestiones_cobro', 'Gestiones de cobro', self::ACTIVE_GENERIC],
                ['jet_cct_revision_correctiva', 'Revisiones correctivas', self::ACTIVE_GENERIC],
                ['jet_cct_revision_entrega', 'Revisiones de entrega', self::ACTIVE_GENERIC],
                ['jet_cct_revision_preventiva', 'Revisiones preventivas', self::ACTIVE_GENERIC],
                ['jet_cct_revision_recibo', 'Revisiones de recibo', self::ACTIVE_GENERIC],
                ['jet_cct_solicitudes_actualizacion', 'Solicitudes de actualización', self::ACTIVE_GENERIC],
                ['jet_cct_maquinarias', 'Maquinarias activas', self::ACTIVE_GENERIC],
                ['jet_cct_vehiculos', 'Vehículos activos', self::ACTIVE_GENERIC],
            ]),
            'arrendatarios' => $partyTargets('id_arrendatario', 'arrendatario', [
                ['jet_cct_contratos_arrendamiento', 'Contratos vigentes', self::ACTIVE_CONTRACT],
                ['jet_cct_tickets', 'Tickets abiertos', self::ACTIVE_TICKET],
                ['jet_cct_cotizacion_mantenimiento', 'Cotizaciones de mantenimiento', self::ACTIVE_GENERIC],
                ['jet_cct_gestiones_cobro', 'Gestiones de cobro', self::ACTIVE_GENERIC],
                ['jet_cct_revision_correctiva', 'Revisiones correctivas', self::ACTIVE_GENERIC],
                ['jet_cct_revision_entrega', 'Revisiones de entrega', self::ACTIVE_GENERIC],
                ['jet_cct_revision_preventiva', 'Revisiones preventivas', self::ACTIVE_GENERIC],
                ['jet_cct_revision_recibo', 'Revisiones de recibo', self::ACTIVE_GENERIC],
            ]),
            'copropiedades' => [
                ['table'=>'jet_cct_contratos_arrendamiento','label'=>'Contratos vigentes','link'=>'id_copropiedad','active'=>self::ACTIVE_CONTRACT,'map'=>['copropiedad'=>'copropiedad','nit'=>'nit_copropiedad','correo'=>'correo_copropiedad','contacto'=>'celular_copropiedad']],
                ['table'=>'jet_cct_tickets','label'=>'Tickets abiertos','link'=>'id_copropiedad','active'=>self::ACTIVE_TICKET,'map'=>['correo'=>'correo_copropiedad','contacto'=>'celular_copropiedad','indicativo'=>'indicativo_copropiedad']],
            ],
            'clientes' => [
                ['table'=>'jet_cct_estudios_aseguradoras','label'=>'Estudios de aseguradora activos','link'=>'id_cliente','active'=>self::ACTIVE_GENERIC,'map'=>['correo'=>'correo_cliente','celular'=>'celular_cliente']],
            ],
            'proveedores' => [
                ['table'=>'jet_cct_ordenes','label'=>'Órdenes en espera','link'=>'id_proveedor','active'=>"estado = 'Esperando respuesta'",'map'=>['correo_proveedor'=>'correo_proveedor','celular_proveedor'=>'celular_proveedor','direccion_proveedor'=>'direccion_proveedor']],
            ],
            'funcionarios' => [
                ['table'=>'users','label'=>'Usuario WordPress','link'=>'ID','link_source'=>'id_empleado','active'=>'1=1','map'=>['nombre'=>'display_name','correo'=>'user_email']],
            ],
            default => [],
        };
    }

    private function codeudorTarget(array $before, array $changes): ?array
    {
        $contractId = (int) ($before['id_contrato'] ?? 0);
        $document = trim((string) ($before['documento'] ?? ''));
        $table = Database::table('jet_cct_contratos_arrendamiento');
        if ($contractId <= 0 || $document === '' || !Database::tableExists($table)) {
            return null;
        }
        $contract = Database::one("SELECT * FROM {$table} WHERE _ID = ? LIMIT 1", 'i', [$contractId]);
        if (!$contract || !in_array((string) ($contract['estado'] ?? ''), ['Entregado','Por entregar','Por recibir'], true)) {
            return null;
        }
        $slot = 0;
        for ($i = 1; $i <= 4; $i++) {
            if (trim((string) ($contract['documento_codeudor_' . $i] ?? '')) === $document) {
                $slot = $i;
                break;
            }
        }
        if ($slot === 0) {
            return null;
        }
        $columns = Database::columns($table);
        $map = ['nombre'=>'codeudor_' . $slot,'documento'=>'documento_codeudor_' . $slot,'celular'=>'celular_codeudor_' . $slot,'correo'=>'correo_codeudor_' . $slot,'direccion'=>'dir_codeudor_' . $slot];
        $data = [];
        foreach ($map as $source => $destination) {
            if (array_key_exists($source, $changes) && isset($columns[$destination])) {
                $data[$destination] = (string) $changes[$source]['after'];
            }
        }
        if ($data === []) {
            return null;
        }
        return [
            'table'=>$table,'label'=>'Contrato vigente (codeudor ' . $slot . ')','data'=>$data,
            'where'=>'_ID = ?','types'=>'i','params'=>[$contractId],
            'all_where'=>'_ID = ?','all_types'=>'i','all_params'=>[$contractId],
        ];
    }

    private function syncWordPressMeta(array $before, array $changes): int
    {
        $userId = (int) ($before['id_empleado'] ?? 0);
        $table = Database::table('usermeta');
        if ($userId <= 0 || !Database::tableExists($table)) {
            return 0;
        }
        $map = [
            'id_sucursal' => 'sucursal',
            'id_area' => 'id_area',
            'id_cargo' => 'id_cargo',
            'celular' => 'celular',
            'activo' => 'activo',
        ];
        $updated = 0;
        foreach ($map as $field => $metaKey) {
            if (!isset($changes[$field])) {
                continue;
            }
            $value = (string) $changes[$field]['after'];
            $exists = Database::value(
                "SELECT umeta_id FROM {$table} WHERE user_id = ? AND meta_key = ? LIMIT 1",
                'is',
                [$userId, $metaKey]
            );
            $ok = $exists
                ? Database::execute(
                    "UPDATE {$table} SET meta_value = ? WHERE user_id = ? AND meta_key = ?",
                    'sis',
                    [$value, $userId, $metaKey]
                )
                : Database::execute(
                    "INSERT INTO {$table} (user_id, meta_key, meta_value) VALUES (?, ?, ?)",
                    'iss',
                    [$userId, $metaKey, $value]
                );
            if (!$ok) {
                throw new RuntimeException('No fue posible sincronizar el perfil de WordPress.');
            }
            $updated++;
        }
        return $updated;
    }

    private function hasMetaChanges(array $changes): bool
    {
        return array_intersect(array_keys($changes), ['id_sucursal','id_area','id_cargo','celular','activo']) !== [];
    }
}
