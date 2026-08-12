<?php

declare(strict_types=1);

namespace App;

final class ActivityRepository
{
    public function dashboardSummary(): array
    {
        return ReportCache::remember('dashboard_activity', [(string) app_config('db.database', '')], 60, static function (): array {
            $sessions = Database::table('jet_cct_sesiones');
            if (!Database::tableExists($sessions)) {
                return ['sessions' => []];
            }

            return [
                'sessions' => Database::one(
                    'SELECT COUNT(*) AS usuarios, COALESCE(SUM(cantidad_sesiones), 0) AS sesiones, MAX(fecha) AS ultima_fecha FROM ' . $sessions
                ),
            ];
        });
    }

    public function summary(string $from, string $to, string $actor = '', string $usuario = '', string $nombre = '', string $menu = ''): array
    {
        $sessions = Database::table('jet_cct_sesiones');
        $access = Database::table('jet_cct_accesos_portal');
        $usuario = trim($usuario);
        $nombre = trim($nombre);
        $menu = trim($menu);
        if (!Database::tableExists($sessions)) {
            return [
                'sessions' => [],
                'recent_sessions' => [],
                'by_actor' => [],
                'top_users' => [],
                'access' => [],
                'menus' => [],
                'access_by_actor' => [],
                'recent' => [],
                'actor_options' => [],
                'actor_tabs' => [],
                'selected_actor' => '',
                'selected_usuario' => $usuario,
                'selected_nombre' => $nombre,
                'selected_menu' => $menu,
                'access_table_exists' => Database::tableExists($access),
            ];
        }

        $actorOptions = $this->actorOptions($sessions, $access);
        $validActors = array_map(static fn(array $row): string => (string) $row['tipo_actor'], $actorOptions);
        if ($actor !== '' && !in_array($actor, $validActors, true)) {
            $actor = '';
        }

        [$sessionWhere, $sessionAnd, $sessionTypes, $sessionParams] = $this->personFilterSql($actor, $usuario, $nombre);
        [$fromTs, $toTs] = $this->dateRangeAsTimestamps($from, $to);
        $actorTabs = $this->actorTabs($sessions, $access, $from, $to, $fromTs, $toTs);
        $indexedTabs = [];
        foreach ($actorTabs as $tab) {
            $indexedTabs[(string) ($tab['tipo_actor'] ?? '')] = $tab;
        }
        foreach ($actorOptions as $option) {
            $optionActor = (string) ($option['tipo_actor'] ?? '');
            if ($optionActor !== '' && !isset($indexedTabs[$optionActor])) {
                $indexedTabs[$optionActor] = [
                    'tipo_actor' => $optionActor,
                    'sesiones' => 0,
                    'usuarios_recientes' => 0,
                    'accesos' => 0,
                    'usuarios_acceso' => 0,
                    'total' => 0,
                ];
            }
        }
        $actorTabs = array_values($indexedTabs);
        usort($actorTabs, static fn (array $a, array $b): int => ((int) $b['total'] <=> (int) $a['total']) ?: strcasecmp((string) $a['tipo_actor'], (string) $b['tipo_actor']));

        $data = [
            'sessions' => Database::one(
                'SELECT COUNT(*) AS usuarios, COALESCE(SUM(cantidad_sesiones), 0) AS sesiones, MAX(fecha) AS ultima_fecha FROM ' . $sessions . $sessionWhere,
                $sessionTypes,
                $sessionParams
            ),
            'recent_sessions' => Database::one(
                'SELECT COUNT(*) AS usuarios_recientes, COALESCE(SUM(cantidad_sesiones), 0) AS sesiones_acumuladas_recientes
                   FROM ' . $sessions . '
                  WHERE fecha BETWEEN ? AND ?' . $sessionAnd,
                'ii' . $sessionTypes,
                array_merge([$fromTs, $toTs], $sessionParams)
            ),
            'by_actor' => Database::rows(
                'SELECT COALESCE(NULLIF(tipo_actor, ""), "Sin clasificar") AS tipo_actor,
                        COUNT(*) AS usuarios,
                        COALESCE(SUM(cantidad_sesiones), 0) AS sesiones,
                        MAX(fecha) AS ultima_fecha
                   FROM ' . $sessions . $sessionWhere . '
                  GROUP BY COALESCE(NULLIF(tipo_actor, ""), "Sin clasificar")
                  ORDER BY sesiones DESC',
                $sessionTypes,
                $sessionParams
            ),
            'top_users' => Database::rows(
                'SELECT tipo_actor, usuario, nombre, cantidad_sesiones, fecha
                   FROM ' . $sessions . $sessionWhere . '
                  ORDER BY cantidad_sesiones DESC, fecha DESC
                  LIMIT 12',
                $sessionTypes,
                $sessionParams
            ),
            'access' => [],
            'menus' => [],
            'access_by_actor' => [],
            'recent' => [],
            'actor_options' => $actorOptions,
            'actor_tabs' => $actorTabs,
            'selected_actor' => $actor,
            'selected_usuario' => $usuario,
            'selected_nombre' => $nombre,
            'selected_menu' => $menu,
            'access_table_exists' => Database::tableExists($access),
        ];

        if (Database::tableExists($access)) {
            $conditions = [];
            $types = '';
            $params = [];
            if ($from !== '' && $to !== '') {
                $conditions[] = 'creado_en BETWEEN ? AND ?';
                $types = 'ss';
                $params = [$from . ' 00:00:00', $to . ' 23:59:59'];
            } elseif ($from !== '') {
                $conditions[] = 'creado_en >= ?';
                $types = 's';
                $params = [$from . ' 00:00:00'];
            } elseif ($to !== '') {
                $conditions[] = 'creado_en <= ?';
                $types = 's';
                $params = [$to . ' 23:59:59'];
            }
            [$accessFilterSql, $accessFilterTypes, $accessFilterParams] = $this->accessFilterConditions($actor, $usuario, $nombre, $menu);
            $conditions = array_merge($conditions, $accessFilterSql);
            $types .= $accessFilterTypes;
            $params = array_merge($params, $accessFilterParams);

            $where = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : 'WHERE 1=1';
            $data['access'] = Database::one(
                "SELECT COUNT(*) AS accesos, COUNT(DISTINCT usuario) AS usuarios_unicos, MAX(creado_en) AS ultimo_acceso
                   FROM {$access}
                  {$where}",
                $types,
                $params
            );
            $data['menus'] = Database::rows(
                "SELECT proyecto, menu, COUNT(*) AS accesos, COUNT(DISTINCT usuario) AS usuarios_unicos, MAX(creado_en) AS ultimo_acceso
                   FROM {$access}
                  {$where}
                  GROUP BY proyecto, menu
                  ORDER BY accesos DESC
                  LIMIT 20",
                $types,
                $params
            );
            $data['access_by_actor'] = Database::rows(
                "SELECT COALESCE(NULLIF(tipo_actor, ''), 'Sin clasificar') AS tipo_actor,
                        COUNT(*) AS accesos,
                        COUNT(DISTINCT usuario) AS usuarios_unicos,
                        MAX(creado_en) AS ultimo_acceso
                   FROM {$access}
                  {$where}
                  GROUP BY COALESCE(NULLIF(tipo_actor, ''), 'Sin clasificar')
                  ORDER BY accesos DESC",
                $types,
                $params
            );
            $data['recent'] = Database::rows(
                "SELECT creado_en, proyecto, tipo_actor, usuario, nombre, menu, ruta, ip
                   FROM {$access}
                  {$where}
                  ORDER BY creado_en DESC
                  LIMIT 30",
                $types,
                $params
            );
        }

        return $data;
    }

    private function actorOptions(string $sessions, string $access): array
    {
        $sql = 'SELECT DISTINCT COALESCE(NULLIF(tipo_actor, ""), "Sin clasificar") AS tipo_actor FROM ' . $sessions;
        if (Database::tableExists($access)) {
            $sql .= ' UNION SELECT DISTINCT COALESCE(NULLIF(tipo_actor, ""), "Sin clasificar") AS tipo_actor FROM ' . $access;
        }

        return Database::rows('SELECT tipo_actor FROM (' . $sql . ') actor_list ORDER BY tipo_actor ASC');
    }

    private function personFilterSql(string $actor, string $usuario, string $nombre): array
    {
        [$conditions, $types, $params] = $this->personFilterConditions($actor, $usuario, $nombre);

        return [
            $conditions !== [] ? ' WHERE ' . implode(' AND ', $conditions) : '',
            $conditions !== [] ? ' AND ' . implode(' AND ', $conditions) : '',
            $types,
            $params,
        ];
    }

    private function personFilterConditions(string $actor, string $usuario, string $nombre): array
    {
        $conditions = [];
        $types = '';
        $params = [];

        if ($actor !== '') {
            $conditions[] = 'COALESCE(NULLIF(tipo_actor, ""), "Sin clasificar") = ?';
            $types .= 's';
            $params[] = $actor;
        }

        if ($usuario !== '') {
            $conditions[] = 'usuario LIKE ?';
            $types .= 's';
            $params[] = '%' . $usuario . '%';
        }

        if ($nombre !== '') {
            $conditions[] = 'nombre LIKE ?';
            $types .= 's';
            $params[] = '%' . $nombre . '%';
        }

        return [$conditions, $types, $params];
    }

    private function accessFilterConditions(string $actor, string $usuario, string $nombre, string $menu): array
    {
        [$conditions, $types, $params] = $this->personFilterConditions($actor, $usuario, $nombre);

        if ($menu !== '') {
            $conditions[] = 'menu LIKE ?';
            $types .= 's';
            $params[] = '%' . $menu . '%';
        }

        return [$conditions, $types, $params];
    }

    private function actorTabs(string $sessions, string $access, string $from, string $to, int $fromTs, int $toTs, string $usuario = '', string $nombre = '', string $menu = ''): array
    {
        $items = [];
        $ensure = static function (array &$items, string $actor): void {
            if (!isset($items[$actor])) {
                $items[$actor] = [
                    'tipo_actor' => $actor,
                    'sesiones' => 0,
                    'usuarios_recientes' => 0,
                    'accesos' => 0,
                    'usuarios_acceso' => 0,
                    'total' => 0,
                ];
            }
        };

        [$tabPersonConditions, $tabPersonTypes, $tabPersonParams] = $this->personFilterConditions('', $usuario, $nombre);
        $tabPersonAnd = $tabPersonConditions !== [] ? ' AND ' . implode(' AND ', $tabPersonConditions) : '';

        if ($menu === '') {
            foreach (Database::rows(
                'SELECT COALESCE(NULLIF(tipo_actor, ""), "Sin clasificar") AS tipo_actor,
                        COUNT(*) AS usuarios_recientes,
                        COALESCE(SUM(cantidad_sesiones), 0) AS sesiones
                   FROM ' . $sessions . '
                  WHERE fecha BETWEEN ? AND ?' . $tabPersonAnd . '
                  GROUP BY COALESCE(NULLIF(tipo_actor, ""), "Sin clasificar")',
                'ii' . $tabPersonTypes,
                array_merge([$fromTs, $toTs], $tabPersonParams)
            ) as $row) {
                $actor = (string) ($row['tipo_actor'] ?? 'Sin clasificar');
                $ensure($items, $actor);
                $items[$actor]['usuarios_recientes'] = (int) ($row['usuarios_recientes'] ?? 0);
                $items[$actor]['sesiones'] = (int) ($row['sesiones'] ?? 0);
            }
        }

        if (Database::tableExists($access)) {
            $dateWhere = '';
            $types = '';
            $params = [];
            if ($from !== '' && $to !== '') {
                $dateWhere = 'WHERE creado_en BETWEEN ? AND ?';
                $types = 'ss';
                $params = [$from . ' 00:00:00', $to . ' 23:59:59'];
            } elseif ($from !== '') {
                $dateWhere = 'WHERE creado_en >= ?';
                $types = 's';
                $params = [$from . ' 00:00:00'];
            } elseif ($to !== '') {
                $dateWhere = 'WHERE creado_en <= ?';
                $types = 's';
                $params = [$to . ' 23:59:59'];
            } else {
                $dateWhere = 'WHERE 1=1';
            }

            [$accessTabConditions, $accessTabTypes, $accessTabParams] = $this->accessFilterConditions('', $usuario, $nombre, $menu);
            $accessPersonWhere = $accessTabConditions !== [] ? ' AND ' . implode(' AND ', $accessTabConditions) : '';
            $types .= $accessTabTypes;
            $params = array_merge($params, $accessTabParams);

            foreach (Database::rows(
                "SELECT COALESCE(NULLIF(tipo_actor, ''), 'Sin clasificar') AS tipo_actor,
                        COUNT(*) AS accesos,
                        COUNT(DISTINCT usuario) AS usuarios_acceso
                   FROM {$access}
                  {$dateWhere}{$accessPersonWhere}
                  GROUP BY COALESCE(NULLIF(tipo_actor, ''), 'Sin clasificar')",
                $types,
                $params
            ) as $row) {
                $actor = (string) ($row['tipo_actor'] ?? 'Sin clasificar');
                $ensure($items, $actor);
                $items[$actor]['accesos'] = (int) ($row['accesos'] ?? 0);
                $items[$actor]['usuarios_acceso'] = (int) ($row['usuarios_acceso'] ?? 0);
            }
        }

        foreach ($items as &$item) {
            $item['total'] = (int) $item['sesiones'] + (int) $item['accesos'];
        }
        unset($item);

        uasort($items, static fn (array $a, array $b): int => ((int) $b['total'] <=> (int) $a['total']) ?: strcasecmp((string) $a['tipo_actor'], (string) $b['tipo_actor']));

        return array_values($items);
    }

    private function dateRangeAsTimestamps(string $from, string $to): array
    {
        $defaultFrom = date('Y-m-d', strtotime('-30 days'));
        $today = date('Y-m-d');
        $fromDate = $from !== '' ? $from : $defaultFrom;
        $toDate = $to !== '' ? $to : $today;

        return [
            strtotime($fromDate . ' 00:00:00') ?: strtotime($defaultFrom . ' 00:00:00'),
            strtotime($toDate . ' 23:59:59') ?: strtotime($today . ' 23:59:59'),
        ];
    }
}
