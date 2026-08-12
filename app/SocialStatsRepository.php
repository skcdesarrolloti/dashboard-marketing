<?php

declare(strict_types=1);

namespace App;

final class SocialStatsRepository
{
    private string $accountsTable;
    private string $dailyTable;
    private string $postsTable;
    private string $adsTable;
    private string $audienceTable;
    private string $leadFormsTable;
    private string $conversationsTable;

    public function __construct()
    {
        $this->accountsTable = Database::table('marketing_social_accounts');
        $this->dailyTable = Database::table('marketing_social_daily_stats');
        $this->postsTable = Database::table('marketing_social_posts');
        $this->adsTable = Database::table('marketing_social_ads_stats');
        $this->audienceTable = Database::table('marketing_social_audience');
        $this->leadFormsTable = Database::table('marketing_social_lead_forms');
        $this->conversationsTable = Database::table('marketing_social_conversations');
        $this->ensureTables();
        $this->purgeDemoData();
    }

    public function dashboard(string $from, string $to, string $platform = ''): array
    {
        $filters = ['from' => $from, 'to' => $to, 'platform' => $platform];
        $current = $this->dailyRows($filters);
        $days = max(1, (int) ((strtotime($to) - strtotime($from)) / 86400) + 1);
        $currentDates = array_values(array_unique(array_map(static fn (array $row): string => (string) ($row['metric_date'] ?? ''), $current)));
        $currentDates = array_values(array_filter($currentDates, static fn (string $date): bool => $date !== ''));
        $previousTo = date('Y-m-d', strtotime($from . ' -1 day'));
        $previousFrom = date('Y-m-d', strtotime($previousTo . ' -' . ($days - 1) . ' days'));
        $previous = $this->dailyRows(['from' => $previousFrom, 'to' => $previousTo, 'platform' => $platform]);
        $previousCoverage = count(array_unique(array_map(static fn (array $row): string => (string) ($row['metric_date'] ?? ''), $previous)));
        $currentSummary = $this->summary($current);
        $previousSummary = $this->summary($previous);
        $platformSummary = $this->platformSummary($current);
        if ($platform === 'instagram') {
            $currentSummary = $this->mergePostSummary($currentSummary, $this->postSummary($from, $to, 'instagram'));
            $previousSummary = $this->mergePostSummary($previousSummary, $this->postSummary($previousFrom, $previousTo, 'instagram'));
            $platformSummary = ['instagram' => $currentSummary];
        }

        return [
            'accounts' => $this->accounts($platform),
            'summary' => $currentSummary,
            'previous' => $previousSummary,
            'platforms' => $platformSummary,
            'series' => $this->series($current),
            'topPosts' => $this->topPosts($from, $to, $platform),
            'posts' => $this->postsForRange($from, $to, $platform),
            'ads' => $this->adsDashboard($from, $to),
            'audience' => $this->audienceDashboard(),
            'period' => [
                'current' => ['from' => $from, 'to' => $to],
                'previous' => ['from' => $previousFrom, 'to' => $previousTo],
                'comparison_ready' => $previousCoverage >= $days,
                'requested_days' => $days,
                'covered_days' => count($currentDates),
                'available_from' => $currentDates ? min($currentDates) : '',
                'available_to' => $currentDates ? max($currentDates) : '',
            ],
            'hasLiveData' => $this->hasLiveData(),
        ];
    }

    public function platforms(): array
    {
        return ['' => 'Todas', 'instagram' => 'Instagram', 'facebook' => 'Facebook'];
    }

    public function upsertAccount(array $data): void
    {
        $platform = $this->choice($data['platform'] ?? '', ['instagram', 'facebook'], 'instagram');
        $accountId = $this->text($data['account_id'] ?? '', 120);
        if ($accountId === '') {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $payload = [
            'platform' => $platform,
            'account_name' => $this->text($data['account_name'] ?? '', 160),
            'account_id' => $accountId,
            'username' => $this->text($data['username'] ?? '', 120),
            'connected' => (int) ($data['connected'] ?? 1),
            'last_sync' => $now,
            'notes' => $this->textArea($data['notes'] ?? 'Sincronizado desde Meta Graph API.', 1000),
            'updated_at' => $now,
        ];

        $existing = Database::one(
            "SELECT _ID FROM {$this->accountsTable} WHERE platform = ? AND account_id = ? LIMIT 1",
            'ss',
            [$platform, $accountId]
        );

        if ($existing) {
            Database::update($this->accountsTable, $payload, (int) $existing['_ID']);
            return;
        }

        $payload['created_at'] = $now;
        Database::insert($this->accountsTable, $payload);
    }

    public function upsertDailyStat(array $data): void
    {
        $payload = $this->dailyPayload($data);
        $platform = (string) $payload['platform'];
        $accountId = (string) $payload['account_id'];
        $date = (string) $payload['metric_date'];

        $existing = Database::one(
            "SELECT _ID FROM {$this->dailyTable} WHERE metric_date = ? AND platform = ? AND account_id = ? LIMIT 1",
            'sss',
            [$date, $platform, $accountId]
        );

        if ($existing) {
            Database::update($this->dailyTable, $payload, (int) $existing['_ID']);
            return;
        }

        $payload['created_at'] = $payload['updated_at'];
        Database::insert($this->dailyTable, $payload);
    }

    public function upsertDailyStats(array $rows): void
    {
        $payloads = array_map(fn (array $row): array => $this->dailyPayload($row) + ['created_at' => date('Y-m-d H:i:s')], $rows);
        $this->bulkUpsert($this->dailyTable, $payloads, ['followers', 'reach', 'impressions', 'profile_views', 'website_clicks', 'likes', 'comments', 'shares', 'saves', 'content_count', 'source', 'updated_at']);
    }

    public function upsertPost(array $data): void
    {
        $payload = $this->postPayload($data);
        $platform = (string) $payload['platform'];
        $externalId = (string) $payload['external_id'];
        if ($externalId === '') {
            return;
        }

        $existing = Database::one(
            "SELECT _ID FROM {$this->postsTable} WHERE platform = ? AND external_id = ? LIMIT 1",
            'ss',
            [$platform, $externalId]
        );

        if ($existing) {
            Database::update($this->postsTable, $payload, (int) $existing['_ID']);
            return;
        }

        $payload['created_at'] = $payload['updated_at'];
        Database::insert($this->postsTable, $payload);
    }

    public function upsertPosts(array $rows): void
    {
        $payloads = [];
        foreach ($rows as $row) {
            $payload = $this->postPayload($row);
            if ($payload['external_id'] === '') {
                continue;
            }
            $payload['created_at'] = $payload['updated_at'];
            $payloads[] = $payload;
        }
        $this->bulkUpsert($this->postsTable, $payloads, ['post_date', 'post_time', 'account_id', 'content_type', 'title', 'permalink', 'thumbnail_url', 'reach', 'impressions', 'likes', 'comments', 'shares', 'saves', 'metrics_json', 'source', 'updated_at']);
    }

    public function upsertAdStat(array $data): void
    {
        $externalId = $this->text($data['external_id'] ?? '', 160);
        if ($externalId === '') {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $level = $this->choice($data['level'] ?? 'campaign', ['account', 'account_daily', 'campaign', 'ad'], 'campaign');
        $metricDate = $this->date($data['metric_date'] ?? date('Y-m-d'), date('Y-m-d'));
        $payload = [
            'period_start' => $this->date($data['period_start'] ?? $metricDate, $metricDate),
            'metric_date' => $metricDate,
            'level' => $level,
            'account_id' => $this->text($data['account_id'] ?? '', 120),
            'external_id' => $externalId,
            'name' => $this->text($data['name'] ?? '', 220),
            'status' => $this->text($data['status'] ?? '', 80),
            'objective' => $this->text($data['objective'] ?? '', 80),
            'spend' => max(0, (float) ($data['spend'] ?? 0)),
            'impressions' => max(0, (int) ($data['impressions'] ?? 0)),
            'reach' => max(0, (int) ($data['reach'] ?? 0)),
            'clicks' => max(0, (int) ($data['clicks'] ?? 0)),
            'link_clicks' => max(0, (int) ($data['link_clicks'] ?? 0)),
            'ctr' => max(0, (float) ($data['ctr'] ?? 0)),
            'cpc' => max(0, (float) ($data['cpc'] ?? 0)),
            'cpm' => max(0, (float) ($data['cpm'] ?? 0)),
            'frequency' => max(0, (float) ($data['frequency'] ?? 0)),
            'actions_json' => $this->jsonText($data['actions'] ?? []),
            'thumbnail_url' => $this->text($data['thumbnail_url'] ?? '', 800),
            'source' => 'meta',
            'updated_at' => $now,
        ];

        $existing = Database::one(
            "SELECT _ID FROM {$this->adsTable} WHERE period_start = ? AND metric_date = ? AND level = ? AND external_id = ? LIMIT 1",
            'ssss',
            [$payload['period_start'], $metricDate, $level, $externalId]
        );

        if ($existing) {
            Database::update($this->adsTable, $payload, (int) $existing['_ID']);
            return;
        }

        $payload['created_at'] = $now;
        Database::insert($this->adsTable, $payload);
    }

    public function upsertAudienceInsight(array $data): void
    {
        $label = $this->text($data['label'] ?? '', 180);
        $breakdown = $this->choice($data['breakdown'] ?? '', ['city', 'country', 'gender', 'age', 'gender_age', 'media_product_type', 'follower_type'], 'city');
        if ($label === '') {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $snapshotDate = $this->date($data['snapshot_date'] ?? date('Y-m-d'), date('Y-m-d'));
        $payload = [
            'snapshot_date' => $snapshotDate,
            'platform' => $this->choice($data['platform'] ?? 'instagram', ['instagram', 'facebook'], 'instagram'),
            'account_id' => $this->text($data['account_id'] ?? '', 120),
            'breakdown' => $breakdown,
            'label' => $label,
            'value' => max(0, (int) ($data['value'] ?? 0)),
            'source' => 'meta',
            'updated_at' => $now,
        ];

        $existing = Database::one(
            "SELECT _ID FROM {$this->audienceTable} WHERE snapshot_date = ? AND platform = ? AND breakdown = ? AND label = ? LIMIT 1",
            'ssss',
            [$snapshotDate, $payload['platform'], $breakdown, $label]
        );

        if ($existing) {
            Database::update($this->audienceTable, $payload, (int) $existing['_ID']);
            return;
        }

        $payload['created_at'] = $now;
        Database::insert($this->audienceTable, $payload);
    }

    public function hasAudienceSnapshot(string $date, string $platform = 'instagram'): bool
    {
        $date = $this->date($date, date('Y-m-d'));
        $platform = $this->choice($platform, ['instagram', 'facebook'], 'instagram');
        return (int) Database::value(
            "SELECT COUNT(*) FROM {$this->audienceTable} WHERE snapshot_date = ? AND platform = ?",
            'ss',
            [$date, $platform]
        ) > 0;
    }

    public function upsertLeadForm(array $data): void
    {
        $externalId = $this->text($data['external_id'] ?? '', 160);
        if ($externalId === '') {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $payload = [
            'external_id' => $externalId,
            'name' => $this->text($data['name'] ?? '', 220),
            'status' => $this->text($data['status'] ?? '', 80),
            'leads_count' => max(0, (int) ($data['leads_count'] ?? 0)),
            'created_time' => $this->dateTime($data['created_time'] ?? ''),
            'source' => 'meta',
            'updated_at' => $now,
        ];

        $existing = Database::one("SELECT _ID FROM {$this->leadFormsTable} WHERE external_id = ? LIMIT 1", 's', [$externalId]);
        if ($existing) {
            Database::update($this->leadFormsTable, $payload, (int) $existing['_ID']);
            return;
        }

        $payload['created_at'] = $now;
        Database::insert($this->leadFormsTable, $payload);
    }

    public function upsertConversation(array $data): void
    {
        $externalId = $this->text($data['external_id'] ?? '', 180);
        if ($externalId === '') {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $payload = [
            'external_id' => $externalId,
            'updated_time' => $this->dateTime($data['updated_time'] ?? ''),
            'message_count' => max(0, (int) ($data['message_count'] ?? 0)),
            'unread_count' => max(0, (int) ($data['unread_count'] ?? 0)),
            'participants' => $this->textArea($data['participants'] ?? '', 1000),
            'source' => 'meta',
            'updated_at' => $now,
        ];

        $existing = Database::one("SELECT _ID FROM {$this->conversationsTable} WHERE external_id = ? LIMIT 1", 's', [$externalId]);
        if ($existing) {
            Database::update($this->conversationsTable, $payload, (int) $existing['_ID']);
            return;
        }

        $payload['created_at'] = $now;
        Database::insert($this->conversationsTable, $payload);
    }

    private function dailyPayload(array $data): array
    {
        return [
            'metric_date' => $this->date($data['metric_date'] ?? '', date('Y-m-d')),
            'platform' => $this->choice($data['platform'] ?? '', ['instagram', 'facebook'], 'instagram'),
            'account_id' => $this->text($data['account_id'] ?? '', 120),
            'followers' => max(0, (int) ($data['followers'] ?? 0)),
            'reach' => max(0, (int) ($data['reach'] ?? 0)),
            'impressions' => max(0, (int) ($data['impressions'] ?? 0)),
            'profile_views' => max(0, (int) ($data['profile_views'] ?? 0)),
            'website_clicks' => max(0, (int) ($data['website_clicks'] ?? 0)),
            'likes' => max(0, (int) ($data['likes'] ?? 0)),
            'comments' => max(0, (int) ($data['comments'] ?? 0)),
            'shares' => max(0, (int) ($data['shares'] ?? 0)),
            'saves' => max(0, (int) ($data['saves'] ?? 0)),
            'content_count' => max(0, (int) ($data['content_count'] ?? 0)),
            'source' => $this->choice($data['source'] ?? 'meta', ['manual', 'meta'], 'meta'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function postPayload(array $data): array
    {
        return [
            'post_date' => $this->date($data['post_date'] ?? '', date('Y-m-d')),
            'post_time' => $this->time($data['post_time'] ?? ''),
            'platform' => $this->choice($data['platform'] ?? '', ['instagram', 'facebook'], 'instagram'),
            'account_id' => $this->text($data['account_id'] ?? '', 120),
            'external_id' => $this->text($data['external_id'] ?? '', 160),
            'content_type' => $this->text($data['content_type'] ?? 'post', 32),
            'title' => $this->text($data['title'] ?? '', 220),
            'permalink' => $this->text($data['permalink'] ?? '', 800),
            'thumbnail_url' => $this->text($data['thumbnail_url'] ?? '', 800),
            'reach' => max(0, (int) ($data['reach'] ?? 0)),
            'impressions' => max(0, (int) ($data['impressions'] ?? 0)),
            'likes' => max(0, (int) ($data['likes'] ?? 0)),
            'comments' => max(0, (int) ($data['comments'] ?? 0)),
            'shares' => max(0, (int) ($data['shares'] ?? 0)),
            'saves' => max(0, (int) ($data['saves'] ?? 0)),
            'metrics_json' => $this->jsonText($data['metrics'] ?? []),
            'source' => $this->choice($data['source'] ?? 'meta', ['manual', 'meta'], 'meta'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function bulkUpsert(string $table, array $rows, array $updateColumns): void
    {
        foreach (array_chunk($rows, 100) as $chunk) {
            if ($chunk === []) {
                continue;
            }
            $columns = array_keys($chunk[0]);
            $rowPlaceholder = '(' . implode(',', array_fill(0, count($columns), '?')) . ')';
            $params = [];
            foreach ($chunk as $row) {
                foreach ($columns as $column) {
                    $params[] = (string) ($row[$column] ?? '');
                }
            }
            $updates = array_map(static fn (string $column): string => "`{$column}` = VALUES(`{$column}`)", $updateColumns);
            $sql = 'INSERT INTO ' . $table . ' (`' . implode('`,`', $columns) . '`) VALUES '
                . implode(',', array_fill(0, count($chunk), $rowPlaceholder))
                . ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);
            if (!Database::execute($sql, str_repeat('s', count($params)), $params)) {
                throw new \RuntimeException('No fue posible guardar el bloque sincronizado en la base de datos.');
            }
        }
    }

    private function ensureTables(): void
    {
        if (!Database::tableExists($this->accountsTable)) {
            Database::execute(
                "CREATE TABLE {$this->accountsTable} (
                    _ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    platform VARCHAR(32) NOT NULL,
                    account_name VARCHAR(160) NOT NULL DEFAULT '',
                    account_id VARCHAR(120) NOT NULL DEFAULT '',
                    username VARCHAR(120) NOT NULL DEFAULT '',
                    connected TINYINT(1) NOT NULL DEFAULT 0,
                    last_sync DATETIME NULL,
                    notes TEXT NULL,
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL,
                    PRIMARY KEY (_ID),
                    UNIQUE KEY platform_account (platform, account_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        if (!Database::tableExists($this->dailyTable)) {
            Database::execute(
                "CREATE TABLE {$this->dailyTable} (
                    _ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    metric_date DATE NOT NULL,
                    platform VARCHAR(32) NOT NULL,
                    account_id VARCHAR(120) NOT NULL DEFAULT '',
                    followers INT UNSIGNED NOT NULL DEFAULT 0,
                    reach INT UNSIGNED NOT NULL DEFAULT 0,
                    impressions INT UNSIGNED NOT NULL DEFAULT 0,
                    profile_views INT UNSIGNED NOT NULL DEFAULT 0,
                    website_clicks INT UNSIGNED NOT NULL DEFAULT 0,
                    likes INT UNSIGNED NOT NULL DEFAULT 0,
                    comments INT UNSIGNED NOT NULL DEFAULT 0,
                    shares INT UNSIGNED NOT NULL DEFAULT 0,
                    saves INT UNSIGNED NOT NULL DEFAULT 0,
                    content_count INT UNSIGNED NOT NULL DEFAULT 0,
                    source VARCHAR(32) NOT NULL DEFAULT 'manual',
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL,
                    PRIMARY KEY (_ID),
                    UNIQUE KEY daily_account (metric_date, platform, account_id),
                    KEY metric_date (metric_date),
                    KEY platform (platform)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        if (!Database::tableExists($this->postsTable)) {
            Database::execute(
                "CREATE TABLE {$this->postsTable} (
                    _ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    post_date DATE NOT NULL,
                    post_time CHAR(5) NOT NULL DEFAULT '',
                    platform VARCHAR(32) NOT NULL,
                    account_id VARCHAR(120) NOT NULL DEFAULT '',
                    external_id VARCHAR(160) NOT NULL DEFAULT '',
                    content_type VARCHAR(32) NOT NULL DEFAULT 'post',
                    title VARCHAR(220) NOT NULL DEFAULT '',
                    permalink VARCHAR(800) NOT NULL DEFAULT '',
                    thumbnail_url VARCHAR(800) NOT NULL DEFAULT '',
                    reach INT UNSIGNED NOT NULL DEFAULT 0,
                    impressions INT UNSIGNED NOT NULL DEFAULT 0,
                    likes INT UNSIGNED NOT NULL DEFAULT 0,
                    comments INT UNSIGNED NOT NULL DEFAULT 0,
                    shares INT UNSIGNED NOT NULL DEFAULT 0,
                    saves INT UNSIGNED NOT NULL DEFAULT 0,
                    metrics_json MEDIUMTEXT NULL,
                    source VARCHAR(32) NOT NULL DEFAULT 'manual',
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL,
                    PRIMARY KEY (_ID),
                    KEY post_date (post_date),
                    KEY platform (platform),
                    KEY external_id (external_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
        if (Database::tableExists($this->postsTable) && !Database::columnExists($this->postsTable, 'metrics_json')) {
            Database::execute("ALTER TABLE {$this->postsTable} ADD COLUMN metrics_json MEDIUMTEXT NULL AFTER saves");
        }
        $postUniqueIndex = Database::one("SHOW INDEX FROM {$this->postsTable} WHERE Key_name = 'platform_external' LIMIT 1");
        if (!$postUniqueIndex) {
            Database::execute("ALTER TABLE {$this->postsTable} ADD UNIQUE KEY platform_external (platform, external_id)");
        }

        if (!Database::tableExists($this->adsTable)) {
            Database::execute(
                "CREATE TABLE {$this->adsTable} (
                    _ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    period_start DATE NOT NULL,
                    metric_date DATE NOT NULL,
                    level VARCHAR(32) NOT NULL,
                    account_id VARCHAR(120) NOT NULL DEFAULT '',
                    external_id VARCHAR(160) NOT NULL DEFAULT '',
                    name VARCHAR(220) NOT NULL DEFAULT '',
                    status VARCHAR(80) NOT NULL DEFAULT '',
                    objective VARCHAR(80) NOT NULL DEFAULT '',
                    spend DECIMAL(14,2) NOT NULL DEFAULT 0,
                    impressions INT UNSIGNED NOT NULL DEFAULT 0,
                    reach INT UNSIGNED NOT NULL DEFAULT 0,
                    clicks INT UNSIGNED NOT NULL DEFAULT 0,
                    link_clicks INT UNSIGNED NOT NULL DEFAULT 0,
                    ctr DECIMAL(10,4) NOT NULL DEFAULT 0,
                    cpc DECIMAL(14,4) NOT NULL DEFAULT 0,
                    cpm DECIMAL(14,4) NOT NULL DEFAULT 0,
                    frequency DECIMAL(10,4) NOT NULL DEFAULT 0,
                    actions_json MEDIUMTEXT NULL,
                    thumbnail_url VARCHAR(800) NOT NULL DEFAULT '',
                    source VARCHAR(32) NOT NULL DEFAULT 'meta',
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL,
                    PRIMARY KEY (_ID),
                    UNIQUE KEY period_stat_unique (period_start, metric_date, level, external_id),
                    KEY level_date (level, metric_date),
                    KEY account_id (account_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
        if (Database::tableExists($this->adsTable) && !Database::columnExists($this->adsTable, 'period_start')) {
            Database::execute("ALTER TABLE {$this->adsTable} ADD COLUMN period_start DATE NOT NULL DEFAULT '1970-01-01' AFTER _ID");
            Database::execute("UPDATE {$this->adsTable} SET period_start = metric_date WHERE period_start = '1970-01-01'");
            Database::execute("ALTER TABLE {$this->adsTable} DROP INDEX stat_unique");
            Database::execute("ALTER TABLE {$this->adsTable} ADD UNIQUE KEY period_stat_unique (period_start, metric_date, level, external_id)");
        }

        if (!Database::tableExists($this->audienceTable)) {
            Database::execute(
                "CREATE TABLE {$this->audienceTable} (
                    _ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    snapshot_date DATE NOT NULL,
                    platform VARCHAR(32) NOT NULL,
                    account_id VARCHAR(120) NOT NULL DEFAULT '',
                    breakdown VARCHAR(64) NOT NULL,
                    label VARCHAR(180) NOT NULL,
                    value INT UNSIGNED NOT NULL DEFAULT 0,
                    source VARCHAR(32) NOT NULL DEFAULT 'meta',
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL,
                    PRIMARY KEY (_ID),
                    UNIQUE KEY audience_unique (snapshot_date, platform, breakdown, label),
                    KEY breakdown (breakdown),
                    KEY snapshot_date (snapshot_date)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        if (!Database::tableExists($this->leadFormsTable)) {
            Database::execute(
                "CREATE TABLE {$this->leadFormsTable} (
                    _ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    external_id VARCHAR(160) NOT NULL DEFAULT '',
                    name VARCHAR(220) NOT NULL DEFAULT '',
                    status VARCHAR(80) NOT NULL DEFAULT '',
                    leads_count INT UNSIGNED NOT NULL DEFAULT 0,
                    created_time DATETIME NULL,
                    source VARCHAR(32) NOT NULL DEFAULT 'meta',
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL,
                    PRIMARY KEY (_ID),
                    UNIQUE KEY form_unique (external_id),
                    KEY status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        if (!Database::tableExists($this->conversationsTable)) {
            Database::execute(
                "CREATE TABLE {$this->conversationsTable} (
                    _ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    external_id VARCHAR(180) NOT NULL DEFAULT '',
                    updated_time DATETIME NULL,
                    message_count INT UNSIGNED NOT NULL DEFAULT 0,
                    unread_count INT UNSIGNED NOT NULL DEFAULT 0,
                    participants TEXT NULL,
                    source VARCHAR(32) NOT NULL DEFAULT 'meta',
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL,
                    PRIMARY KEY (_ID),
                    UNIQUE KEY conversation_unique (external_id),
                    KEY updated_time (updated_time)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
    }

    private function purgeDemoData(): void
    {
        Database::execute("DELETE FROM {$this->dailyTable} WHERE source = 'demo'");
        Database::execute("DELETE FROM {$this->postsTable} WHERE source = 'demo'");
        Database::execute("DELETE FROM {$this->accountsTable} WHERE connected = 0 OR account_id LIKE 'demo\\_%'");
    }

    private function dailyRows(array $filters): array
    {
        $where = 'metric_date BETWEEN ? AND ?';
        $types = 'ss';
        $params = [$filters['from'], $filters['to']];
        if (($filters['platform'] ?? '') !== '') {
            $where .= ' AND platform = ?';
            $types .= 's';
            $params[] = $filters['platform'];
        }
        if ($this->hasLiveData()) {
            $where .= " AND source <> 'demo'";
        }

        return Database::rows(
            "SELECT * FROM {$this->dailyTable} WHERE {$where} ORDER BY metric_date ASC, platform ASC",
            $types,
            $params
        );
    }

    private function accounts(string $platform): array
    {
        $where = '1=1';
        $types = '';
        $params = [];
        if ($platform !== '') {
            $where .= ' AND platform = ?';
            $types .= 's';
            $params[] = $platform;
        }
        if ($this->hasLiveData()) {
            $where .= ' AND connected = 1';
        }
        return Database::rows("SELECT * FROM {$this->accountsTable} WHERE {$where} ORDER BY platform ASC", $types, $params);
    }

    private function topPosts(string $from, string $to, string $platform): array
    {
        $where = 'post_date BETWEEN ? AND ?';
        $types = 'ss';
        $params = [$from, $to];
        if ($platform !== '') {
            $where .= ' AND platform = ?';
            $types .= 's';
            $params[] = $platform;
        }
        if ($this->hasLiveData()) {
            $where .= " AND source <> 'demo'";
        }

        return Database::rows(
            "SELECT *, (likes + comments + shares + saves) AS engagement
               FROM {$this->postsTable}
              WHERE {$where}
              ORDER BY engagement DESC, reach DESC
              LIMIT 8",
            $types,
            $params
        );
    }

    private function postsForRange(string $from, string $to, string $platform): array
    {
        $where = 'post_date BETWEEN ? AND ?';
        $types = 'ss';
        $params = [$from, $to];
        if ($platform !== '') {
            $where .= ' AND platform = ?';
            $types .= 's';
            $params[] = $platform;
        }
        if ($this->hasLiveData()) {
            $where .= " AND source <> 'demo'";
        }

        return Database::rows(
            "SELECT *, (likes + comments + shares + saves) AS engagement
               FROM {$this->postsTable}
              WHERE {$where}
              ORDER BY post_date DESC, post_time DESC, _ID DESC",
            $types,
            $params
        );
    }

    private function adsDashboard(string $from, string $to): array
    {
        $period = $this->adsPeriod($from, $to);
        if ($period === []) {
            return ['summary' => [], 'campaigns' => [], 'ads' => [], 'series' => $this->adsDailySeries($from, $to), 'date' => '', 'period_start' => '', 'exact' => false];
        }

        $summary = Database::one(
            "SELECT
                MIN(period_start) AS period_start,
                MAX(metric_date) AS metric_date,
                'account' AS level,
                COUNT(*) AS account_count,
                SUM(spend) AS spend,
                SUM(impressions) AS impressions,
                SUM(reach) AS reach,
                SUM(clicks) AS clicks,
                SUM(link_clicks) AS link_clicks,
                CASE WHEN SUM(impressions) > 0 THEN (SUM(clicks) / SUM(impressions)) * 100 ELSE 0 END AS ctr,
                CASE WHEN SUM(clicks) > 0 THEN SUM(spend) / SUM(clicks) ELSE 0 END AS cpc,
                CASE WHEN SUM(impressions) > 0 THEN (SUM(spend) / SUM(impressions)) * 1000 ELSE 0 END AS cpm,
                CASE WHEN SUM(reach) > 0 THEN SUM(impressions) / SUM(reach) ELSE 0 END AS frequency
               FROM {$this->adsTable}
              WHERE period_start = ? AND metric_date = ? AND level = 'account'",
            'ss',
            [$period['from'], $period['to']]
        );
        $campaigns = Database::rows(
            "SELECT *
              FROM {$this->adsTable}
              WHERE period_start = ? AND metric_date = ? AND level = 'campaign'
              ORDER BY spend DESC, impressions DESC, name ASC",
            'ss',
            [$period['from'], $period['to']]
        );
        $ads = Database::rows(
            "SELECT *
              FROM {$this->adsTable}
              WHERE period_start = ? AND metric_date = ? AND level = 'ad'
              ORDER BY spend DESC, impressions DESC, name ASC",
            'ss',
            [$period['from'], $period['to']]
        );

        return ['summary' => $summary, 'campaigns' => $campaigns, 'ads' => $ads, 'series' => $this->adsDailySeries($from, $to), 'date' => $period['to'], 'period_start' => $period['from'], 'exact' => $period['exact']];
    }

    private function adsDailySeries(string $from, string $to): array
    {
        return Database::rows(
            "SELECT
                metric_date,
                SUM(spend) AS spend,
                SUM(impressions) AS impressions,
                SUM(reach) AS reach,
                SUM(clicks) AS clicks,
                SUM(link_clicks) AS link_clicks
               FROM {$this->adsTable}
              WHERE metric_date BETWEEN ? AND ? AND level = 'account_daily'
              GROUP BY metric_date
              ORDER BY metric_date ASC",
            'ss',
            [$from, $to]
        );
    }

    private function adsPeriod(string $from, string $to): array
    {
        $exact = Database::one(
            "SELECT period_start, metric_date
               FROM {$this->adsTable}
              WHERE period_start = ? AND metric_date = ? AND level IN ('account', 'campaign', 'ad')
              ORDER BY FIELD(level, 'account', 'campaign', 'ad')
              LIMIT 1",
            'ss',
            [$from, $to]
        );
        if ($exact) {
            return ['from' => (string) $exact['period_start'], 'to' => (string) $exact['metric_date'], 'exact' => true];
        }

        $latest = Database::one(
            "SELECT period_start, metric_date
              FROM {$this->adsTable}
              WHERE metric_date <= ? AND level IN ('account', 'campaign', 'ad')
              ORDER BY metric_date DESC, period_start ASC
              LIMIT 1",
            's',
            [$to]
        );
        if (!$latest) {
            return [];
        }
        return ['from' => (string) $latest['period_start'], 'to' => (string) $latest['metric_date'], 'exact' => false];
    }

    private function audienceDashboard(): array
    {
        $latestDate = (string) (Database::value("SELECT MAX(snapshot_date) FROM {$this->audienceTable}") ?? '');
        if ($latestDate === '') {
            return ['date' => '', 'city' => [], 'country' => [], 'gender_age' => [], 'gender' => [], 'age' => []];
        }

        $result = ['date' => $latestDate];
        foreach (['city', 'country', 'gender_age', 'gender', 'age'] as $breakdown) {
            $result[$breakdown] = Database::rows(
                "SELECT * FROM {$this->audienceTable} WHERE snapshot_date = ? AND breakdown = ? ORDER BY value DESC LIMIT 10",
                'ss',
                [$latestDate, $breakdown]
            );
        }
        return $result;
    }

    private function summary(array $rows): array
    {
        $latestFollowers = [];
        $summary = [
            'followers' => 0,
            'reach' => 0,
            'impressions' => 0,
            'profile_views' => 0,
            'website_clicks' => 0,
            'likes' => 0,
            'comments' => 0,
            'shares' => 0,
            'saves' => 0,
            'content_count' => 0,
            'engagement' => 0,
            'engagement_rate' => 0.0,
        ];

        foreach ($rows as $row) {
            $platform = (string) ($row['platform'] ?? '');
            $latestFollowers[$platform] = (int) ($row['followers'] ?? 0);
            foreach (['reach', 'impressions', 'profile_views', 'website_clicks', 'likes', 'comments', 'shares', 'saves', 'content_count'] as $key) {
                $summary[$key] += (int) ($row[$key] ?? 0);
            }
        }

        $summary['followers'] = array_sum($latestFollowers);
        $summary['engagement'] = $summary['likes'] + $summary['comments'] + $summary['shares'] + $summary['saves'];
        $summary['engagement_rate'] = $summary['reach'] > 0 ? round(($summary['engagement'] / $summary['reach']) * 100, 2) : 0.0;

        return $summary;
    }

    private function postSummary(string $from, string $to, string $platform): array
    {
        return Database::one(
            "SELECT
                COUNT(*) AS content_count,
                COALESCE(SUM(reach), 0) AS reach,
                COALESCE(SUM(impressions), 0) AS impressions,
                COALESCE(SUM(likes), 0) AS likes,
                COALESCE(SUM(comments), 0) AS comments,
                COALESCE(SUM(shares), 0) AS shares,
                COALESCE(SUM(saves), 0) AS saves
               FROM {$this->postsTable}
              WHERE post_date BETWEEN ? AND ? AND platform = ? AND source <> 'demo'",
            'sss',
            [$from, $to, $platform]
        );
    }

    private function mergePostSummary(array $summary, array $posts): array
    {
        foreach (['reach', 'impressions', 'likes', 'comments', 'shares', 'saves', 'content_count'] as $key) {
            $summary[$key] = (int) ($posts[$key] ?? 0);
        }
        $summary['engagement'] = $summary['likes'] + $summary['comments'] + $summary['shares'] + $summary['saves'];
        $summary['engagement_rate'] = $summary['reach'] > 0 ? round(($summary['engagement'] / $summary['reach']) * 100, 2) : 0.0;
        return $summary;
    }

    private function platformSummary(array $rows): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $platform = (string) ($row['platform'] ?? 'otro');
            $grouped[$platform][] = $row;
        }
        $summary = [];
        foreach ($grouped as $platform => $platformRows) {
            $summary[$platform] = $this->summary($platformRows);
        }
        return $summary;
    }

    private function series(array $rows): array
    {
        $series = [];
        foreach ($rows as $row) {
            $date = (string) ($row['metric_date'] ?? '');
            if (!isset($series[$date])) {
                $series[$date] = ['date' => $date, 'reach' => 0, 'impressions' => 0, 'engagement' => 0, 'followers' => 0];
            }
            $series[$date]['reach'] += (int) ($row['reach'] ?? 0);
            $series[$date]['impressions'] += (int) ($row['impressions'] ?? 0);
            $series[$date]['engagement'] += (int) ($row['likes'] ?? 0) + (int) ($row['comments'] ?? 0) + (int) ($row['shares'] ?? 0) + (int) ($row['saves'] ?? 0);
            $series[$date]['followers'] += (int) ($row['followers'] ?? 0);
        }
        return array_values($series);
    }

    private function hasLiveData(): bool
    {
        return (int) Database::value("SELECT COUNT(*) FROM {$this->dailyTable} WHERE source <> 'demo'") > 0;
    }

    private function dateTime(mixed $value): ?string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }
        $time = strtotime($text);
        return $time ? date('Y-m-d H:i:s', $time) : null;
    }

    private function jsonText(mixed $value): string
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return is_string($json) ? $json : '[]';
    }

    private function date(mixed $value, string $fallback): string
    {
        $text = trim((string) $value);
        $date = \DateTime::createFromFormat('Y-m-d', $text);
        return $date && $date->format('Y-m-d') === $text ? $text : $fallback;
    }

    private function time(mixed $value): string
    {
        $text = trim((string) $value);
        return preg_match('/^\d{2}:\d{2}$/', $text) ? $text : '';
    }

    private function choice(mixed $value, array $allowed, string $fallback): string
    {
        $text = trim((string) $value);
        return in_array($text, $allowed, true) ? $text : $fallback;
    }

    private function text(mixed $value, int $max): string
    {
        $text = trim(strip_tags((string) $value));
        $text = preg_replace('/[\x00-\x1F\x7F]/u', '', $text) ?? '';
        return mb_substr($text, 0, $max);
    }

    private function textArea(mixed $value, int $max): string
    {
        $text = trim(strip_tags((string) $value));
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';
        return mb_substr($text, 0, $max);
    }
}
