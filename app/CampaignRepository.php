<?php

declare(strict_types=1);

namespace App;

final class CampaignRepository
{
    public function rename(string $old, string $new): void
    {
        $old = trim($old); $new = trim($new);
        if ($old === '' || $new === '') throw new \RuntimeException('Nombre de campaña inválido.');
        foreach (['jet_cct_campaign_config','jet_cct_campaign_batches','jet_cct_campaign_exclusions','jet_cct_email_tracking'] as $name) {
            $table = Database::table($name);
            if (Database::tableExists($table) && Database::columnExists($table, 'campaign_tag')) Database::execute("UPDATE {$table} SET campaign_tag=? WHERE campaign_tag=?", 'ss', [$new,$old]);
        }
        if (Database::tableExists('skc_notification_queue')) Database::execute("UPDATE skc_notification_queue SET meta_json=JSON_SET(meta_json,'$.gda.campaign_tag',?) WHERE project_code='gestor-actores' AND gda_campaign_tag=?", 'ss', [$new,$old]);
    }

    public function exclude(string $tag, int $actorId, string $type, string $reason): void
    {
        $table = Database::table('jet_cct_campaign_exclusions');
        if ($tag === '' || $actorId < 1 || !Database::tableExists($table)) throw new \RuntimeException('Exclusión inválida.');
        Database::execute("INSERT INTO {$table} (campaign_tag,id_actor,tipo_actor,reason,created_by,created_at) VALUES (?,?,?,?,?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE tipo_actor=VALUES(tipo_actor), reason=VALUES(reason)", 'sissi', [$tag,$actorId,$type,$reason,(int) (Auth::user()['id'] ?? 0)]);
    }
    public function list(): array
    {
        $items = [];
        foreach ((new CampaignService())->list() as $row) {
            $tag = trim((string) ($row['tag'] ?? ''));
            if ($tag === '') {
                continue;
            }
            $items[$tag] = array_merge([
                'tag' => $tag,
                'pending_total' => 0,
                'status' => 'active',
                'pending_total' => 0,
            ], $row);
        }

        foreach ($this->configRows() as $row) {
            $tag = trim((string) ($row['campaign_tag'] ?? ''));
            if ($tag === '') {
                continue;
            }
            $items[$tag] = array_merge($items[$tag] ?? ['tag' => $tag], [
                'status' => (string) ($row['status'] ?? 'active'),
                'batch_size' => (int) ($row['batch_size'] ?? 200),
                'batch_interval_minutes' => (int) ($row['batch_interval_minutes'] ?? 15),
                'max_per_day' => (int) ($row['max_per_day'] ?? 1000),
                'last_activity' => $items[$tag]['last_activity'] ?? ($row['cct_modified'] ?? $row['cct_created'] ?? ''),
            ]);
        }

        foreach ($this->catalogTags() as $tag) {
            $items[$tag] = array_merge([
                'tag' => $tag,
                'pending_total' => 0,
                'sent_total' => 0,
                'failed_total' => 0,
                'status' => 'active',
            ], $items[$tag] ?? []);
        }

        uasort($items, static fn (array $a, array $b): int => strcasecmp((string) ($a['tag'] ?? ''), (string) ($b['tag'] ?? '')));

        return array_values($items);
    }

    public function detail(string $tag, array $filters = [], int $page = 1): array
    {
        $tag = $this->normalize($tag);
        $light = !empty($filters['_light']);
        $status = $this->status($tag, $filters);
        return [
            'tag' => $tag,
            'status' => $status,
            'config' => $this->config($tag),
            'batches' => $light ? [] : $this->batches($tag),
            'exclusions' => $light ? [] : $this->exclusions($tag),
            'actor_types' => $light ? [] : $this->actorTypeUsage($tag, $filters),
            'tracking' => $this->tracking($tag, $filters),
            'analytics' => $this->emptyAnalytics($tag),
            'templates' => $light ? [] : $this->templateUsage($tag, $filters),
            'failed' => $light ? [] : $this->failed($tag, 25),
            'history' => $light ? ['items' => [], 'page' => 1, 'pages' => 1, 'total' => 0] : $this->history($tag, $filters, $page),
        ];
    }

    public function create(string $tag): bool
    {
        $tag = $this->normalize($tag);
        if ($tag === '') {
            return false;
        }

        $this->saveConfig($tag, [
            'batch_size' => 200,
            'batch_interval_minutes' => 15,
            'max_per_day' => 1000,
            'auto_start' => 1,
            'status' => 'active',
        ]);

        return true;
    }

    public function saveConfig(string $tag, array $input): bool
    {
        $table = Database::table('jet_cct_campaign_config');
        if (!Database::tableExists($table)) {
            return false;
        }

        $tag = $this->normalize($tag);
        $columns = Database::columns($table);
        $data = [
            'campaign_tag' => $tag,
            'batch_size' => max(1, (int) ($input['batch_size'] ?? 200)),
            'batch_interval_minutes' => max(1, (int) ($input['batch_interval_minutes'] ?? $input['batch_interval'] ?? 15)),
            'max_per_day' => max(1, (int) ($input['max_per_day'] ?? 1000)),
            'auto_start' => !empty($input['auto_start']) ? 1 : 0,
            'status' => in_array(($input['status'] ?? 'active'), ['active', 'paused'], true) ? (string) $input['status'] : 'active',
            'id_funcionario' => (int) (Auth::user()['id'] ?? 0),
        ];
        if (isset($columns['cct_modified'])) {
            $data['cct_modified'] = date('Y-m-d H:i:s');
        }
        $data = array_intersect_key($data, $columns);

        $existing = Database::one("SELECT _ID FROM {$table} WHERE campaign_tag = ? LIMIT 1", 's', [$tag]);
        if ($existing) {
            return Database::update($table, $data, (int) $existing['_ID']);
        }

        if (isset($columns['cct_created'])) {
            $data['cct_created'] = date('Y-m-d H:i:s');
        }

        return Database::insert($table, $data) > 0;
    }

    public function pause(string $tag, bool $pause): bool
    {
        $config = $this->config($tag);
        $config['status'] = $pause ? 'paused' : 'active';
        return $this->saveConfig($tag, $config);
    }

    public function retryFailed(string $tag): int
    {
        $tag = $this->normalize($tag);
        if ($tag === '') {
            return 0;
        }

        if (!Database::tableExists('skc_notification_queue')) {
            return 0;
        }

        $where = "project_code = 'gestor-actores'
            AND gda_campaign_tag = ?
            AND status = 'failed'";
        $before = (int) Database::value("SELECT COUNT(*) FROM skc_notification_queue WHERE {$where}", 's', [$tag]);
        Database::execute(
            "UPDATE skc_notification_queue
                SET status = 'pending', attempts = 0, last_error = NULL, updated_at = ?
              WHERE {$where}",
            'ss',
            [date('Y-m-d H:i:s'), $tag]
        );

        return $before;
    }

    public function actorTypeOptions(string $tag): array
    {
        $tag = $this->normalize($tag);
        if ($tag === '' || !Database::tableExists('skc_notification_queue')) {
            return [];
        }

        $rows = Database::rows(
            "SELECT gda_tipo_actor AS tipo_actor, COUNT(*) AS total
               FROM skc_notification_queue
              WHERE project_code = 'gestor-actores'
                AND gda_campaign_tag = ?
                AND gda_tipo_actor IS NOT NULL
                AND gda_tipo_actor != ''
              GROUP BY gda_tipo_actor
              ORDER BY gda_tipo_actor ASC",
            's',
            [$tag]
        );

        return array_values(array_filter(array_map(
            static fn (array $row): string => trim((string) ($row['tipo_actor'] ?? '')),
            $rows
        )));
    }

    private function status(string $tag, array $filters = []): array
    {
        $channels = [
            'email' => ['pending' => 0, 'sent' => 0, 'failed' => 0, 'total' => 0],
            'sms' => ['pending' => 0, 'sent' => 0, 'failed' => 0, 'total' => 0],
            'whatsapp' => ['pending' => 0, 'sent' => 0, 'failed' => 0, 'total' => 0],
        ];
        $tag = $this->normalize($tag);
        if (Database::tableExists('skc_notification_queue')) {
            $tracking = Database::table('jet_cct_email_tracking');
            $hasTracking = Database::tableExists($tracking) && trim((string) ($filters['opened'] ?? '')) !== '';
            [$queueWhere, $queueTypes, $queueParams] = $this->historyQueueWhere($tag, $filters, $hasTracking);
            $trackingJoin = $hasTracking ? "LEFT JOIN {$tracking} tracking ON tracking.queue_id = skc_notification_queue.id" : '';
            foreach (Database::rows(
                "SELECT skc_notification_queue.channel AS channel,
                        skc_notification_queue.status AS status,
                        COUNT(*) AS total
                   FROM skc_notification_queue
                   {$trackingJoin}
                  WHERE {$queueWhere}
                  GROUP BY skc_notification_queue.channel, skc_notification_queue.status",
                $queueTypes,
                $queueParams
            ) as $row) {
                $channel = strtolower((string) ($row['channel'] ?? 'email'));
                $state = strtolower((string) ($row['status'] ?? 'pending'));
                $total = (int) ($row['total'] ?? 0);
                if (!isset($channels[$channel])) {
                    $channels[$channel] = ['pending' => 0, 'sent' => 0, 'failed' => 0, 'total' => 0];
                }
                $bucket = $state === 'sent' ? 'sent' : ($state === 'failed' ? 'failed' : 'pending');
                $channels[$channel][$bucket] += $total;
                $channels[$channel]['total'] += $total;
            }
        }

        return $channels;
    }

    private function config(string $tag): array
    {
        $table = Database::table('jet_cct_campaign_config');
        $defaults = [
            'campaign_tag' => $this->normalize($tag),
            'batch_size' => 200,
            'batch_interval_minutes' => 15,
            'max_per_day' => 1000,
            'auto_start' => 1,
            'status' => 'active',
        ];
        if ($this->normalize($tag) === '' || !Database::tableExists($table)) {
            return $defaults;
        }

        return array_merge($defaults, Database::one("SELECT * FROM {$table} WHERE campaign_tag = ? LIMIT 1", 's', [$this->normalize($tag)]));
    }

    private function configRows(): array
    {
        $table = Database::table('jet_cct_campaign_config');
        return Database::tableExists($table) ? Database::rows("SELECT * FROM {$table} ORDER BY cct_modified DESC, _ID DESC") : [];
    }

    private function hydrateAnalytics(array &$items): void
    {
        $stats = $this->analyticsForTags(array_keys($items));
        foreach ($items as $tag => $row) {
            $analytics = $stats[$tag] ?? $this->emptyAnalytics($tag);
            $sent = (int) ($row['sent_total'] ?? 0);
            $analytics['sent_total'] = $sent;
            $analytics['effectiveness_rate'] = $sent > 0 ? round(((int) $analytics['hits'] / $sent) * 100, 1) : 0.0;
            $analytics['unique_effectiveness_rate'] = $sent > 0 ? round(((int) $analytics['unique_ips'] / $sent) * 100, 1) : 0.0;
            $items[$tag]['analytics'] = $analytics;
            $items[$tag]['analytics_hits'] = (int) $analytics['hits'];
            $items[$tag]['analytics_unique_ips'] = (int) $analytics['unique_ips'];
            $items[$tag]['analytics_effectiveness_rate'] = (float) $analytics['effectiveness_rate'];
        }
    }

    private function analytics(string $tag, array $filters, array $status): array
    {
        $tag = $this->normalize($tag);
        $analytics = $this->analyticsForTags([$tag], $filters)[$tag] ?? $this->emptyAnalytics($tag);
        $sent = 0;
        $queueTotal = 0;
        foreach ($status as $row) {
            $sent += (int) ($row['sent'] ?? 0);
            $queueTotal += (int) ($row['total'] ?? 0);
        }

        $analytics['sent_total'] = $sent;
        $analytics['queue_total'] = $queueTotal;
        $analytics['effectiveness_rate'] = $sent > 0 ? round(((int) $analytics['hits'] / $sent) * 100, 1) : 0.0;
        $analytics['unique_effectiveness_rate'] = $sent > 0 ? round(((int) $analytics['unique_ips'] / $sent) * 100, 1) : 0.0;

        return $analytics;
    }

    private function analyticsForTags(array $tags, array $filters = []): array
    {
        $tags = array_values(array_unique(array_filter(array_map(fn ($tag): string => $this->normalize((string) $tag), $tags))));
        if ($tags === []) {
            return [];
        }

        if (!$this->hasAnalyticsFilters($filters)) {
            return $this->analyticsForTagsUnfiltered($tags);
        }

        $result = [];
        foreach ($tags as $tag) {
            $analytics = $this->analyticsForTag($tag, $filters);
            if ((int) ($analytics['hits'] ?? 0) > 0) {
                $result[$tag] = $analytics;
            }
        }

        return $result;
    }

    private function analyticsForTagsUnfiltered(array $tags): array
    {
        $table = Database::table('jet_cct_tracker_logs');
        if (!Database::tableExists($table)) {
            return [];
        }

        $result = [];
        $placeholders = implode(',', array_fill(0, count($tags), '?'));
        foreach (Database::rows(
            "SELECT camp_slug AS tag_key,
                    COUNT(*) AS hits,
                    COUNT(DISTINCT ip_address) AS unique_ips,
                    SUM(event_type = 'event') AS events,
                    SUM(event_type = 'view') AS views,
                    SUM(event_type = 'view' AND object_id > 0) AS property_views,
                    MIN(fecha_visita) AS first_visit,
                    MAX(fecha_visita) AS last_visit,
                    GROUP_CONCAT(DISTINCT NULLIF(utm_source, '') ORDER BY utm_source SEPARATOR ' | ') AS sources,
                    GROUP_CONCAT(DISTINCT NULLIF(utm_medium, '') ORDER BY utm_medium SEPARATOR ' | ') AS mediums
               FROM {$table}
              WHERE camp_slug IN ({$placeholders})
              GROUP BY camp_slug",
            str_repeat('s', count($tags)),
            $tags
        ) as $row) {
            $tag = (string) ($row['tag_key'] ?? '');
            $result[$tag] = $this->analyticsRow($tag, $row);
        }

        $matchersByTag = [];
        $slugs = [];
        foreach ($tags as $tag) {
            foreach ($this->analyticsMatchersForCampaign($tag) as $matcher) {
                $slug = (string) ($matcher['slug'] ?? '');
                $matcherSources = (array) ($matcher['sources'] ?? []);
                if ($slug === '') {
                    continue;
                }
                $matchersByTag[$tag][] = ['slug' => $slug, 'sources' => $matcherSources];
                $slugs[$slug] = $slug;
            }
        }

        if ($slugs !== []) {
            $slugValues = array_values($slugs);
            $slugPlaceholders = implode(',', array_fill(0, count($slugValues), '?'));
            $rows = Database::rows(
                "SELECT camp_slug,
                        utm_source,
                        COUNT(*) AS hits,
                        COUNT(DISTINCT ip_address) AS unique_ips,
                        SUM(event_type = 'event') AS events,
                        SUM(event_type = 'view') AS views,
                        SUM(event_type = 'view' AND object_id > 0) AS property_views,
                        MIN(fecha_visita) AS first_visit,
                        MAX(fecha_visita) AS last_visit,
                        GROUP_CONCAT(DISTINCT NULLIF(utm_source, '') ORDER BY utm_source SEPARATOR ' | ') AS sources,
                        GROUP_CONCAT(DISTINCT NULLIF(utm_medium, '') ORDER BY utm_medium SEPARATOR ' | ') AS mediums
                   FROM {$table}
                  WHERE camp_slug IN ({$slugPlaceholders})
                  GROUP BY camp_slug, utm_source",
                str_repeat('s', count($slugValues)),
                $slugValues
            );

            foreach ($rows as $row) {
                $slug = (string) ($row['camp_slug'] ?? '');
                $source = (string) ($row['utm_source'] ?? '');
                foreach ($matchersByTag as $tag => $tagMatchers) {
                    foreach ($tagMatchers as $matcher) {
                        $matcherSources = (array) ($matcher['sources'] ?? []);
                        if ($matcher['slug'] === $slug && ($matcherSources === [] || in_array($source, $matcherSources, true))) {
                            $this->mergeAnalyticsRow($result[$tag], $tag, $row);
                        }
                    }
                }
            }
        }

        return $result;
    }

    private function hasAnalyticsFilters(array $filters): bool
    {
        foreach (['from', 'to', 'canal', 'tipo_actor', 'analytics_slug', 'analytics_source', 'analytics_medium'] as $key) {
            if (trim((string) ($filters[$key] ?? '')) !== '') {
                return true;
            }
        }
        return false;
    }

    private function analyticsForTag(string $tag, array $filters = []): array
    {
        $table = Database::table('jet_cct_tracker_logs');
        if (!Database::tableExists($table)) {
            return $this->emptyAnalytics($tag);
        }

        $tag = $this->normalize($tag);
        $conditions = [];
        $types = '';
        $params = [];
        $analyticsSource = trim((string) ($filters['analytics_source'] ?? ''));
        $explicitSources = $this->analyticsSourcesForFilter($analyticsSource);
        $autoSources = $analyticsSource === ''
            ? ($this->analyticsSourcesForActorType((string) ($filters['tipo_actor'] ?? '')) ?: $this->analyticsSourcesForCampaignTag($tag))
            : $explicitSources;
        $mediums = $this->analyticsMediumsForFilters($filters);
        $selectedSlug = trim((string) ($filters['analytics_slug'] ?? ''));

        if ($selectedSlug !== '') {
            $this->appendAnalyticsCondition($conditions, $types, $params, $selectedSlug, $autoSources, $mediums);
        } else {
            $this->appendAnalyticsCondition($conditions, $types, $params, $tag, $autoSources, $mediums);
            foreach ($this->analyticsMatchersForCampaign($tag) as $matcher) {
                $matcherSources = $analyticsSource === '' ? $autoSources : $explicitSources;
                $this->appendAnalyticsCondition(
                    $conditions,
                    $types,
                    $params,
                    (string) ($matcher['slug'] ?? ''),
                    $matcherSources,
                    $mediums
                );
            }
        }

        if ($conditions === []) {
            return $this->emptyAnalytics($tag);
        }

        $where = '(' . implode(' OR ', $conditions) . ')';
        $this->appendDateFilters($where, $types, $params, 'fecha_visita', $filters);

        $row = Database::one(
            "SELECT COUNT(*) AS hits,
                    COUNT(DISTINCT ip_address) AS unique_ips,
                    SUM(event_type = 'event') AS events,
                    SUM(event_type = 'view') AS views,
                    SUM(event_type = 'view' AND object_id > 0) AS property_views,
                    MIN(fecha_visita) AS first_visit,
                    MAX(fecha_visita) AS last_visit,
                    GROUP_CONCAT(DISTINCT NULLIF(utm_source, '') ORDER BY utm_source SEPARATOR ' | ') AS sources,
                    GROUP_CONCAT(DISTINCT NULLIF(utm_medium, '') ORDER BY utm_medium SEPARATOR ' | ') AS mediums
               FROM {$table}
              WHERE {$where}",
            $types,
            $params
        );

        $analytics = $this->analyticsRow($tag, $row);
        $analytics['selected_slug'] = $selectedSlug;
        $analytics['selected_source'] = $analyticsSource;
        $analytics['effective_sources'] = implode(' | ', $autoSources);
        $analytics['selected_medium'] = trim((string) ($filters['analytics_medium'] ?? ''));
        $analytics['effective_mediums'] = implode(' | ', $mediums);
        return $analytics;
    }

    private function analyticsRow(string $tag, array $row): array
    {
        return [
            'tag' => $tag,
            'hits' => (int) ($row['hits'] ?? 0),
            'unique_ips' => (int) ($row['unique_ips'] ?? 0),
            'events' => (int) ($row['events'] ?? 0),
            'views' => (int) ($row['views'] ?? 0),
            'property_views' => (int) ($row['property_views'] ?? 0),
            'first_visit' => (string) ($row['first_visit'] ?? ''),
            'last_visit' => (string) ($row['last_visit'] ?? ''),
            'sources' => (string) ($row['sources'] ?? ''),
            'mediums' => (string) ($row['mediums'] ?? ''),
        ];
    }

    private function mergeAnalyticsRow(?array &$current, string $tag, array $row): void
    {
        if ($current === null) {
            $current = $this->emptyAnalytics($tag);
        }

        foreach (['hits', 'unique_ips', 'events', 'views', 'property_views'] as $key) {
            $current[$key] = (int) ($current[$key] ?? 0) + (int) ($row[$key] ?? 0);
        }

        $first = (string) ($row['first_visit'] ?? '');
        if ($first !== '' && ((string) ($current['first_visit'] ?? '') === '' || strcmp($first, (string) $current['first_visit']) < 0)) {
            $current['first_visit'] = $first;
        }
        $last = (string) ($row['last_visit'] ?? '');
        if ($last !== '' && ((string) ($current['last_visit'] ?? '') === '' || strcmp($last, (string) $current['last_visit']) > 0)) {
            $current['last_visit'] = $last;
        }

        foreach (['sources', 'mediums'] as $key) {
            $values = [];
            foreach (explode(' | ', (string) ($current[$key] ?? '')) as $value) {
                $value = trim($value);
                if ($value !== '') {
                    $values[$value] = true;
                }
            }
            foreach (explode(' | ', (string) ($row[$key] ?? '')) as $value) {
                $value = trim($value);
                if ($value !== '') {
                    $values[$value] = true;
                }
            }
            $current[$key] = implode(' | ', array_keys($values));
        }
    }

    private function appendAnalyticsCondition(array &$conditions, string &$types, array &$params, string $slug, array $sources = [], array $mediums = []): void
    {
        $slug = $this->normalize($slug);
        if ($slug === '') {
            return;
        }

        $parts = ['camp_slug = ?'];
        $types .= 's';
        $params[] = $slug;

        $sources = array_values(array_unique(array_filter(array_map('trim', $sources))));
        if ($sources !== []) {
            $parts[] = 'utm_source IN (' . implode(',', array_fill(0, count($sources), '?')) . ')';
            $types .= str_repeat('s', count($sources));
            array_push($params, ...$sources);
        }

        $mediums = array_values(array_unique(array_filter(array_map('trim', $mediums))));
        if ($mediums !== []) {
            $parts[] = 'utm_medium IN (' . implode(',', array_fill(0, count($mediums), '?')) . ')';
            $types .= str_repeat('s', count($mediums));
            array_push($params, ...$mediums);
        }

        $conditions[] = '(' . implode(' AND ', $parts) . ')';
    }

    private function analyticsMatchersForCampaign(string $tag): array
    {
        $sources = $this->analyticsSourcesForCampaignTag($tag);
        $slugs = $this->analyticsSlugsForCampaignTag($tag);
        $matchers = [];
        foreach ($slugs as $slug) {
            $matchers[] = ['slug' => $slug, 'sources' => $sources];
        }
        return $matchers;
    }

    private function analyticsSourcesForCampaignTag(string $tag): array
    {
        $upper = strtoupper($tag);
        $map = [
            'ARRENDAT' => ['Base de Datos Arrendatarios'],
            'CLIENT' => ['Base de Datos Clientes'],
            'CODEUD' => ['Base de Datos Codeudores'],
            'COOPROP' => ['Base de Datos Coopropiedades', 'Base de Datos Copropiedades'],
            'COPROP' => ['Base de Datos Coopropiedades', 'Base de Datos Copropiedades'],
            'PROPIET' => ['Base de Datos Propietarios'],
            'PROVEED' => ['Base de Datos Proveedores'],
            'SUSCRIPTOR' => ['Base de Datos Suscriptores'],
        ];

        foreach ($map as $needle => $sources) {
            if (str_contains($upper, $needle)) {
                return $sources;
            }
        }

        return [];
    }

    private function analyticsSourcesForActorType(string $type): array
    {
        $type = strtolower(trim($type));
        return match ($type) {
            'arrendatarios' => ['Base de Datos Arrendatarios'],
            'clientes' => ['Base de Datos Clientes'],
            'club_pph' => ['Base de Datos Club PPH'],
            'codeudores' => ['Base de Datos Codeudores'],
            'contactos' => ['Base de Datos Contactos'],
            'contactos_funcionarios' => ['Base de Datos Contactos Funcionarios'],
            'copropiedades' => ['Base de Datos Coopropiedades', 'Base de Datos Copropiedades'],
            'funcionarios' => ['Base de Datos Funcionarios'],
            'propietarios' => ['Base de Datos Propietarios'],
            'proveedores' => ['Base de Datos Proveedores'],
            'suscriptores' => ['Base de Datos Suscriptores'],
            default => [],
        };
    }

    private function analyticsSlugsForCampaignTag(string $tag): array
    {
        $tag = $this->normalize($tag);
        $slugs = [];
        $add = static function (string $slug) use (&$slugs): void {
            $slug = strtolower(trim($slug));
            $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug) ?? $slug;
            $slug = trim($slug, '-');
            if ($slug !== '') {
                $slugs[$slug] = true;
            }
        };

        foreach ($this->analyticsSlugHintsFromQueue()[$tag] ?? [] as $slug) {
            $add($slug);
        }

        foreach ($this->editionSlugsFromText($tag) as $slug) {
            $add($slug);
        }

        return array_keys($slugs);
    }

    private function analyticsSlugHintsFromQueue(): array
    {
        static $cache = null;
        if (is_array($cache)) {
            return $cache;
        }

        $cache = [];
        if (!Database::tableExists('skc_notification_queue')) {
            return $cache;
        }

        $rows = Database::rows(
            "SELECT gda_campaign_tag, message_text, message_html
               FROM skc_notification_queue
              WHERE project_code = 'gestor-actores'
                AND gda_campaign_tag IS NOT NULL
                AND gda_campaign_tag != ''
                AND (
                    message_text LIKE '%esencia%'
                    OR message_html LIKE '%esencia%'
                    OR message_text LIKE '%utm_campaign%'
                    OR message_html LIKE '%utm_campaign%'
                )
              GROUP BY gda_campaign_tag, message_text, message_html
              ORDER BY MAX(id) DESC
              LIMIT 800"
        );

        foreach ($rows as $row) {
            $tag = $this->normalize((string) ($row['gda_campaign_tag'] ?? ''));
            if ($tag === '') {
                continue;
            }
            $body = html_entity_decode((string) ($row['message_text'] ?? '') . ' ' . (string) ($row['message_html'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            foreach ($this->analyticsSlugsFromBody($body) as $slug) {
                $cache[$tag][$slug] = $slug;
            }
        }

        foreach ($cache as &$slugs) {
            $slugs = array_values($slugs);
        }
        unset($slugs);

        return $cache;
    }

    private function analyticsSlugsFromBody(string $body): array
    {
        $slugs = [];
        if (preg_match_all('/esencia-inmobiliaria-edicion-[a-z0-9-]+/i', $body, $matches)) {
            foreach ($matches[0] as $match) {
                $slugs[] = strtolower($match);
            }
        }
        if (preg_match_all('/utm_campaign=([^&"\'<>\s]+)/i', $body, $campaignMatches)) {
            foreach ($campaignMatches[1] as $match) {
                $normalized = $this->normalizeAnalyticsCampaignSlug(rawurldecode($match));
                if ($normalized !== '') {
                    $slugs[] = $normalized;
                }
            }
        }
        foreach ($this->editionSlugsFromText($body) as $slug) {
            $slugs[] = $slug;
        }

        return array_values(array_unique($slugs));
    }

    private function normalizeAnalyticsCampaignSlug(string $value): string
    {
        $value = strtolower(trim($value));
        $value = str_replace(['+', '_', ' '], '-', $value);
        $value = preg_replace('/-+/', '-', $value) ?? $value;
        $value = trim($value, '-');
        if (in_array($value, [
            'revista-esencia-junio-2026',
            'revista-esencia-junio-2026',
            'esencia-junio',
            'esencia-inmobiliaria-junio-2026',
            'rv-esencia-junio-26-2026-visitasjunio-2026',
            'esencia-inmobiliaria-edicion-junio-2026-visitas',
        ], true)) {
            return 'esencia-inmobiliaria-edicion-junio-2026';
        }
        $value = preg_replace('/[^a-z0-9-]+/', '-', $value) ?? $value;
        $value = preg_replace('/-+/', '-', $value) ?? $value;
        return trim($value, '-');
    }

    private function editionSlugsFromText(string $text): array
    {
        $text = strtolower($text);
        $months = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
        $slugs = [];
        foreach ($months as $month) {
            if (preg_match('/' . preg_quote($month, '/') . '[\s-]+(20\d{2})/', $text, $match)) {
                $slugs[] = 'esencia-inmobiliaria-edicion-' . $month . '-' . $match[1];
            }
        }
        return $slugs;
    }

    private function analyticsMediumsForChannel(string $channel): array
    {
        return match (strtolower(trim($channel))) {
            'email' => ['Correo Electrónico', 'Email'],
            'sms' => ['SMS'],
            'whatsapp' => ['WhatsApp'],
            default => [],
        };
    }

    private function analyticsSourcesForFilter(string $source): array
    {
        $source = trim($source);
        return $source === '' || $source === '__all' ? [] : [$source];
    }

    private function analyticsMediumsForFilters(array $filters): array
    {
        $medium = trim((string) ($filters['analytics_medium'] ?? ''));
        if ($medium === '__all') {
            return [];
        }
        if ($medium !== '') {
            return [$medium];
        }

        return $this->analyticsMediumsForChannel((string) ($filters['canal'] ?? ''));
    }

    private function emptyAnalytics(string $tag): array
    {
        return [
            'tag' => $tag,
            'hits' => 0,
            'unique_ips' => 0,
            'events' => 0,
            'views' => 0,
            'property_views' => 0,
            'first_visit' => '',
            'last_visit' => '',
            'sources' => '',
            'mediums' => '',
            'sent_total' => 0,
            'queue_total' => 0,
            'effectiveness_rate' => 0.0,
            'unique_effectiveness_rate' => 0.0,
        ];
    }

    private function catalogTags(): array
    {
        $tags = [];
        $add = function (string $tag) use (&$tags): void {
            $tag = $this->normalize($tag);
            if ($tag !== '') {
                $tags[$tag] = true;
            }
        };

        $optionsTable = Database::table('options');
        if (Database::tableExists($optionsTable)) {
            $optionNames = ['gda_campaigns_catalog'];
            $userId = (int) (Auth::user()['id'] ?? 0);
            if ($userId > 0) {
                $optionNames[] = 'gda_campaigns_catalog_user_' . $userId;
            }

            foreach ($optionNames as $optionName) {
                $value = (string) (Database::value("SELECT option_value FROM {$optionsTable} WHERE option_name = ? LIMIT 1", 's', [$optionName]) ?? '');
                foreach ($this->decodeCatalogOption($value) as $tag) {
                    $add((string) $tag);
                }
            }
        }

        $logTable = Database::table('jet_cct_envios_log');
        if (Database::tableExists($logTable) && Database::columnExists($logTable, 'campaign_tag')) {
            foreach (Database::rows("SELECT DISTINCT campaign_tag FROM {$logTable} WHERE campaign_tag IS NOT NULL AND campaign_tag != '' ORDER BY campaign_tag ASC") as $row) {
                $add((string) ($row['campaign_tag'] ?? ''));
            }
        }

        return array_keys($tags);
    }

    private function decodeCatalogOption(string $value): array
    {
        if ($value === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $unserialized = @unserialize($value, ['allowed_classes' => false]);
        return is_array($unserialized) ? $unserialized : [];
    }

    private function batches(string $tag): array
    {
        $table = Database::table('jet_cct_campaign_batches');
        $tag = $this->normalize($tag);
        if (!Database::tableExists($table)) {
            return [];
        }

        return $tag !== ''
            ? Database::rows("SELECT * FROM {$table} WHERE campaign_tag = ? ORDER BY batch_number ASC", 's', [$tag])
            : Database::rows("SELECT * FROM {$table} ORDER BY _ID DESC LIMIT 100")
            ;
    }

    private function exclusions(string $tag): array
    {
        $table = Database::table('jet_cct_campaign_exclusions');
        $tag = $this->normalize($tag);
        if (!Database::tableExists($table)) {
            return [];
        }

        return $tag !== ''
            ? Database::rows("SELECT * FROM {$table} WHERE campaign_tag = ? ORDER BY _ID DESC LIMIT 50", 's', [$tag])
            : Database::rows("SELECT * FROM {$table} ORDER BY _ID DESC LIMIT 50");
    }

    private function tracking(string $tag, array $filters = []): array
    {
        $table = Database::table('jet_cct_email_tracking');
        if (!Database::tableExists($table) || !Database::tableExists('skc_notification_queue')) {
            return ['tracked' => 0, 'opened' => 0, 'total_opens' => 0];
        }

        $tag = $this->normalize($tag);
        [$where, $types, $params] = $this->historyQueueWhere($tag, $filters, true);

        return Database::one(
            "SELECT COUNT(tracking._ID) AS tracked,
                    SUM(CASE WHEN tracking.opened_at IS NOT NULL OR COALESCE(tracking.open_count, 0) > 0 THEN 1 ELSE 0 END) AS opened,
                    COALESCE(SUM(tracking.open_count), 0) AS total_opens
               FROM skc_notification_queue
               LEFT JOIN {$table} tracking ON tracking.queue_id = skc_notification_queue.id
              WHERE {$where}",
            $types,
            $params
        );
    }

    private function actorTypeUsage(string $tag, array $filters = []): array
    {
        if (!Database::tableExists('skc_notification_queue')) {
            return [];
        }

        $tracking = Database::table('jet_cct_email_tracking');
        $hasTracking = Database::tableExists($tracking) && trim((string) ($filters['opened'] ?? '')) !== '';
        [$where, $types, $params] = $this->historyQueueWhere($tag, $filters, $hasTracking);
        $actorExpression = 'skc_notification_queue.gda_tipo_actor';
        $items = [];

        foreach (Database::rows(
            "SELECT {$actorExpression} AS tipo_actor, COUNT(*) AS total
               FROM skc_notification_queue
              WHERE {$where}
              GROUP BY tipo_actor
              ORDER BY total DESC, tipo_actor ASC
              LIMIT 8",
            $types,
            $params
        ) as $row) {
            $key = trim((string) ($row['tipo_actor'] ?? ''));
            if ($key !== '') {
                $items[$key] = (int) ($items[$key] ?? 0) + (int) ($row['total'] ?? 0);
            }
        }

        arsort($items);
        $out = [];
        foreach (array_slice($items, 0, 8, true) as $tipoActor => $total) {
            $out[] = ['tipo_actor' => $tipoActor, 'total' => $total];
        }
        return $out;
    }

    private function failed(string $tag, int $limit): array
    {
        if (!Database::tableExists('skc_notification_queue')) {
            return [];
        }

        $tag = $this->normalize($tag);
        $campaignExpression = 'skc_notification_queue.gda_campaign_tag';
        $actorExpression = 'skc_notification_queue.gda_tipo_actor';
        $where = $tag !== '' ? "project_code = 'gestor-actores' AND {$campaignExpression} = ? AND status = 'failed'" : "project_code = 'gestor-actores' AND {$campaignExpression} IS NOT NULL AND {$campaignExpression} != '' AND status = 'failed'";
        $types = $tag !== '' ? 'si' : 'i';
        $params = $tag !== '' ? [$tag, $limit] : [$limit];

        $items = Database::rows(
            "SELECT id AS _ID,
                    COALESCE(sent_at, scheduled_at, created_at) AS fecha_envio,
                    channel AS canal,
                    destination_name AS nombre_destinatario,
                    subject AS asunto,
                    last_error AS error_info,
                    gda_id_actor AS id_actor,
                    {$actorExpression} AS tipo_actor
               FROM skc_notification_queue
              WHERE {$where}
              ORDER BY id DESC
              LIMIT ?",
            $types,
            $params
        );

        usort($items, static fn (array $a, array $b): int => strcmp((string) ($b['fecha_envio'] ?? ''), (string) ($a['fecha_envio'] ?? '')));
        return array_slice($items, 0, $limit);
    }

    private function history(string $tag, array $filters, int $page): array
    {
        $hasQueue = Database::tableExists('skc_notification_queue');
        if (!$hasQueue) {
            return ['items' => [], 'page' => 1, 'pages' => 1, 'total' => 0];
        }

        $queries = [];
        $countParams = [];
        $countTypes = '';
        $itemParams = [];
        $itemTypes = '';

        $tracking = Database::table('jet_cct_email_tracking');
        $hasTracking = Database::tableExists($tracking) && trim((string) ($filters['opened'] ?? '')) !== '';
        [$where, $types, $params] = $this->historyQueueWhere($tag, $filters, $hasTracking);
        $campaignExpression = 'skc_notification_queue.gda_campaign_tag';
        $actorExpression = 'skc_notification_queue.gda_tipo_actor';
        $templateExpression = $this->templateNameExpression();
        $trackingJoin = $hasTracking
            ? "LEFT JOIN {$tracking} tracking ON tracking.queue_id = skc_notification_queue.id"
            : '';
        $openCount = $hasTracking ? 'COALESCE(tracking.open_count, 0)' : '0';
        $openedAt = $hasTracking ? 'tracking.opened_at' : 'NULL';
        $source = $this->textExpression("'queue'");
        $rowId = $this->textExpression('CAST(skc_notification_queue.id AS CHAR)');
        $campaign = $this->textExpression($campaignExpression);
        $channel = $this->textExpression('skc_notification_queue.channel');
        $state = $this->textExpression('skc_notification_queue.status');
        $recipient = $this->textExpression('skc_notification_queue.destination_name');
        $subject = $this->textExpression('skc_notification_queue.subject');
        $templateName = $this->textExpression($templateExpression);
        $actor = $this->textExpression($actorExpression);
        $error = $this->textExpression('skc_notification_queue.last_error');
        $queries[] = "SELECT {$source} AS source, {$rowId} AS row_id,
                             COALESCE(skc_notification_queue.sent_at, skc_notification_queue.scheduled_at, skc_notification_queue.created_at) AS fecha_envio,
                             {$campaign} AS campaign_tag,
                             {$channel} AS canal,
                             {$state} AS estado,
                             {$recipient} AS nombre_destinatario,
                             {$subject} AS asunto,
                             {$templateName} AS template_name,
                             {$actor} AS tipo_actor,
                             {$openCount} AS open_count,
                             {$openedAt} AS opened_at,
                             {$error} AS error_info
                        FROM skc_notification_queue
                        {$trackingJoin}
                       WHERE {$where}";
        $countTypes .= $types;
        $countParams = array_merge($countParams, $params);
        $itemTypes .= $types;
        $itemParams = array_merge($itemParams, $params);

        $perPage = 25;
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;
        $union = implode(' UNION ALL ', $queries);
        $total = (int) Database::value("SELECT COUNT(*) FROM ({$union}) history_rows", $countTypes, $countParams);
        $items = Database::rows(
            "SELECT * FROM ({$union}) history_rows ORDER BY fecha_envio DESC, row_id DESC LIMIT ? OFFSET ?",
            $itemTypes . 'ii',
            array_merge($itemParams, [$perPage, $offset])
        );

        return ['items' => $items, 'page' => $page, 'pages' => max(1, (int) ceil($total / $perPage)), 'total' => $total];
    }

    private function templateUsage(string $tag, array $filters = []): array
    {
        if (!Database::tableExists('skc_notification_queue')) {
            return [];
        }

        $tag = $this->normalize($tag);
        $tracking = Database::table('jet_cct_email_tracking');
        $hasTracking = Database::tableExists($tracking) && trim((string) ($filters['opened'] ?? '')) !== '';
        $templateExpression = $this->templateNameExpression();
        [$where, $types, $params] = $this->historyQueueWhere($tag, $filters, $hasTracking);
        $trackingJoin = $hasTracking ? "LEFT JOIN {$tracking} tracking ON tracking.queue_id = skc_notification_queue.id" : '';

        return Database::rows(
            "SELECT {$templateExpression} AS template_name, COUNT(*) AS total
               FROM skc_notification_queue
               {$trackingJoin}
              WHERE {$where}
              GROUP BY template_name
              ORDER BY total DESC, template_name ASC
              LIMIT 8",
            $types,
            $params
        );
    }

    private function historyQueueWhere(string $tag, array $filters, bool $hasTracking): array
    {
        $where = "skc_notification_queue.project_code = 'gestor-actores'";
        $types = '';
        $params = [];
        $campaignExpression = 'skc_notification_queue.gda_campaign_tag';
        $actorExpression = 'skc_notification_queue.gda_tipo_actor';
        $dateExpression = 'COALESCE(skc_notification_queue.sent_at, skc_notification_queue.scheduled_at, skc_notification_queue.created_at)';

        if ($tag !== '') {
            $where .= " AND {$campaignExpression} = ?";
            $types .= 's';
            $params[] = $tag;
        } else {
            $where .= " AND {$campaignExpression} IS NOT NULL AND {$campaignExpression} != ''";
        }

        $this->appendDateFilters($where, $types, $params, $dateExpression, $filters);
        foreach (['canal' => 'skc_notification_queue.channel', 'estado' => 'skc_notification_queue.status'] as $filter => $column) {
            $value = trim((string) ($filters[$filter] ?? ''));
            if ($value !== '') {
                $where .= " AND {$column} = ?";
                $types .= 's';
                $params[] = $value;
            }
        }

        $actorType = trim((string) ($filters['tipo_actor'] ?? ''));
        if ($actorType !== '') {
            $where .= " AND {$actorExpression} = ?";
            $types .= 's';
            $params[] = $actorType;
        }

        $opened = trim((string) ($filters['opened'] ?? ''));
        if ($opened === 'yes') {
            $where .= $hasTracking
                ? ' AND (tracking.opened_at IS NOT NULL OR COALESCE(tracking.open_count, 0) > 0)'
                : ' AND 1 = 0';
        } elseif ($opened === 'no') {
            $where .= $hasTracking
                ? ' AND (tracking.queue_id IS NULL OR (tracking.opened_at IS NULL AND COALESCE(tracking.open_count, 0) = 0))'
                : '';
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where .= " AND (skc_notification_queue.destination_name LIKE ? OR skc_notification_queue.destination LIKE ? OR skc_notification_queue.subject LIKE ? OR skc_notification_queue.message_text LIKE ? OR skc_notification_queue.last_error LIKE ? OR {$campaignExpression} LIKE ?)";
            $types .= 'ssssss';
            array_push($params, '%' . $q . '%', '%' . $q . '%', '%' . $q . '%', '%' . $q . '%', '%' . $q . '%', '%' . $q . '%');
        }

        return [$where, $types, $params];
    }

    private function appendDateFilters(string &$where, string &$types, array &$params, string $columnExpression, array $filters): void
    {
        $from = trim((string) ($filters['from'] ?? ''));
        $to = trim((string) ($filters['to'] ?? ''));
        if ($from !== '') {
            $where .= " AND {$columnExpression} >= ?";
            $types .= 's';
            $params[] = $from . ' 00:00:00';
        }
        if ($to !== '') {
            $where .= " AND {$columnExpression} <= ?";
            $types .= 's';
            $params[] = $to . ' 23:59:59';
        }
    }

    private function textExpression(string $expression): string
    {
        return "CONVERT({$expression} USING utf8mb4) COLLATE utf8mb4_unicode_ci";
    }

    private function templateNameExpression(): string
    {
        $templates = Database::table('jet_cct_plantillas');
        $empty = $this->collatedText("''");
        $manual = $this->collatedText("'Manual'");
        $templateColumn = $this->collatedText('skc_notification_queue.template_name');
        $metaTemplateId = "CAST(JSON_UNQUOTE(JSON_EXTRACT(skc_notification_queue.meta_json, '$.gda.template_id')) AS UNSIGNED)";
        $metaTemplate = $this->collatedText("JSON_UNQUOTE(JSON_EXTRACT(skc_notification_queue.meta_json, '$.gda.template_name'))");
        $templateName = $this->collatedText('tpl.nombre');
        $templateSubject = $this->collatedText('tpl.asunto');
        $queueSubject = $this->collatedText('skc_notification_queue.subject');
        $actorType = $this->collatedText('skc_notification_queue.gda_tipo_actor');
        $idFallback = Database::tableExists($templates)
            ? "(SELECT {$templateName} FROM {$templates} tpl
                  WHERE tpl._ID = {$metaTemplateId}
                    AND tpl.nombre IS NOT NULL
                    AND {$templateName} != {$empty}
                  LIMIT 1)"
            : "''";
        $fallback = Database::tableExists($templates)
            ? "(SELECT {$templateName} FROM {$templates} tpl
                  WHERE {$templateSubject} = {$queueSubject}
                    AND tpl.nombre IS NOT NULL
                    AND {$templateName} != {$empty}
                  ORDER BY CASE
                      WHEN LOWER({$templateName}) LIKE CONCAT('%', LOWER({$actorType}), '%') THEN 0
                      ELSE 1
                    END,
                    tpl._ID DESC
                  LIMIT 1)"
            : "''";

        return "COALESCE(
            NULLIF({$templateColumn}, {$empty}),
            NULLIF({$metaTemplate}, {$empty}),
            NULLIF({$idFallback}, {$empty}),
            {$fallback},
            {$manual}
        )";
    }

    private function collatedText(string $expression): string
    {
        return "CONVERT({$expression} USING utf8mb4) COLLATE utf8mb4_unicode_ci";
    }

    private function normalize(string $tag): string
    {
        $tag = trim($tag);
        $tag = preg_replace('/[\x00-\x1F\x7F]/', '', $tag) ?? $tag;
        return substr($tag, 0, 120);
    }
}
