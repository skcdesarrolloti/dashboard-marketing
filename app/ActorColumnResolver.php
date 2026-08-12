<?php

declare(strict_types=1);

namespace App;

final class ActorColumnResolver
{
    public static function enrich(string $type, array $rows): array
    {
        if ($rows === []) {
            return $rows;
        }

        $config = ActorCatalog::get($type);
        if (!$config) {
            return $rows;
        }

        $ids = array_map(static fn (array $r): int => (int) $r['_ID'], $rows);

        $contratosByActor = self::contratosByActor($type, $ids);
        $contratosDetalleByActor = self::contratosDetalleByActor($type, $ids);
        $inmueblesByActor = self::inmueblesByActor($type, $ids);
        $inmueblesDetalleByActor = self::inmueblesDetalleByActor($type, $ids);
        $inmueblesDetalleHtmlByActor = self::inmueblesDetalleHtmlByActor($type, $ids);
        $autorByActor = self::autorByActor($type, $rows);
        $cargosById = self::cargosById($type, $rows);
        $areasById = self::areasById($type, $rows);
        $sucursalesById = self::sucursalesById($type, $rows);

        foreach ($rows as &$row) {
            $id = (int) $row['_ID'];

            $row['v_contratos'] = (int) ($contratosByActor[$id]['total'] ?? 0);
            $row['v_contratos_total'] = (int) ($contratosByActor[$id]['total'] ?? 0);
            $row['v_inmuebles'] = (int) ($inmueblesByActor[$id] ?? 0);
            $row['v_contratos_detalle'] = $contratosDetalleByActor[$id] ?? '';
            $row['v_inmuebles_detalle'] = $inmueblesDetalleByActor[$id] ?? '';
            $row['v_inmuebles_detalle_html'] = $inmueblesDetalleHtmlByActor[$id] ?? '';

            $total = (int) ($contratosByActor[$id]['total'] ?? 0);
            if ($type === 'propietarios' || $type === 'arrendatarios') {
                $row['v_estado'] = self::estadoActor((int) ($contratosByActor[$id]['entregados'] ?? 0), $total);
            }

            $row['author_name'] = $autorByActor[$id] ?? '';
            if ($type === 'funcionarios') {
                $row['cargo_nombre'] = self::labelsFromCsv((string) ($row['id_cargo'] ?? ''), $cargosById);
                $row['area_nombre'] = self::labelsFromCsv((string) ($row['id_area'] ?? ''), $areasById);
                $row['sucursal_nombre'] = self::labelsFromCsv((string) ($row['id_sucursal'] ?? ''), $sucursalesById);
            } else {
                $row['cargo_nombre'] = $cargosById[(int) ($row['id_cargo'] ?? 0)] ?? (string) ($row['id_cargo'] ?? '');
            }

            if ($type === 'codeudores') {
                $row['v_contratos'] = self::codeudorContratoLabel($contratosByActor[$id] ?? []);
            }

            if ($type === 'club_pph') {
                $row['total_familia'] = self::calcTotalFamilia($row);
            }
        }
        unset($row);

        return $rows;
    }

    public static function resolveOne(string $type, array $row): array
    {
        return self::enrich($type, [$row])[0] ?? $row;
    }

    private static function contratosByActor(string $type, array $ids): array
    {
        $table = Database::table('jet_cct_contratos_arrendamiento');
        if (!Database::tableExists($table) || $ids === []) {
            return [];
        }

        $linkColumn = match ($type) {
            'propietarios' => 'id_propietario',
            'arrendatarios' => 'id_arrendatario',
            'codeudores' => 'id_codeudor',
            default => null,
        };

        if ($linkColumn === null) {
            return [];
        }

        $columns = Database::columns($table);
        if (!isset($columns[$linkColumn])) {
            return [];
        }
        $entregadoColumn = self::pickEstadoContratoColumn($columns);

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $entregadoSelect = $entregadoColumn ? ", SUM(CASE WHEN " . self::estadoEntregadoSql($entregadoColumn) . " THEN 1 ELSE 0 END) AS entregados, SUBSTRING_INDEX(GROUP_CONCAT(`{$entregadoColumn}` ORDER BY `_ID` DESC SEPARATOR ','), ',', 1) AS latest_state" : ", 0 AS entregados, '' AS latest_state";
        $firstContractSelect = $type === 'codeudores' ? ', MIN(`_ID`) AS first_contract_id' : '';
        $rows = Database::rows(
            "SELECT `{$linkColumn}` AS link_id, COUNT(*) AS total{$entregadoSelect}{$firstContractSelect} FROM {$table} WHERE `{$linkColumn}` IN ({$placeholders}) GROUP BY `{$linkColumn}`",
            str_repeat('i', count($ids)),
            $ids
        );

        $out = [];
        foreach ($rows as $row) {
            $resultKey = (int) $row['link_id'];
            if ($resultKey <= 0) {
                continue;
            }
            $out[$resultKey] = [
                'total' => (int) $row['total'],
                'entregados' => (int) ($row['entregados'] ?? 0),
                'latest_state' => (string) ($row['latest_state'] ?? ''),
                'first_contract_id' => (int) ($row['first_contract_id'] ?? 0),
            ];
        }
        return $out;
    }

    private static function inmueblesByActor(string $type, array $ids): array
    {
        $table = Database::table('jet_cct_inmuebles');
        if (!Database::tableExists($table) || $ids === []) {
            return [];
        }

        $linkColumn = match ($type) {
            'propietarios' => 'id_propietario',
            'arrendatarios' => 'id_arrendatario',
            'copropiedades' => 'id_copropiedad',
            default => null,
        };

        if ($linkColumn === null) {
            return [];
        }

        $columns = Database::columns($table);
        if (!isset($columns[$linkColumn])) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = Database::rows(
            "SELECT `{$linkColumn}` AS link_id, COUNT(*) AS total FROM {$table} WHERE `{$linkColumn}` IN ({$placeholders}) GROUP BY `{$linkColumn}`",
            str_repeat('i', count($ids)),
            $ids
        );

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['link_id']] = (int) $row['total'];
        }
        return $out;
    }

    private static function contratosDetalleByActor(string $type, array $ids): array
    {
        $table = Database::table('jet_cct_contratos_arrendamiento');
        if (!Database::tableExists($table) || $ids === []) {
            return [];
        }

        $linkColumn = match ($type) {
            'propietarios' => 'id_propietario',
            'arrendatarios' => 'id_arrendatario',
            'codeudores' => 'id_codeudor',
            default => null,
        };
        if ($linkColumn === null) {
            return [];
        }

        $columns = Database::columns($table);
        if (!isset($columns[$linkColumn])) {
            return [];
        }

        $entregadoColumn = isset($columns['contrato_entregado']) ? 'contrato_entregado' : (isset($columns['entregado']) ? 'entregado' : (isset($columns['estado_contrato']) ? 'estado_contrato' : ''));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = Database::rows(
            "SELECT _ID, `{$linkColumn}` AS link_id" . ($entregadoColumn !== '' ? ", `{$entregadoColumn}` AS estado_raw" : ", '' AS estado_raw") . "
               FROM {$table}
              WHERE `{$linkColumn}` IN ({$placeholders})
              ORDER BY _ID DESC",
            str_repeat('i', count($ids)),
            $ids
        );

        $out = [];
        foreach ($rows as $row) {
            $linkId = (int) ($row['link_id'] ?? 0);
            if ($linkId <= 0) {
                continue;
            }
            $bucket = $out[$linkId] ?? [];
            if (count($bucket) >= 3) {
                $out[$linkId] = $bucket;
                continue;
            }
            $estado = self::normalizeContratoEstado((string) ($row['estado_raw'] ?? ''));
            $label = 'Contrato #' . (int) ($row['_ID'] ?? 0);
            if ($estado !== '') {
                $label .= ' (' . $estado . ')';
            }
            $bucket[] = $label;
            $out[$linkId] = $bucket;
        }

        foreach ($out as $linkId => $bucket) {
            $out[$linkId] = implode(', ', $bucket);
        }

        return $out;
    }

    private static function inmueblesDetalleByActor(string $type, array $ids): array
    {
        $items = self::inmueblesDetalleItemsByActor($type, $ids);
        $out = [];
        foreach ($items as $linkId => $bucket) {
            $out[$linkId] = implode(', ', array_map(static fn (array $item): string => $item['label'], $bucket));
        }

        return $out;
    }

    private static function inmueblesDetalleHtmlByActor(string $type, array $ids): array
    {
        $items = self::inmueblesDetalleItemsByActor($type, $ids);
        $out = [];
        foreach ($items as $linkId => $bucket) {
            $html = [];
            foreach ($bucket as $item) {
                $label = htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8');
                $href = htmlspecialchars('/inmueble/?id_inmueble=' . $item['id'], ENT_QUOTES, 'UTF-8');
                $html[] = '<a href="' . $href . '" target="_blank" rel="noopener">' . $label . '</a>';
            }
            $out[$linkId] = implode('<br>', $html);
        }

        return $out;
    }

    private static function inmueblesDetalleItemsByActor(string $type, array $ids): array
    {
        $table = Database::table('jet_cct_inmuebles');
        if (!Database::tableExists($table) || $ids === []) {
            return [];
        }

        $linkColumn = match ($type) {
            'propietarios' => 'id_propietario',
            'arrendatarios' => 'id_arrendatario',
            'copropiedades' => 'id_copropiedad',
            default => null,
        };
        if ($linkColumn === null) {
            return [];
        }

        $columns = Database::columns($table);
        if (!isset($columns[$linkColumn])) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $labelColumn = self::pickLabelColumn($columns, ['codigo', 'nombre_inmueble', 'inmueble', 'referencia', 'direccion']);
        $labelSelect = $labelColumn !== '' ? ", `{$labelColumn}` AS label_raw" : ", '' AS label_raw";
        $rows = Database::rows(
            "SELECT _ID, `{$linkColumn}` AS link_id{$labelSelect}
               FROM {$table}
              WHERE `{$linkColumn}` IN ({$placeholders})
              ORDER BY _ID DESC",
            str_repeat('i', count($ids)),
            $ids
        );

        $out = [];
        foreach ($rows as $row) {
            $linkId = (int) ($row['link_id'] ?? 0);
            if ($linkId <= 0) {
                continue;
            }
            $bucket = $out[$linkId] ?? [];
            if (count($bucket) >= 3) {
                $out[$linkId] = $bucket;
                continue;
            }
            $raw = trim((string) ($row['label_raw'] ?? ''));
            $bucket[] = [
                'id' => (int) ($row['_ID'] ?? 0),
                'label' => $raw !== '' ? $raw : 'Inm: ' . (int) ($row['_ID'] ?? 0),
            ];
            $out[$linkId] = $bucket;
        }

        return $out;
    }

    private static function autorByActor(string $type, array $rows): array
    {
        if ($type !== 'contactos_funcionarios' || $rows === []) {
            return [];
        }

        $usersTable = Database::table('users');
        $table = Database::table('jet_cct_funcionarios');
        if (!Database::tableExists($table) || !Database::tableExists($usersTable)) {
            return [];
        }

        $userIds = [];
        foreach ($rows as $row) {
            $userId = (int) ($row['cct_author_id'] ?? 0);
            if ($userId > 0) {
                $userIds[$userId] = $userId;
            }
        }
        if ($userIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $authors = Database::rows(
            "SELECT u.ID, COALESCE(NULLIF(f.nombre, ''), NULLIF(u.display_name, ''), u.user_login) AS author_name
               FROM {$usersTable} u
          LEFT JOIN {$table} f ON f.id_empleado = u.ID
              WHERE u.ID IN ({$placeholders})",
            str_repeat('i', count($userIds)),
            array_values($userIds)
        );

        $authorsByUserId = [];
        foreach ($authors as $author) {
            $authorsByUserId[(int) $author['ID']] = (string) ($author['author_name'] ?? '');
        }

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['_ID']] = $authorsByUserId[(int) ($row['cct_author_id'] ?? 0)] ?? '';
        }
        return $out;
    }

    private static function estadoActor(int $entregados, int $total): string
    {
        if ($entregados > 0) return 'Activo';
        if ($total > 0) return 'Pendiente';
        return 'Inactivo';
    }

    private static function codeudorContratoLabel(array $data): string
    {
        if (!isset($data['total']) || $data['total'] === 0) {
            return '—';
        }
        if ((int) ($data['total'] ?? 0) === 1 && (int) ($data['first_contract_id'] ?? 0) > 0) {
            return 'Contrato #' . (int) $data['first_contract_id'];
        }
        return (int) ($data['total'] ?? 0) . ' contratos';
    }

    private static function calcTotalFamilia(array $row): int
    {
        $rangos = ['infancia_familia', 'ninez_familia', 'pubertad_familia', 'adolescencia_familia', 'juventud_familia', 'adulto_familia', 'adulto_mayor_familia', 'ancianidad_familia'];
        $total = 0;
        foreach ($rangos as $rango) {
            $total += (int) ($row[$rango] ?? 0);
        }
        return $total;
    }

    private static function cargosById(string $type, array $rows): array
    {
        if ($type !== 'funcionarios' || $rows === []) {
            return [];
        }

        $table = Database::table('jet_cct_cargos');
        if (!Database::tableExists($table) || !Database::columnExists($table, 'nombre_cargo')) {
            return [];
        }

        $ids = [];
        foreach ($rows as $row) {
            foreach (self::idsFromCsv((string) ($row['id_cargo'] ?? '')) as $id) {
                $ids[$id] = $id;
            }
        }
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = Database::rows(
            "SELECT _ID, nombre_cargo FROM {$table} WHERE _ID IN ({$placeholders})",
            str_repeat('i', count($ids)),
            array_values($ids)
        );

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['_ID']] = (string) ($row['nombre_cargo'] ?? '');
        }
        return $out;
    }

    private static function areasById(string $type, array $rows): array
    {
        if ($type !== 'funcionarios' || $rows === []) {
            return [];
        }

        $table = Database::table('jet_cct_areas_organizacion');
        if (!Database::tableExists($table) || !Database::columnExists($table, 'nombre')) {
            return [];
        }

        $ids = [];
        foreach ($rows as $row) {
            foreach (self::idsFromCsv((string) ($row['id_area'] ?? '')) as $id) {
                $ids[$id] = $id;
            }
        }
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = Database::rows(
            "SELECT _ID, nombre FROM {$table} WHERE _ID IN ({$placeholders})",
            str_repeat('i', count($ids)),
            array_values($ids)
        );

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['_ID']] = (string) ($row['nombre'] ?? '');
        }
        return $out;
    }

    private static function sucursalesById(string $type, array $rows): array
    {
        if ($type !== 'funcionarios' || $rows === []) {
            return [];
        }

        $table = Database::table('jet_cct_sucursales');
        if (!Database::tableExists($table) || !Database::columnExists($table, 'nombre')) {
            return [];
        }

        $ids = [];
        foreach ($rows as $row) {
            foreach (self::idsFromCsv((string) ($row['id_sucursal'] ?? '')) as $id) {
                $ids[$id] = $id;
            }
        }
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = Database::rows(
            "SELECT _ID, nombre FROM {$table} WHERE _ID IN ({$placeholders})",
            str_repeat('i', count($ids)),
            array_values($ids)
        );

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['_ID']] = (string) ($row['nombre'] ?? '');
        }
        return $out;
    }

    private static function idsFromCsv(string $value): array
    {
        $parts = preg_split('/[,\s;|]+/', trim($value)) ?: [];
        $ids = [];
        foreach ($parts as $part) {
            $id = (int) trim((string) $part);
            if ($id <= 0) {
                continue;
            }
            $ids[$id] = $id;
        }

        return array_values($ids);
    }

    private static function labelsFromCsv(string $value, array $labelsById): string
    {
        $labels = [];
        foreach (self::idsFromCsv($value) as $id) {
            $label = trim((string) ($labelsById[$id] ?? ''));
            $labels[] = $label !== '' ? $label : (string) $id;
        }

        return implode(', ', array_values(array_unique($labels)));
    }

    private static function normalizeContratoEstado(string $value): string
    {
        $value = strtolower(trim($value));
        return match ($value) {
            'si', '1', '1.0', 'true', 'activo', 'entregado' => 'Entregado',
            'no', '0', 'false', 'pendiente' => 'Pendiente',
            default => $value !== '' ? ucfirst($value) : '',
        };
    }

    private static function pickEstadoContratoColumn(array $columns): ?string
    {
        foreach (['estado', 'estado_contrato', 'contrato_entregado', 'entregado'] as $candidate) {
            if (isset($columns[$candidate])) {
                return $candidate;
            }
        }

        return null;
    }

    private static function estadoEntregadoSql(string $column): string
    {
        if ($column === 'estado' || $column === 'estado_contrato') {
            return "`{$column}` = 'Entregado'";
        }

        return "`{$column}` IN ('Si','1','1.0','true','activo','entregado')";
    }

    private static function pickLabelColumn(array $columns, array $candidates): string
    {
        foreach ($candidates as $candidate) {
            if (isset($columns[$candidate])) {
                return $candidate;
            }
        }

        return '';
    }

    public static function funcionariosListForFilter(string $type): array
    {
        if ($type !== 'contactos_funcionarios') {
            return [];
        }

        $usersTable = Database::table('users');
        $funcsTable = Database::table('jet_cct_funcionarios');
        $contactosTable = Database::table('jet_cct_contactos');
        if (!Database::tableExists($usersTable) || !Database::tableExists($contactosTable)) {
            return [];
        }

        $hasFuncs = Database::tableExists($funcsTable);
        $rows = Database::rows(
            "SELECT DISTINCT COALESCE(NULLIF(f.nombre, ''), NULLIF(u.display_name, ''), u.user_login) AS author_name
               FROM {$contactosTable} c
          LEFT JOIN {$usersTable} u ON u.ID = c.cct_author_id" .
            ($hasFuncs ? " LEFT JOIN {$funcsTable} f ON f.id_empleado = u.ID" : '') . "
              WHERE c.cct_author_id IS NOT NULL AND c.cct_author_id > 0
                AND COALESCE(NULLIF(u.display_name, ''), u.user_login) IS NOT NULL
           ORDER BY author_name ASC"
        );

        $out = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row['author_name'] ?? ''));
            if ($name !== '') {
                $out[] = $name;
            }
        }
        return $out;
    }

    public static function distinctValues(string $type, string $column, int $limit = 100): array
    {
        $config = ActorCatalog::get($type);
        if (!$config || !Database::tableExists($config['table'])) {
            return [];
        }

        $columns = Database::columns($config['table']);
        if (!isset($columns[$column])) {
            return [];
        }

        $limit = max(1, min(500, $limit));
        $rows = Database::rows(
            "SELECT DISTINCT `{$column}` AS value
               FROM {$config['table']}
              WHERE `{$column}` IS NOT NULL AND TRIM(`{$column}`) != ''
              ORDER BY `{$column}` ASC
              LIMIT {$limit}"
        );

        $out = [];
        foreach ($rows as $row) {
            $value = trim((string) ($row['value'] ?? ''));
            if ($value !== '') {
                $out[] = $value;
            }
        }
        return $out;
    }

    public static function cargosListForFilter(): array
    {
        $table = Database::table('jet_cct_cargos');
        if (!Database::tableExists($table) || !Database::columnExists($table, 'nombre_cargo')) {
            return [];
        }

        $rows = Database::rows(
            "SELECT DISTINCT nombre_cargo
               FROM {$table}
              WHERE nombre_cargo IS NOT NULL AND TRIM(nombre_cargo) != ''
              ORDER BY nombre_cargo ASC
              LIMIT 500"
        );

        $out = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row['nombre_cargo'] ?? ''));
            if ($name !== '') {
                $out[] = $name;
            }
        }
        return $out;
    }
}
