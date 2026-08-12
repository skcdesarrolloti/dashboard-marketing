<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

final class ActorRepository
{
    private array $stateIds = [];
    public function paginate(string $type, string $search = '', int $page = 1, int $perPage = 20, array $filters = []): array
    {
        $config = ActorCatalog::get($type);
        if (!$config || !Database::tableExists($config['table'])) {
            return ['items' => [], 'total' => 0, 'pages' => 1];
        }

        $columns = Database::columns($config['table']);
        $vista = array_values(array_filter($config['vista'] ?? [], static function (string $column) use ($columns): bool {
            if (str_starts_with($column, 'v_') || in_array($column, ['author_name', 'total_familia', 'cargo_nombre'], true)) {
                return true;
            }
            return isset($columns[$column]);
        }));
        $select = ['_ID'];
        foreach ($vista as $column) {
            if (isset($columns[$column])) {
                $select[] = $column;
            }
        }
        if (isset($columns['cct_author_id'])) {
            $select[] = 'cct_author_id';
        }
        if ($type === 'funcionarios' && in_array('cargo_nombre', $vista, true) && isset($columns['id_cargo'])) {
            $select[] = 'id_cargo';
        }
        $select = array_values(array_unique($select));
        [$where, $types, $params] = $this->whereFromSearch($type, $config, $columns, $search, $filters);

        $total = (int) Database::value("SELECT COUNT(*) FROM {$config['table']} WHERE {$where}", $types, $params);
        $offset = max(0, ($page - 1) * $perPage);
        $sql = 'SELECT `' . implode('`,`', $select) . "` FROM {$config['table']} WHERE {$where} ORDER BY _ID DESC LIMIT ? OFFSET ?";
        $items = Database::rows($sql, $types . 'ii', array_merge($params, [$perPage, $offset]));

        $items = ActorColumnResolver::enrich($type, $items);

        return [
            'items' => $items,
            'total' => $total,
            'pages' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    public function ids(string $type, string $search = '', array $filters = [], int $limit = 5000): array
    {
        $config = ActorCatalog::get($type);
        if (!$config || !Database::tableExists($config['table'])) {
            return [];
        }

        $columns = Database::columns($config['table']);
        [$where, $types, $params] = $this->whereFromSearch($type, $config, $columns, $search, $filters);
        $rows = Database::rows(
            "SELECT _ID FROM {$config['table']} WHERE {$where} ORDER BY _ID DESC LIMIT ?",
            $types . 'i',
            array_merge($params, [$limit])
        );

        return array_map(static fn (array $row): int => (int) $row['_ID'], $rows);
    }

    public function find(string $type, int $id): array
    {
        $config = ActorCatalog::get($type);
        if (!$config || !Database::tableExists($config['table'])) {
            return [];
        }

        if ($type === 'contactos' && Database::columnExists($config['table'], 'cct_author_id')) {
            $row = Database::one("SELECT * FROM {$config['table']} WHERE _ID=? AND cct_author_id=? LIMIT 1", 'ii', [$id, Auth::authorId()]);
        } else {
            $row = Database::one("SELECT * FROM {$config['table']} WHERE _ID = ? LIMIT 1", 'i', [$id]);
        }
        return ActorColumnResolver::resolveOne($type, $row);
    }

    public function save(string $type, array $input): int
    {
        $config = ActorCatalog::get($type);
        if (!$config || !Database::tableExists($config['table'])) {
            return 0;
        }

        $columns = Database::columns($config['table']);
        $user = Auth::user();
        $userId = Auth::authorId();
        $id = (int) ($input['_ID'] ?? 0);
        $existingAuthor = 0;

        if ($id > 0 && isset($columns['cct_author_id'])) {
            $existingAuthor = (int) Database::value("SELECT cct_author_id FROM {$config['table']} WHERE _ID = ?", 'i', [$id]);
        }

        if ($id > 0) {
            if (!PermissionService::canEdit($type, $id, $existingAuthor)) {
                throw new RuntimeException('No tienes permisos para editar este registro.');
            }
        }

        $preferencesOnly = $id > 0 && !PermissionService::canFullEdit($type, $id, $existingAuthor);
        $preferenceFieldIds = $preferencesOnly ? ActorPreferences::fieldIds() : [];
        $data = [];
        foreach (($config['campos'] ?? []) as $field) {
            $idField = $field['id'] ?? null;
            $fieldType = (string) ($field['type'] ?? '');
            if (!$idField || in_array($fieldType, ['header', 'subheader'], true)) {
                continue;
            }
            if ($preferencesOnly && !isset($preferenceFieldIds[$idField])) {
                continue;
            }
            if (!isset($columns[$idField])) {
                continue;
            }
            $value = $input[$idField] ?? null;
            if ($fieldType === 'multiselect') {
                $rawValues = is_array($value) ? $value : (($value === null || $value === '') ? [] : explode(',', (string) $value));
                $cleanValues = [];
                foreach ($rawValues as $rawValue) {
                    $cleanValue = trim((string) $rawValue);
                    if ($cleanValue === '') {
                        continue;
                    }
                    $cleanValues[$cleanValue] = $cleanValue;
                }
                $data[$idField] = implode(',', array_values($cleanValues));
            } elseif ($fieldType === 'checkbox') {
                $data[$idField] = (int) ($value === '1' || $value === 'on' || $value === 1 || $value === true) ? 1 : 0;
            } elseif ($value !== null) {
                $cleanValue = is_string($value) ? trim($value) : (string) $value;
                if ($idField === 'celular' || $idField === 'celular_proveedor') {
                    $cleanValue = ActorPhone::clean($cleanValue);
                }
                $data[$idField] = $cleanValue;
            }
        }

        foreach (($config['fields'] ?? []) as $field => $_label) {
            if ($preferencesOnly) {
                continue;
            }
            if (isset($columns[$field]) && !isset($data[$field]) && array_key_exists($field, $input)) {
                $value = $input[$field];
                $data[$field] = in_array($field, ['celular', 'celular_proveedor'], true) ? ActorPhone::clean((string) $value) : trim((string) $value);
            }
        }

        if ($type === 'contactos' && $id <= 0) {
            $cel = (string) ($data['celular'] ?? '');
            $mail = (string) ($data['correo'] ?? '');
            if ($cel !== '' && $this->duplicadoContacto($userId, $cel, $mail)) {
                throw new RuntimeException('Este contacto ya existe en tu lista (celular o correo duplicado).');
            }
            $data['cct_author_id'] = $userId;
            $data['id_dueno'] = $userId;
            $data['tipo_actor'] = 'Contacto';
        }

        if (!empty($config['force_tipo_actor'])) {
            $data['tipo_actor'] = $config['force_tipo_actor'];
        }

        if ($type === 'club_pph' && $id > 0) {
            $motivo = trim((string) ($input['club_pph_edit_reason'] ?? ''));
            if ($motivo === '') {
                throw new RuntimeException('Debes seleccionar motivo de edición.');
            }
        }

        if ($type === 'club_pph' && !empty($config['auto_fecha_fondo'])) {
            $pertenece = strtolower(trim((string) ($data['pertenece_fondo'] ?? '')));
            $pertenece = str_replace(['í', 'Í'], 'i', $pertenece);
            if (in_array($pertenece, ['si', '1', 'true'], true) && Database::columnExists($config['table'], 'fecha_fondo')) {
                $currentFecha = '';
                if ($id > 0) {
                    $currentFecha = (string) Database::value("SELECT fecha_fondo FROM {$config['table']} WHERE _ID = ?", 'i', [$id]);
                }
                $postedFecha = trim((string) ($data['fecha_fondo'] ?? ''));
                if ($currentFecha === '' && $postedFecha === '') {
                    $data['fecha_fondo'] = gmdate('Y-m-d H:i:s');
                }
            }
        }

        if ($type === 'club_pph') {
            $rangos = ['infancia_familia', 'ninez_familia', 'pubertad_familia', 'adolescencia_familia', 'juventud_familia', 'adulto_familia', 'adulto_mayor_familia', 'ancianidad_familia'];
            $totalFamilia = 0;
            foreach ($rangos as $rango) {
                $totalFamilia += (int) ($data[$rango] ?? 0);
            }
            if (Database::columnExists($config['table'], 'total_familia')) {
                $data['total_familia'] = $totalFamilia;
            }
        }

        $now = gmdate('Y-m-d H:i:s');
        $data['cct_modified'] = $now;

        if ($id > 0) {
            Database::update($config['table'], $data, $id);
            ActorSync::afterSave($type, $id, $data);
            return $id;
        }

        $data['cct_status'] = 'publish';
        $data['cct_created'] = $now;
        if (!isset($data['cct_author_id'])) {
            $data['cct_author_id'] = $userId;
        }

        $newId = Database::insert($config['table'], $data);
        ActorSync::afterSave($type, $newId, $data);
        return $newId;
    }

    public function delete(string $type, int $id): bool
    {
        $config = ActorCatalog::get($type);
        if (!$config || !Database::tableExists($config['table'])) {
            return false;
        }

        $authorId = (int) Database::value("SELECT cct_author_id FROM {$config['table']} WHERE _ID = ?", 'i', [$id]);
        if (!PermissionService::canDelete($type, $id, $authorId)) {
            return false;
        }

        return Database::execute("DELETE FROM {$config['table']} WHERE _ID = ? LIMIT 1", 'i', [$id]);
    }

    public function cleanPhones(string $type): int
    {
        $config = ActorCatalog::get($type);
        if (!$config || !Database::tableExists($config['table'])) {
            return 0;
        }

        $columns = Database::columns($config['table']);
        $phoneColumn = $config['phone'] ?? '';
        if ($phoneColumn === '' || !isset($columns[$phoneColumn])) {
            return 0;
        }

        $ownerWhere = $type === 'contactos' && isset($columns['cct_author_id']) ? ' AND cct_author_id = ?' : '';
        $rows = Database::rows("SELECT _ID, `{$phoneColumn}` AS phone FROM {$config['table']} WHERE `{$phoneColumn}` IS NOT NULL AND `{$phoneColumn}` != ''{$ownerWhere}", $ownerWhere ? 'i' : '', $ownerWhere ? [Auth::authorId()] : []);
        $updated = 0;
        foreach ($rows as $row) {
            $clean = ActorPhone::clean((string) ($row['phone'] ?? ''));
            if ($clean === (string) ($row['phone'] ?? '')) {
                continue;
            }
            if (Database::update($config['table'], [$phoneColumn => $clean], (int) $row['_ID'])) {
                $updated++;
            }
        }

        return $updated;
    }

    public function recipients(string $type, array $ids, string $channel): array
    {
        $config = ActorCatalog::get($type);
        if (!$config || !$ids || !Database::tableExists($config['table'])) {
            return [];
        }

        $contactColumn = in_array($channel, ['sms', 'whatsapp'], true) ? $config['phone'] : $config['email'];
        $columns = Database::columns($config['table']);
        if (!isset($columns[$contactColumn])) {
            return [];
        }

        $ids = array_values(array_unique(array_map('intval', $ids)));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $select = ['*'];
        $contactExpr = "`{$contactColumn}`";
        if (($channel === 'sms' || $channel === 'whatsapp') && isset($columns['indicativo'])) {
            $prefix = "REPLACE(REPLACE(REPLACE(COALESCE(`indicativo`, ''), '+', ''), '-', ''), ' ', '')";
            $number = "REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(`{$contactColumn}`, ''), '+', ''), '-', ''), ' ', ''), '(', '')";
            $contactExpr = "CASE WHEN {$prefix} != '' THEN CONCAT('+', {$prefix}, {$number}) ELSE {$number} END";
        }
        $select[] = "{$contactExpr} AS contacto";
        $ownerWhere = $type === 'contactos' && isset($columns['cct_author_id']) ? ' AND cct_author_id = ?' : '';
        $sql = 'SELECT ' . implode(', ', $select) . " FROM {$config['table']} WHERE _ID IN ({$placeholders}){$ownerWhere}";

        return Database::rows($sql, str_repeat('i', count($ids)) . ($ownerWhere ? 'i' : ''), $ownerWhere ? array_merge($ids, [Auth::authorId()]) : $ids);
    }

    public function existsDuplicadoContacto(int $userId, string $celular, string $correo): bool
    {
        return $this->duplicadoContacto($userId, $celular, $correo);
    }

    private function duplicadoContacto(int $userId, string $celular, string $correo): bool
    {
        $table = Database::table('jet_cct_contactos');
        if (!Database::tableExists($table)) {
            return false;
        }
        $conditions = [];
        $params = [$userId];
        $types = 'i';
        if ($celular !== '') {
            $conditions[] = 'celular = ?';
            $params[] = $celular;
            $types .= 's';
        }
        if ($correo !== '') {
            $conditions[] = 'correo = ?';
            $params[] = $correo;
            $types .= 's';
        }
        if ($conditions === []) return false;
        $where = 'cct_author_id = ? AND (' . implode(' OR ', $conditions) . ')';
        $row = Database::one("SELECT _ID FROM {$table} WHERE {$where} LIMIT 1", $types, $params);
        return (bool) $row;
    }

    private function whereFromSearch(string $type, array $config, array $columns, string $search, array $filters): array
    {
        $where = '1=1';
        $params = [];
        $types = '';

        if (!empty($config['restrict_by_author'])) {
            $userId = Auth::authorId();
            if ($userId > 0 && Database::columnExists($config['table'], 'cct_author_id')) {
                $where .= ' AND cct_author_id = ?';
                $params[] = $userId;
                $types .= 'i';
            }
        }

        if (!empty($config['force_tipo_actor']) && isset($columns['tipo_actor'])) {
            $where .= ' AND tipo_actor = ?';
            $params[] = (string) $config['force_tipo_actor'];
            $types .= 's';
        }

        $searchColumns = array_values(array_filter(
            $config['vista'] ?? [],
            static fn ($c) => !str_starts_with((string) $c, 'v_') && $c !== 'author_name'
        ));
        $searchColumns = array_values(array_filter($searchColumns, static fn ($c) => isset($columns[$c])));

        if ($search !== '' && $searchColumns !== []) {
            $whereParts = [];
            foreach ($searchColumns as $column) {
                $whereParts[] = "`{$column}` LIKE ?";
                $params[] = '%' . $search . '%';
                $types .= 's';
            }
            $where .= ' AND (' . implode(' OR ', $whereParts) . ')';
        }

        foreach ($filters as $column => $value) {
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }

            if ($column === 'v_estado') {
                [$stateWhere, $stateTypes, $stateParams] = $this->estadoFilterClause($type, $config['table'], $value);
                if ($stateWhere !== '') {
                    $where .= ' AND ' . $stateWhere;
                    $types .= $stateTypes;
                    $params = array_merge($params, $stateParams);
                }
                continue;
            }

            if ($column === 'campaign_tag' || $column === 'campaign_delivery' || $column === 'campaign_channel') {
                continue;
            }

            if ($column === 'fecha' && in_array($type, ['suscriptores', 'club_pph'], true) && isset($columns['fecha'])) {
                [$dateWhere, $dateTypes, $dateParams] = $this->unixDateRangeFilterClause($value);
                if ($dateWhere !== '') {
                    $where .= ' AND ' . $dateWhere;
                    $types .= $dateTypes;
                    $params = array_merge($params, $dateParams);
                }
                continue;
            }

            if ($column === 'author_name' && $type === 'contactos_funcionarios' && isset($columns['cct_author_id'])) {
                $usersTable = Database::table('users');
                $funcsTable = Database::table('jet_cct_funcionarios');
                if (Database::tableExists($usersTable)) {
                    $like = '%' . $value . '%';
                    $subquery = "(SELECT u.ID FROM {$usersTable} u
                              LEFT JOIN {$funcsTable} f ON f.id_empleado = u.ID
                                  WHERE COALESCE(NULLIF(f.nombre, ''), NULLIF(u.display_name, ''), u.user_login) LIKE ?)";
                    $where .= " AND cct_author_id IN {$subquery}";
                    $params[] = $like;
                    $types .= 's';
                }
                continue;
            }

            if ($column === 'cargo_nombre' && $type === 'funcionarios' && isset($columns['id_cargo'])) {
                $cargosTable = Database::table('jet_cct_cargos');
                if (Database::tableExists($cargosTable) && Database::columnExists($cargosTable, 'nombre_cargo')) {
                    $cargoRows = Database::rows(
                        "SELECT _ID FROM {$cargosTable} WHERE nombre_cargo LIKE ? LIMIT 500",
                        's',
                        ['%' . $value . '%']
                    );
                    $cargoIds = [];
                    foreach ($cargoRows as $cargoRow) {
                        $cargoId = (int) ($cargoRow['_ID'] ?? 0);
                        if ($cargoId > 0) {
                            $cargoIds[$cargoId] = $cargoId;
                        }
                    }

                    if ($cargoIds === []) {
                        $where .= ' AND 1=0';
                    } else {
                        $parts = [];
                        foreach (array_values($cargoIds) as $cargoId) {
                            $parts[] = "FIND_IN_SET(?, REPLACE(REPLACE(REPLACE(COALESCE(id_cargo, ''), ' ', ''), ';', ','), '|', ','))";
                            $params[] = $cargoId;
                            $types .= 'i';
                        }
                        $where .= ' AND (' . implode(' OR ', $parts) . ')';
                    }
                }
                continue;
            }

            if (!isset($columns[$column])) {
                continue;
            }
            $where .= " AND `{$column}` LIKE ?";
            $params[] = '%' . $value . '%';
            $types .= 's';
        }

        $campaignTag = trim((string) ($filters['campaign_tag'] ?? ''));
        $campaignChannel = trim((string) ($filters['campaign_channel'] ?? ''));
        $campaignDelivery = trim((string) ($filters['campaign_delivery'] ?? ''));
        if ($campaignTag !== '') {
            if ($campaignDelivery === '') $campaignDelivery = 'exclude_any';
            [$campaignWhere, $campaignTypes, $campaignParams] = $this->campaignFilterClause($type, $config['table'], $campaignTag, $campaignDelivery, $campaignChannel);
            if ($campaignWhere !== '') {
                $where .= ' AND ' . $campaignWhere;
                $types .= $campaignTypes;
                $params = array_merge($params, $campaignParams);
            }
        }

        return [$where, $types, $params];
    }

    private function unixDateRangeFilterClause(string $value): array
    {
        preg_match_all('/\d{4}-\d{2}-\d{2}/', $value, $matches);
        $dates = $matches[0] ?? [];
        if ($dates === []) {
            return ['', '', []];
        }

        $from = $this->dateBoundaryTimestamp($dates[0], false);
        $to = $this->dateBoundaryTimestamp($dates[1] ?? $dates[0], true);
        if ($from === null || $to === null) {
            return ['', '', []];
        }

        if ($from > $to) {
            [$from, $to] = [$this->dateBoundaryTimestamp($dates[1] ?? $dates[0], false), $this->dateBoundaryTimestamp($dates[0], true)];
            if ($from === null || $to === null) {
                return ['', '', []];
            }
        }

        return ['`fecha` BETWEEN ? AND ?', 'ii', [$from, $to]];
    }

    private function dateBoundaryTimestamp(string $date, bool $endOfDay): ?int
    {
        $timezone = new \DateTimeZone('America/Bogota');
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, $timezone);
        if (!$parsed || $parsed->format('Y-m-d') !== $date) {
            return null;
        }

        if ($endOfDay) {
            $parsed = $parsed->setTime(23, 59, 59);
        }

        return (int) $parsed->format('U');
    }

    private function estadoFilterClause(string $type, string $outerTable, string $value): array
    {
        if (!in_array($type, ['propietarios', 'arrendatarios'], true)) {
            return ['', '', []];
        }

        $contratos = Database::table('jet_cct_contratos_arrendamiento');
        if (!Database::tableExists($contratos)) {
            return ['', '', []];
        }

        $columns = Database::columns($contratos);
        $linkColumn = $type === 'propietarios' ? 'id_propietario' : 'id_arrendatario';
        if (!isset($columns[$linkColumn])) {
            return ['', '', []];
        }

        $normalized = strtolower(trim($value));
        if (!in_array($normalized, ['activo', 'pendiente', 'inactivo'], true)) return ['', '', []];

        $entregadoColumn = $this->pickEstadoContratoColumn($columns);
        $activeSelect = $entregadoColumn !== ''
            ? ', SUM(CASE WHEN ' . $this->estadoEntregadoSql($entregadoColumn) . ' THEN 1 ELSE 0 END) AS activos'
            : ', 0 AS activos';
        if (!isset($this->stateIds[$type])) {
            $rows = Database::rows(
                "SELECT `{$linkColumn}` AS actor_id{$activeSelect}
                   FROM {$contratos} c
                  WHERE `{$linkColumn}` IS NOT NULL AND `{$linkColumn}`!=''
                  GROUP BY `{$linkColumn}`"
            );
            $allIds = [];
            $activeIds = [];
            foreach ($rows as $row) {
                $id = (int) ($row['actor_id'] ?? 0);
                if ($id <= 0) continue;
                $allIds[$id] = true;
                if ((int) ($row['activos'] ?? 0) > 0) $activeIds[$id] = true;
            }
            $this->stateIds[$type] = ['all' => $allIds, 'active' => $activeIds];
        }
        $allIds = $this->stateIds[$type]['all'];
        $activeIds = $this->stateIds[$type]['active'];
        $pendingIds = array_diff_key($allIds, $activeIds);
        $ids = $normalized === 'activo' ? array_keys($activeIds) : array_keys($pendingIds);

        if ($normalized === 'inactivo') {
            return [$allIds === [] ? '1=1' : "{$outerTable}._ID NOT IN (" . implode(',', array_keys($allIds)) . ')', '', []];
        }
        return [$ids === [] ? '1=0' : "{$outerTable}._ID IN (" . implode(',', $ids) . ')', '', []];
    }

    private function campaignFilterClause(string $type, string $outerTable, string $campaignTag, string $delivery, string $channel = ''): array
    {
        $hasQueue = Database::tableExists('skc_notification_queue');
        if (!$hasQueue) {
            return ['', '', []];
        }

        $statusClause = '';
        $delivery = strtolower(trim($delivery));
        $channel = strtolower(trim($channel));
        $channelClause = '';
        $queryTypes = 'ss';
        $queryParams = [$type, $campaignTag];

        if ($delivery === 'pending') {
            $statusClause = " AND skc_notification_queue.status IN ('pending', 'processing')";
        } elseif ($delivery === 'sent') {
            $statusClause = " AND skc_notification_queue.status = 'sent'";
        } elseif ($delivery === 'failed') {
            $statusClause = " AND skc_notification_queue.status = 'failed'";
        }
        if (in_array($channel, ['email', 'sms', 'whatsapp'], true)) {
            $channelClause = " AND skc_notification_queue.channel = ?";
            $queryTypes .= 's';
            $queryParams[] = $channel;
        }

        $ids = [];
        if ($hasQueue) {
            $rows = Database::rows(
                "SELECT DISTINCT gda_id_actor AS actor_id
                   FROM skc_notification_queue
                  WHERE project_code='gestor-actores'
                    AND gda_tipo_actor=?
                    AND gda_campaign_tag=?{$statusClause}{$channelClause}",
                $queryTypes,
                $queryParams
            );
            foreach ($rows as $row) {
                $id = (int) ($row['actor_id'] ?? 0);
                if ($id > 0) {
                    $ids[$id] = $id;
                }
            }
        }

        $ids = array_values($ids);

        if ($delivery === 'exclude_any') {
            return [$ids === [] ? '1=1' : "{$outerTable}._ID NOT IN (" . implode(',', $ids) . ')', '', []];
        }

        return [$ids === [] ? '1=0' : "{$outerTable}._ID IN (" . implode(',', $ids) . ')', '', []];
    }

    private function pickEstadoContratoColumn(array $columns): string
    {
        foreach (['estado', 'estado_contrato', 'contrato_entregado', 'entregado'] as $candidate) {
            if (isset($columns[$candidate])) {
                return $candidate;
            }
        }

        return '';
    }

    private function estadoEntregadoSql(string $column): string
    {
        if ($column === 'estado' || $column === 'estado_contrato') {
            return "c.`{$column}` = 'Entregado'";
        }

        return "c.`{$column}` IN ('Si','1','1.0','true','activo','entregado')";
    }
}
