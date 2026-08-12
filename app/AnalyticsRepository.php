<?php

declare(strict_types=1);

namespace App;

final class AnalyticsRepository
{
    public function filterOptions(): array
    {
        $table = Database::table('jet_cct_tracker_logs');
        return Database::tableExists($table) ? $this->options($table) : [];
    }

    public function mergeCampaign(string $from, string $to): array
    {
        $from = trim($from);
        $to = trim($to);
        $table = Database::table('jet_cct_tracker_logs');
        $result = ['status' => 'invalid', 'updated' => 0, 'from' => $from, 'to' => $to];

        if ($from === '' || $to === '' || $from === $to || !Database::tableExists($table)) {
            return $from !== '' && $to !== '' && $from === $to
                ? array_merge($result, ['status' => 'same'])
                : $result;
        }

        $existing = (int) Database::value("SELECT COUNT(*) FROM {$table} WHERE camp_slug = ?", 's', [$from]);
        if ($existing <= 0) {
            return array_merge($result, ['status' => 'not_found']);
        }

        $updated = Database::execute("UPDATE {$table} SET camp_slug = ? WHERE camp_slug = ?", 'ss', [$to, $from]);
        $affected = $updated ? $existing : 0;
        $this->mergeCampaignCatalog($from, $to, $affected);
        $this->clearOptionsCache($table);
        ReportCache::forget('analytics_report');
        ReportCache::forget('dashboard_analytics');

        return ['status' => $affected > 0 ? 'updated' : 'unchanged', 'updated' => $affected, 'from' => $from, 'to' => $to];
    }

    public function summary(string $from, string $to, array $filters = [], string $tab = 'general', int $page = 1, bool $loadAllTabs = false): array
    {
        ksort($filters);
        return ReportCache::remember(
            'analytics_report',
            [(string) app_config('db.database', ''), $from, $to, $filters, $tab, $page, $loadAllTabs],
            60,
            fn (): array => $this->buildSummary($from, $to, $filters, $tab, $page, $loadAllTabs)
        );
    }

    public function dashboardSummary(string $from, string $to): array
    {
        return ReportCache::remember('dashboard_analytics', [(string) app_config('db.database', ''), $from, $to], 60, function () use ($from, $to): array {
            $table = Database::table('jet_cct_tracker_logs');
            if (!Database::tableExists($table)) {
                return ['totals' => [], 'sources' => []];
            }

            [$where, $types, $params] = $this->where($from, $to, []);
            $queries = [
                "SELECT 'totals' AS dataset, '' AS label, COUNT(*) AS hits, COUNT(DISTINCT ip_address) AS unique_ips FROM {$table} WHERE {$where}",
                "SELECT 'sources' AS dataset, COALESCE(NULLIF(utm_source, ''), 'Directo') AS label, COUNT(*) AS hits, 0 AS unique_ips FROM {$table} WHERE {$where} GROUP BY label ORDER BY hits DESC LIMIT 8",
            ];
            $rows = Database::rows(
                '(' . implode(') UNION ALL (', $queries) . ')',
                $types . $types,
                array_merge($params, $params)
            );
            $data = ['totals' => [], 'sources' => []];
            foreach ($rows as $row) {
                if (($row['dataset'] ?? '') === 'totals') {
                    $data['totals'] = [
                        'hits' => (int) ($row['hits'] ?? 0),
                        'unique_ips' => (int) ($row['unique_ips'] ?? 0),
                    ];
                    continue;
                }
                $data['sources'][] = [
                    'source' => (string) ($row['label'] ?? ''),
                    'hits' => (int) ($row['hits'] ?? 0),
                ];
            }

            return $data;
        });
    }

    private function buildSummary(string $from, string $to, array $filters = [], string $tab = 'general', int $page = 1, bool $loadAllTabs = false): array
    {
        $table = Database::table('jet_cct_tracker_logs');
        if (!Database::tableExists($table)) {
            return [
                'totals' => [],
                'sources' => [],
                'mediums' => [],
                'countries' => [],
                'cities' => [],
                'campaigns' => [],
                'daily' => [],
                'event_types' => [],
                'pages' => [],
                'referrers' => [],
                'campaign_sources' => [],
                'campaign_events' => [],
                'campaign_properties' => [],
                'campaign_locations' => [],
                'properties' => [],
                'events' => [],
                'logs' => [],
                'options' => [],
                'pagination' => ['page' => 1, 'pages' => 1, 'total' => 0],
                'propertyPagination' => ['page' => 1, 'pages' => 1, 'total' => 0],
                'logPagination' => ['page' => 1, 'pages' => 1, 'total' => 0],
            ];
        }

        [$where, $types, $params] = $this->where($from, $to, $filters);
        $perPage = 25;
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $data = [
            'totals' => Database::one(
                "SELECT COUNT(*) AS hits,
                        COUNT(DISTINCT ip_address) AS unique_ips,
                        SUM(CASE WHEN DATE(fecha_visita) = CURDATE() THEN 1 ELSE 0 END) AS today_hits,
                        SUM(CASE WHEN event_type = 'event' THEN 1 ELSE 0 END) AS event_hits,
                        SUM(CASE WHEN event_type = 'view' THEN 1 ELSE 0 END) AS view_hits,
                        SUM(CASE WHEN user_id > 0 THEN 1 ELSE 0 END) AS registered_hits,
                        SUM(CASE WHEN user_id = 0 THEN 1 ELSE 0 END) AS guest_hits,
                        COUNT(DISTINCT CASE WHEN user_id > 0 THEN user_id END) AS registered_users,
                        COUNT(DISTINCT CASE WHEN user_id = 0 THEN ip_address END) AS guest_users,
                        COUNT(DISTINCT camp_slug) AS campaign_count
                   FROM {$table}
                  WHERE {$where}",
                $types,
                $params
            ),
            'sources' => [],
            'mediums' => [],
            'countries' => [],
            'cities' => [],
            'campaigns' => [],
            'daily' => [],
            'event_types' => [],
            'pages' => [],
            'referrers' => [],
            'campaign_sources' => [],
            'campaign_events' => [],
            'campaign_properties' => [],
            'campaign_locations' => [],
            'options' => $this->options($table),
            'properties' => [],
            'events' => [],
            'logs' => [],
            'pagination' => ['page' => $page, 'pages' => 1, 'total' => 0],
            'propertyPagination' => ['page' => $page, 'pages' => 1, 'total' => 0],
            'logPagination' => ['page' => $page, 'pages' => 1, 'total' => 0],
        ];

        if ($tab === 'general' || $loadAllTabs) {
            $data = array_replace($data, $this->generalBreakdowns($table, $where, $types, $params));
        }

        if ($tab === 'campanas' || $loadAllTabs) {
            $data['campaigns'] = Database::rows(
                "SELECT COALESCE(NULLIF(camp_slug, ''), 'general') AS camp_slug,
                        COUNT(*) AS hits,
                        COUNT(DISTINCT ip_address) AS unique_ips,
                        SUM(event_type = 'event') AS events,
                        SUM(event_type = 'view') AS views,
                        SUM(event_type = 'view' AND object_id > 0) AS property_views,
                        MAX(fecha_visita) AS last_visit
                   FROM {$table}
                  WHERE {$where}
                  GROUP BY camp_slug
                  ORDER BY hits DESC
                  LIMIT 40",
                $types,
                $params
            );
        }

        if ($tab === 'inmuebles' || $loadAllTabs) {
            $this->fillProperties($data, $table, $where, $types, $params, $filters, $perPage, $offset, $page, $tab === 'inmuebles');
        }

        if ($tab === 'eventos' || $loadAllTabs) {
            $data['events'] = Database::rows(
                "SELECT COALESCE(NULLIF(event_label, ''), 'Sin etiqueta') AS event_label,
                        COUNT(*) AS hits,
                        COUNT(DISTINCT ip_address) AS unique_ips,
                        MAX(fecha_visita) AS last_event
                   FROM {$table}
                  WHERE {$where} AND event_type = 'event'
                  GROUP BY event_label
                  ORDER BY hits DESC
                  LIMIT 60",
                $types,
                $params
            );
        }

        if ($tab === 'campanas' || $loadAllTabs) {
            $data['campaign_sources'] = Database::rows(
                "SELECT COALESCE(NULLIF(camp_slug, ''), 'general') AS camp_slug,
                        COALESCE(NULLIF(utm_source, ''), 'Directo') AS source,
                        COALESCE(NULLIF(utm_medium, ''), 'Sin medio') AS medium,
                        COUNT(*) AS hits,
                        COUNT(DISTINCT ip_address) AS unique_ips
                   FROM {$table}
                  WHERE {$where}
                  GROUP BY camp_slug, source, medium
                  ORDER BY hits DESC
                  LIMIT 40",
                $types,
                $params
            );
            $data['campaign_events'] = Database::rows(
                "SELECT COALESCE(NULLIF(camp_slug, ''), 'general') AS camp_slug,
                        COALESCE(NULLIF(event_label, ''), 'Sin etiqueta') AS event_label,
                        COUNT(*) AS hits,
                        COUNT(DISTINCT ip_address) AS unique_ips,
                        MAX(fecha_visita) AS last_event
                   FROM {$table}
                  WHERE {$where} AND event_type = 'event'
                  GROUP BY camp_slug, event_label
                  ORDER BY hits DESC
                  LIMIT 40",
                $types,
                $params
            );
            $data['campaign_properties'] = Database::rows(
                "SELECT COALESCE(NULLIF(camp_slug, ''), 'general') AS camp_slug,
                        object_id,
                        object_ref,
                        SUBSTRING_INDEX(GROUP_CONCAT(NULLIF(page_url, '') ORDER BY fecha_visita DESC SEPARATOR '||'), '||', 1) AS page_url,
                        COUNT(*) AS views,
                        COUNT(DISTINCT ip_address) AS unique_ips,
                        MAX(fecha_visita) AS last_view
                   FROM {$table}
                  WHERE {$where} AND event_type = 'view' AND object_id > 0
                  GROUP BY camp_slug, object_id, object_ref
                  ORDER BY views DESC
                  LIMIT 40",
                $types,
                $params
            );
            $data['campaign_locations'] = Database::rows(
                "SELECT COALESCE(NULLIF(camp_slug, ''), 'general') AS camp_slug,
                        COALESCE(NULLIF(city, ''), 'Sin ciudad') AS city,
                        COALESCE(NULLIF(country, ''), 'Sin pais') AS country,
                        COUNT(*) AS hits
                   FROM {$table}
                  WHERE {$where}
                  GROUP BY camp_slug, city, country
                  ORDER BY hits DESC
                  LIMIT 40",
                $types,
                $params
            );
        }

        if ($tab === 'logs' || $loadAllTabs) {
            $total = (int) Database::value("SELECT COUNT(*) FROM {$table} WHERE {$where}", $types, $params);
            $data['logs'] = Database::rows(
                "SELECT fecha_visita, camp_slug, event_type, event_label, object_id, object_ref, utm_source, utm_medium, city, country, user_id, page_url, referrer
                   FROM {$table}
                  WHERE {$where}
                  ORDER BY fecha_visita DESC
                  LIMIT ? OFFSET ?",
                $types . 'ii',
                array_merge($params, [$perPage, $offset])
            );
            $data['logPagination'] = ['page' => $page, 'pages' => max(1, (int) ceil($total / $perPage)), 'total' => $total];
            if ($tab === 'logs') {
                $data['pagination'] = $data['logPagination'];
            }
        }

        return $data;
    }

    public function campaignPanel(string $from, string $to, array $filters = []): array
    {
        $table = Database::table('jet_cct_tracker_logs');
        if (!Database::tableExists($table)) {
            return [
                'totals' => [],
                'campaigns' => [],
                'sources' => [],
                'daily' => [],
            ];
        }

        [$where, $types, $params] = $this->where($from, $to, $filters);
        $totals = Database::one(
            "SELECT COUNT(*) AS hits,
                    COUNT(DISTINCT ip_address) AS unique_ips,
                    SUM(event_type = 'event') AS events,
                    SUM(event_type = 'view') AS views,
                    SUM(event_type = 'view' AND object_id > 0) AS property_views,
                    SUM(user_id > 0) AS registered_hits,
                    MIN(fecha_visita) AS first_visit,
                    MAX(fecha_visita) AS last_visit
               FROM {$table}
              WHERE {$where}",
            $types,
            $params
        );

        $campaigns = Database::rows(
            "SELECT COALESCE(NULLIF(camp_slug, ''), 'general') AS camp_slug,
                    COUNT(*) AS hits,
                    COUNT(DISTINCT ip_address) AS unique_ips,
                    MAX(fecha_visita) AS last_visit
               FROM {$table}
              WHERE {$where}
              GROUP BY camp_slug
              ORDER BY hits DESC
              LIMIT 10",
            $types,
            $params
        );

        $sources = Database::rows(
            "SELECT COALESCE(NULLIF(utm_source, ''), 'Directo') AS source,
                    COALESCE(NULLIF(utm_medium, ''), 'Sin medio') AS medium,
                    COUNT(*) AS hits,
                    COUNT(DISTINCT ip_address) AS unique_ips
               FROM {$table}
              WHERE {$where}
              GROUP BY source, medium
              ORDER BY hits DESC
              LIMIT 12",
            $types,
            $params
        );

        $daily = Database::rows(
            "SELECT *
               FROM (
                    SELECT DATE(fecha_visita) AS day,
                           COUNT(*) AS hits,
                           COUNT(DISTINCT ip_address) AS unique_ips
                      FROM {$table}
                     WHERE {$where}
                     GROUP BY day
                     ORDER BY day DESC
                     LIMIT 14
               ) recent_days
              ORDER BY day ASC",
            $types,
            $params
        );

        return [
            'totals' => $totals,
            'campaigns' => $campaigns,
            'sources' => $sources,
            'daily' => $daily,
        ];
    }

    private function generalBreakdowns(string $table, string $where, string $types, array $params): array
    {
        $queries = [
            "SELECT 'sources' AS dataset, COALESCE(NULLIF(utm_source, ''), 'Directo') AS label, COUNT(*) AS hits, 0 AS secondary, 0 AS tertiary, NULL AS last_value FROM {$table} WHERE {$where} GROUP BY label ORDER BY hits DESC LIMIT 8",
            "SELECT 'mediums' AS dataset, COALESCE(NULLIF(utm_medium, ''), 'Sin medio') AS label, COUNT(*) AS hits, 0 AS secondary, 0 AS tertiary, NULL AS last_value FROM {$table} WHERE {$where} GROUP BY label ORDER BY hits DESC LIMIT 8",
            "SELECT 'countries' AS dataset, COALESCE(NULLIF(country, ''), 'Sin pais') AS label, COUNT(*) AS hits, 0 AS secondary, 0 AS tertiary, NULL AS last_value FROM {$table} WHERE {$where} GROUP BY label ORDER BY hits DESC LIMIT 8",
            "SELECT 'cities' AS dataset, COALESCE(NULLIF(city, ''), 'Sin ciudad') AS label, COUNT(*) AS hits, 0 AS secondary, 0 AS tertiary, NULL AS last_value FROM {$table} WHERE {$where} GROUP BY label ORDER BY hits DESC LIMIT 8",
            "SELECT 'event_types' AS dataset, LOWER(TRIM(COALESCE(NULLIF(event_type, ''), 'view'))) AS label, COUNT(*) AS hits, 0 AS secondary, 0 AS tertiary, NULL AS last_value FROM {$table} WHERE {$where} GROUP BY label ORDER BY hits DESC LIMIT 20",
            "SELECT 'pages' AS dataset, COALESCE(NULLIF(page_url, ''), 'Sin URL') AS label, COUNT(*) AS hits, COUNT(DISTINCT ip_address) AS secondary, 0 AS tertiary, MAX(fecha_visita) AS last_value FROM {$table} WHERE {$where} GROUP BY label ORDER BY hits DESC LIMIT 10",
            "SELECT 'referrers' AS dataset, COALESCE(NULLIF(referrer, ''), 'Directo') AS label, COUNT(*) AS hits, 0 AS secondary, 0 AS tertiary, NULL AS last_value FROM {$table} WHERE {$where} GROUP BY label ORDER BY hits DESC LIMIT 8",
            "SELECT 'daily' AS dataset, DATE(fecha_visita) AS label, COUNT(*) AS hits, COUNT(DISTINCT ip_address) AS secondary, SUM(event_type = 'event') AS tertiary, NULL AS last_value FROM {$table} WHERE {$where} GROUP BY label ORDER BY label DESC LIMIT 30",
        ];
        $sql = 'SELECT * FROM (' . implode(' UNION ALL ', array_map(static fn (string $query): string => '(' . $query . ')', $queries)) . ') analytics_breakdowns';
        $allParams = [];
        foreach ($queries as $_query) {
            array_push($allParams, ...$params);
        }
        $rows = Database::rows($sql, str_repeat($types, count($queries)), $allParams);
        $result = [
            'sources' => [], 'mediums' => [], 'countries' => [], 'cities' => [],
            'event_types' => [], 'pages' => [], 'referrers' => [], 'daily' => [],
        ];
        $labelKeys = [
            'sources' => 'source', 'mediums' => 'medium', 'countries' => 'country',
            'cities' => 'city', 'event_types' => 'event_type', 'pages' => 'page_url',
            'referrers' => 'referrer', 'daily' => 'day',
        ];

        foreach ($rows as $row) {
            $dataset = (string) ($row['dataset'] ?? '');
            if (!isset($result[$dataset], $labelKeys[$dataset])) {
                continue;
            }
            $item = [$labelKeys[$dataset] => (string) ($row['label'] ?? ''), 'hits' => (int) ($row['hits'] ?? 0)];
            if ($dataset === 'pages') {
                $item['unique_ips'] = (int) ($row['secondary'] ?? 0);
                $item['last_visit'] = (string) ($row['last_value'] ?? '');
            } elseif ($dataset === 'daily') {
                $item['unique_ips'] = (int) ($row['secondary'] ?? 0);
                $item['events'] = (int) ($row['tertiary'] ?? 0);
            }
            $result[$dataset][] = $item;
        }
        usort($result['daily'], static fn (array $a, array $b): int => strcmp((string) $a['day'], (string) $b['day']));

        return $result;
    }

    private function fillProperties(array &$data, string $table, string $where, string $types, array $params, array $filters, int $perPage, int $offset, int $page, bool $activatePagination): void
    {
        $search = trim((string) ($filters['search_ref'] ?? ''));
        $propertyWhere = $where . " AND event_type = 'view' AND object_id > 0";
        $propertyTypes = $types;
        $propertyParams = $params;

        if ($search !== '') {
            $propertyWhere .= ' AND (object_ref LIKE ? OR object_id = ?)';
            $propertyTypes .= 'si';
            $propertyParams[] = '%' . $search . '%';
            $propertyParams[] = (int) $search;
        }

        $total = (int) Database::value(
            "SELECT COUNT(*) FROM (
                SELECT object_id
                  FROM {$table}
                 WHERE {$propertyWhere}
                 GROUP BY object_id
            ) x",
            $propertyTypes,
            $propertyParams
        );

        $data['properties'] = Database::rows(
            "SELECT object_id,
                    object_ref,
                    SUBSTRING_INDEX(GROUP_CONCAT(NULLIF(page_url, '') ORDER BY fecha_visita DESC SEPARATOR '||'), '||', 1) AS page_url,
                    COUNT(*) AS views,
                    COUNT(DISTINCT ip_address) AS unique_ips,
                    MAX(fecha_visita) AS last_view
               FROM {$table}
              WHERE {$propertyWhere}
              GROUP BY object_id, object_ref
              ORDER BY views DESC
              LIMIT ? OFFSET ?",
            $propertyTypes . 'ii',
            array_merge($propertyParams, [$perPage, $offset])
        );
        $data['propertyPagination'] = ['page' => $page, 'pages' => max(1, (int) ceil($total / $perPage)), 'total' => $total];
        if ($activatePagination) {
            $data['pagination'] = $data['propertyPagination'];
        }
    }

    private function where(string $from, string $to, array $filters): array
    {
        $where = '1=1';
        $types = '';
        $params = [];

        if ($from !== '' && $to !== '') {
            $where .= ' AND fecha_visita >= ? AND fecha_visita < ?';
            $types .= 'ss';
            $params[] = $from . ' 00:00:00';
            $params[] = (new \DateTimeImmutable($to))->modify('+1 day')->format('Y-m-d 00:00:00');
        } elseif ($from !== '') {
            $where .= ' AND fecha_visita >= ?';
            $types .= 's';
            $params[] = $from . ' 00:00:00';
        } elseif ($to !== '') {
            $where .= ' AND fecha_visita < ?';
            $types .= 's';
            $params[] = (new \DateTimeImmutable($to))->modify('+1 day')->format('Y-m-d 00:00:00');
        }

        $map = [
            'source' => 'utm_source',
            'medium' => 'utm_medium',
            'campaign' => 'camp_slug',
            'country' => 'country',
            'city' => 'city',
        ];

        foreach ($map as $key => $column) {
            $value = trim((string) ($filters[$key] ?? ''));
            if ($value === '') {
                continue;
            }
            $where .= " AND {$column} = ?";
            $types .= 's';
            $params[] = $value;
        }

        $channelMediums = $this->mediumsForChannel((string) ($filters['channel'] ?? ''));
        if ($channelMediums !== []) {
            $where .= ' AND utm_medium IN (' . implode(',', array_fill(0, count($channelMediums), '?')) . ')';
            $types .= str_repeat('s', count($channelMediums));
            array_push($params, ...$channelMediums);
        }

        $userType = trim((string) ($filters['user_type'] ?? ''));
        if ($userType === 'registered') {
            $where .= ' AND user_id > 0';
        } elseif ($userType === 'guest') {
            $where .= ' AND user_id = 0';
        }

        $eventType = trim((string) ($filters['event_type'] ?? ''));
        if ($eventType !== '') {
            $where .= ' AND event_type = ?';
            $types .= 's';
            $params[] = $eventType;
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where .= ' AND (camp_slug LIKE ? OR page_url LIKE ? OR referrer LIKE ? OR object_ref LIKE ? OR event_label LIKE ?)';
            $types .= 'sssss';
            $like = '%' . $q . '%';
            array_push($params, $like, $like, $like, $like, $like);
        }

        return [$where, $types, $params];
    }

    private function mediumsForChannel(string $channel): array
    {
        return match (strtolower(trim($channel))) {
            'email' => ['Correo Electrónico', 'Correo Electronico', 'Email'],
            'sms' => ['SMS'],
            'whatsapp' => ['WhatsApp'],
            default => [],
        };
    }

    private function options(string $table): array
    {
        $cacheKey = 'analytics_options_' . sha1($table);
        $cached = $_SESSION[$cacheKey] ?? null;
        if (is_array($cached) && (int) ($cached['expires'] ?? 0) > time() && is_array($cached['data'] ?? null)) {
            return $cached['data'];
        }
        $cacheFile = dirname(__DIR__) . '/storage/cache/' . $cacheKey . '.json';
        if (is_readable($cacheFile) && (int) @filemtime($cacheFile) > time() - 900) {
            $fileCached = json_decode((string) file_get_contents($cacheFile), true);
            if (is_array($fileCached)) {
                $_SESSION[$cacheKey] = ['expires' => time() + 120, 'data' => $fileCached];
                return $fileCached;
            }
        }

        $rows = Database::rows(
            "(SELECT 'sources' AS dataset, utm_source AS value FROM {$table} WHERE utm_source IS NOT NULL AND utm_source != '' GROUP BY utm_source ORDER BY utm_source ASC LIMIT 200)
             UNION ALL
             (SELECT 'mediums', utm_medium FROM {$table} WHERE utm_medium IS NOT NULL AND utm_medium != '' GROUP BY utm_medium ORDER BY utm_medium ASC LIMIT 200)
             UNION ALL
             (SELECT 'campaigns', camp_slug FROM {$table} WHERE camp_slug IS NOT NULL AND camp_slug != '' GROUP BY camp_slug ORDER BY camp_slug ASC LIMIT 300)
             UNION ALL
             (SELECT 'countries', country FROM {$table} WHERE country IS NOT NULL AND country != '' GROUP BY country ORDER BY country ASC LIMIT 200)
             UNION ALL
             (SELECT 'cities', city FROM {$table} WHERE city IS NOT NULL AND city != '' GROUP BY city ORDER BY city ASC LIMIT 200)
             UNION ALL
             (SELECT 'event_types', event_type FROM {$table} WHERE event_type IS NOT NULL AND event_type != '' GROUP BY event_type ORDER BY event_type ASC LIMIT 40)"
        );
        $options = ['sources' => [], 'mediums' => [], 'campaigns' => [], 'countries' => [], 'cities' => [], 'event_types' => []];
        foreach ($rows as $row) {
            $dataset = (string) ($row['dataset'] ?? '');
            if (isset($options[$dataset])) {
                $options[$dataset][] = ['value' => (string) ($row['value'] ?? '')];
            }
        }
        $_SESSION[$cacheKey] = ['expires' => time() + 120, 'data' => $options];
        $cacheDir = dirname($cacheFile);
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }
        @file_put_contents($cacheFile, json_encode($options, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $options;
    }

    private function clearOptionsCache(string $table): void
    {
        $cacheKey = 'analytics_options_' . sha1($table);
        unset($_SESSION[$cacheKey]);
        $cacheFile = dirname(__DIR__) . '/storage/cache/' . $cacheKey . '.json';
        if (is_file($cacheFile)) {
            @unlink($cacheFile);
        }
    }

    private function mergeCampaignCatalog(string $from, string $to, int $logsUpdated): void
    {
        if ($logsUpdated <= 0) {
            return;
        }

        $table = Database::table('jet_cct_tracker_camps');
        if (!Database::tableExists($table)) {
            return;
        }

        $fromRow = Database::one("SELECT _ID FROM {$table} WHERE slug = ? LIMIT 1", 's', [$from]);
        if (!$fromRow) {
            return;
        }

        $toRow = Database::one("SELECT _ID FROM {$table} WHERE slug = ? LIMIT 1", 's', [$to]);
        $fromId = (int) ($fromRow['_ID'] ?? 0);
        $toId = (int) ($toRow['_ID'] ?? 0);

        if ($toId > 0 && $toId !== $fromId) {
            Database::execute("DELETE FROM {$table} WHERE _ID = ?", 'i', [$fromId]);
            return;
        }

        $columns = Database::columns($table);
        $data = ['slug' => $to, 'nombre' => ucfirst(str_replace(['-', '_'], ' ', $to))];
        if (isset($columns['cct_modified'])) {
            $data['cct_modified'] = date('Y-m-d H:i:s');
        }
        Database::update($table, array_intersect_key($data, $columns), $fromId);
    }
}
