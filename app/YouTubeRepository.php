<?php

declare(strict_types=1);

namespace App;

final class YouTubeRepository
{
    private string $connectionsTable;
    private string $dailyTable;
    private string $videosTable;
    private string $breakdownsTable;

    public function __construct()
    {
        $this->connectionsTable = Database::table('marketing_youtube_connections');
        $this->dailyTable = Database::table('marketing_youtube_daily_stats');
        $this->videosTable = Database::table('marketing_youtube_videos');
        $this->breakdownsTable = Database::table('marketing_youtube_breakdowns');
        $this->ensureTables();
    }

    public function connection(): array
    {
        return Database::one(
            "SELECT * FROM {$this->connectionsTable} ORDER BY connected DESC, updated_at DESC LIMIT 1"
        );
    }

    public function saveConnection(array $data): array
    {
        $channelId = $this->text($data['channel_id'] ?? '', 120);
        if ($channelId === '') {
            throw new \RuntimeException('Google no devolvió un canal de YouTube válido.');
        }

        $now = date('Y-m-d H:i:s');
        Database::execute("UPDATE {$this->connectionsTable} SET connected = 0, updated_at = ?", 's', [$now]);
        $payload = [
            'channel_id' => $channelId,
            'channel_title' => $this->text($data['channel_title'] ?? '', 220),
            'channel_handle' => $this->text($data['channel_handle'] ?? '', 180),
            'thumbnail_url' => $this->text($data['thumbnail_url'] ?? '', 800),
            'uploads_playlist_id' => $this->text($data['uploads_playlist_id'] ?? '', 120),
            'subscriber_count' => max(0, (int) ($data['subscriber_count'] ?? 0)),
            'view_count' => max(0, (int) ($data['view_count'] ?? 0)),
            'video_count' => max(0, (int) ($data['video_count'] ?? 0)),
            'access_token_enc' => $this->textArea($data['access_token_enc'] ?? ''),
            'refresh_token_enc' => $this->textArea($data['refresh_token_enc'] ?? ''),
            'token_expires_at' => $this->dateTime($data['token_expires_at'] ?? ''),
            'scopes' => $this->textArea($data['scopes'] ?? ''),
            'connected' => 1,
            'last_error' => '',
            'updated_at' => $now,
        ];

        $existing = Database::one(
            "SELECT _ID, refresh_token_enc FROM {$this->connectionsTable} WHERE channel_id = ? LIMIT 1",
            's',
            [$channelId]
        );
        if ($existing) {
            if ($payload['refresh_token_enc'] === '') {
                $payload['refresh_token_enc'] = (string) ($existing['refresh_token_enc'] ?? '');
            }
            Database::update($this->connectionsTable, $payload, (int) $existing['_ID']);
        } else {
            $payload['created_at'] = $now;
            Database::insert($this->connectionsTable, $payload);
        }

        return $this->connection();
    }

    public function updateTokens(string $channelId, string $accessTokenEnc, string $refreshTokenEnc, string $expiresAt): void
    {
        $row = Database::one(
            "SELECT _ID, refresh_token_enc FROM {$this->connectionsTable} WHERE channel_id = ? LIMIT 1",
            's',
            [$channelId]
        );
        if (!$row) {
            throw new \RuntimeException('La conexión de YouTube ya no existe. Vuelve a conectar el canal.');
        }

        Database::update($this->connectionsTable, [
            'access_token_enc' => $accessTokenEnc,
            'refresh_token_enc' => $refreshTokenEnc !== '' ? $refreshTokenEnc : (string) ($row['refresh_token_enc'] ?? ''),
            'token_expires_at' => $expiresAt,
            'connected' => 1,
            'last_error' => '',
            'updated_at' => date('Y-m-d H:i:s'),
        ], (int) $row['_ID']);
    }

    public function updateChannel(array $data): void
    {
        $channelId = $this->text($data['channel_id'] ?? '', 120);
        $row = Database::one("SELECT _ID FROM {$this->connectionsTable} WHERE channel_id = ? LIMIT 1", 's', [$channelId]);
        if (!$row) {
            return;
        }

        Database::update($this->connectionsTable, [
            'channel_title' => $this->text($data['channel_title'] ?? '', 220),
            'channel_handle' => $this->text($data['channel_handle'] ?? '', 180),
            'thumbnail_url' => $this->text($data['thumbnail_url'] ?? '', 800),
            'uploads_playlist_id' => $this->text($data['uploads_playlist_id'] ?? '', 120),
            'subscriber_count' => max(0, (int) ($data['subscriber_count'] ?? 0)),
            'view_count' => max(0, (int) ($data['view_count'] ?? 0)),
            'video_count' => max(0, (int) ($data['video_count'] ?? 0)),
            'connected' => 1,
            'last_error' => '',
            'updated_at' => date('Y-m-d H:i:s'),
        ], (int) $row['_ID']);
    }

    public function recordSync(string $channelId, string $error = ''): void
    {
        $row = Database::one("SELECT _ID FROM {$this->connectionsTable} WHERE channel_id = ? LIMIT 1", 's', [$channelId]);
        if (!$row) {
            return;
        }

        $payload = [
            'last_error' => $this->textArea($error, 2000),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($error === '') {
            $payload['last_sync'] = date('Y-m-d H:i:s');
        }
        Database::update($this->connectionsTable, $payload, (int) $row['_ID']);
    }

    public function disconnect(): void
    {
        $now = date('Y-m-d H:i:s');
        Database::execute(
            "UPDATE {$this->connectionsTable}
                SET connected = 0,
                    access_token_enc = '',
                    refresh_token_enc = '',
                    token_expires_at = NULL,
                    last_error = '',
                    updated_at = ?",
            's',
            [$now]
        );
    }

    public function upsertDaily(array $rows): int
    {
        $payloads = [];
        $now = date('Y-m-d H:i:s');
        foreach ($rows as $row) {
            $channelId = $this->text($row['channel_id'] ?? '', 120);
            $date = $this->date($row['metric_date'] ?? '');
            if ($channelId === '' || $date === '') {
                continue;
            }
            $payloads[] = [
                'metric_date' => $date,
                'channel_id' => $channelId,
                'views' => max(0, (int) ($row['views'] ?? 0)),
                'engaged_views' => max(0, (int) ($row['engaged_views'] ?? 0)),
                'watch_minutes' => max(0, (int) round((float) ($row['watch_minutes'] ?? 0))),
                'average_view_duration' => max(0, (float) ($row['average_view_duration'] ?? 0)),
                'average_view_percentage' => max(0, (float) ($row['average_view_percentage'] ?? 0)),
                'likes' => max(0, (int) ($row['likes'] ?? 0)),
                'comments' => max(0, (int) ($row['comments'] ?? 0)),
                'shares' => max(0, (int) ($row['shares'] ?? 0)),
                'subscribers_gained' => max(0, (int) ($row['subscribers_gained'] ?? 0)),
                'subscribers_lost' => max(0, (int) ($row['subscribers_lost'] ?? 0)),
                'videos_published' => max(0, (int) ($row['videos_published'] ?? 0)),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->bulkUpsert($this->dailyTable, $payloads, [
            'views', 'engaged_views', 'watch_minutes', 'average_view_duration', 'average_view_percentage',
            'likes', 'comments', 'shares', 'subscribers_gained', 'subscribers_lost', 'videos_published', 'updated_at',
        ]);

        return count($payloads);
    }

    public function upsertVideos(array $rows): int
    {
        $payloads = [];
        $now = date('Y-m-d H:i:s');
        foreach ($rows as $row) {
            $videoId = $this->text($row['video_id'] ?? '', 64);
            $channelId = $this->text($row['channel_id'] ?? '', 120);
            if ($videoId === '' || $channelId === '') {
                continue;
            }
            $payloads[] = [
                'video_id' => $videoId,
                'channel_id' => $channelId,
                'title' => $this->text($row['title'] ?? '', 300),
                'description' => $this->textArea($row['description'] ?? '', 12000),
                'published_at' => $this->dateTime($row['published_at'] ?? ''),
                'thumbnail_url' => $this->text($row['thumbnail_url'] ?? '', 800),
                'permalink' => 'https://www.youtube.com/watch?v=' . rawurlencode($videoId),
                'duration_seconds' => max(0, (int) ($row['duration_seconds'] ?? 0)),
                'content_type' => $this->text($row['content_type'] ?? 'video', 40),
                'privacy_status' => $this->text($row['privacy_status'] ?? '', 40),
                'public_views' => max(0, (int) ($row['public_views'] ?? 0)),
                'public_likes' => max(0, (int) ($row['public_likes'] ?? 0)),
                'public_comments' => max(0, (int) ($row['public_comments'] ?? 0)),
                'views' => max(0, (int) ($row['views'] ?? 0)),
                'engaged_views' => max(0, (int) ($row['engaged_views'] ?? 0)),
                'watch_minutes' => max(0, (int) round((float) ($row['watch_minutes'] ?? 0))),
                'average_view_duration' => max(0, (float) ($row['average_view_duration'] ?? 0)),
                'average_view_percentage' => max(0, (float) ($row['average_view_percentage'] ?? 0)),
                'likes' => max(0, (int) ($row['likes'] ?? 0)),
                'comments' => max(0, (int) ($row['comments'] ?? 0)),
                'shares' => max(0, (int) ($row['shares'] ?? 0)),
                'subscribers_gained' => max(0, (int) ($row['subscribers_gained'] ?? 0)),
                'subscribers_lost' => max(0, (int) ($row['subscribers_lost'] ?? 0)),
                'analytics_from' => $this->date($row['analytics_from'] ?? ''),
                'analytics_to' => $this->date($row['analytics_to'] ?? ''),
                'metrics_json' => $this->json($row['metrics'] ?? []),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->bulkUpsert($this->videosTable, $payloads, [
            'channel_id', 'title', 'description', 'published_at', 'thumbnail_url', 'permalink', 'duration_seconds',
            'content_type', 'privacy_status', 'public_views', 'public_likes', 'public_comments', 'views', 'engaged_views',
            'watch_minutes', 'average_view_duration', 'average_view_percentage', 'likes', 'comments', 'shares',
            'subscribers_gained', 'subscribers_lost', 'analytics_from', 'analytics_to', 'metrics_json', 'updated_at',
        ]);

        return count($payloads);
    }

    public function replaceBreakdown(string $channelId, string $from, string $to, string $type, array $rows): int
    {
        Database::execute(
            "DELETE FROM {$this->breakdownsTable} WHERE channel_id = ? AND period_from = ? AND period_to = ? AND breakdown_type = ?",
            'ssss',
            [$channelId, $from, $to, $type]
        );

        $payloads = [];
        $now = date('Y-m-d H:i:s');
        foreach ($rows as $row) {
            $label = $this->text($row['label'] ?? '', 240);
            if ($label === '') {
                continue;
            }
            $payloads[] = [
                'channel_id' => $channelId,
                'period_from' => $from,
                'period_to' => $to,
                'breakdown_type' => $this->text($type, 64),
                'label' => $label,
                'secondary_label' => $this->text($row['secondary_label'] ?? '', 240),
                'value' => max(0, (float) ($row['value'] ?? 0)),
                'watch_minutes' => max(0, (float) ($row['watch_minutes'] ?? 0)),
                'metrics_json' => $this->json($row['metrics'] ?? []),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        $this->bulkUpsert($this->breakdownsTable, $payloads, ['secondary_label', 'value', 'watch_minutes', 'metrics_json', 'updated_at']);

        return count($payloads);
    }

    public function dashboard(string $from, string $to): array
    {
        $connection = $this->connection();
        $channelId = (string) ($connection['channel_id'] ?? '');
        $days = max(1, (int) ((strtotime($to) - strtotime($from)) / 86400) + 1);
        $previousTo = date('Y-m-d', strtotime($from . ' -1 day'));
        $previousFrom = date('Y-m-d', strtotime($previousTo . ' -' . ($days - 1) . ' days'));

        $daily = $channelId !== '' ? Database::rows(
            "SELECT * FROM {$this->dailyTable} WHERE channel_id = ? AND metric_date BETWEEN ? AND ? ORDER BY metric_date ASC",
            'sss',
            [$channelId, $from, $to]
        ) : [];
        $summary = $this->summary($channelId, $from, $to);
        $previous = $this->summary($channelId, $previousFrom, $previousTo);
        $videos = $channelId !== '' ? Database::rows(
            "SELECT * FROM {$this->videosTable}
              WHERE channel_id = ? AND DATE(published_at) BETWEEN ? AND ?
              ORDER BY published_at DESC, _ID DESC",
            'sss',
            [$channelId, $from, $to]
        ) : [];
        $topVideos = $videos;
        usort($topVideos, static fn (array $a, array $b): int => ((int) ($b['views'] ?: $b['public_views'])) <=> ((int) ($a['views'] ?: $a['public_views'])));
        $topVideos = array_slice($topVideos, 0, 8);

        $breakdowns = [];
        if ($channelId !== '') {
            foreach (Database::rows(
                "SELECT * FROM {$this->breakdownsTable}
                  WHERE channel_id = ? AND period_from = ? AND period_to = ?
                  ORDER BY breakdown_type ASC, value DESC",
                'sss',
                [$channelId, $from, $to]
            ) as $row) {
                $breakdowns[(string) $row['breakdown_type']][] = $row;
            }
        }

        $availableFrom = $daily ? (string) ($daily[0]['metric_date'] ?? '') : '';
        $availableTo = $daily ? (string) ($daily[count($daily) - 1]['metric_date'] ?? '') : '';
        $previousCoverage = $channelId === '' ? 0 : (int) Database::value(
            "SELECT COUNT(DISTINCT metric_date) FROM {$this->dailyTable} WHERE channel_id = ? AND metric_date BETWEEN ? AND ?",
            'sss',
            [$channelId, $previousFrom, $previousTo]
        );

        return [
            'connection' => $connection,
            'summary' => $summary,
            'previous' => $previous,
            'series' => $daily,
            'videos' => $videos,
            'topVideos' => $topVideos,
            'breakdowns' => $breakdowns,
            'hasLiveData' => $daily !== [] || $videos !== [],
            'period' => [
                'current' => ['from' => $from, 'to' => $to],
                'previous' => ['from' => $previousFrom, 'to' => $previousTo],
                'requested_days' => $days,
                'covered_days' => count($daily),
                'available_from' => $availableFrom,
                'available_to' => $availableTo,
                'comparison_ready' => $previousCoverage > 0,
            ],
        ];
    }

    private function summary(string $channelId, string $from, string $to): array
    {
        if ($channelId === '') {
            return $this->emptySummary();
        }
        $row = Database::one(
            "SELECT
                COALESCE(SUM(views), 0) views,
                COALESCE(SUM(engaged_views), 0) engaged_views,
                COALESCE(SUM(watch_minutes), 0) watch_minutes,
                COALESCE(SUM(average_view_duration * views) / NULLIF(SUM(views), 0), 0) average_view_duration,
                COALESCE(SUM(average_view_percentage * views) / NULLIF(SUM(views), 0), 0) average_view_percentage,
                COALESCE(SUM(likes), 0) likes,
                COALESCE(SUM(comments), 0) comments,
                COALESCE(SUM(shares), 0) shares,
                COALESCE(SUM(subscribers_gained), 0) subscribers_gained,
                COALESCE(SUM(subscribers_lost), 0) subscribers_lost,
                COALESCE(SUM(videos_published), 0) videos_published
             FROM {$this->dailyTable}
             WHERE channel_id = ? AND metric_date BETWEEN ? AND ?",
            'sss',
            [$channelId, $from, $to]
        );
        $row = array_merge($this->emptySummary(), $row);
        $row['subscribers_net'] = (int) $row['subscribers_gained'] - (int) $row['subscribers_lost'];
        $row['interactions'] = (int) $row['likes'] + (int) $row['comments'] + (int) $row['shares'];

        return $row;
    }

    private function emptySummary(): array
    {
        return [
            'views' => 0,
            'engaged_views' => 0,
            'watch_minutes' => 0,
            'average_view_duration' => 0,
            'average_view_percentage' => 0,
            'likes' => 0,
            'comments' => 0,
            'shares' => 0,
            'subscribers_gained' => 0,
            'subscribers_lost' => 0,
            'subscribers_net' => 0,
            'videos_published' => 0,
            'interactions' => 0,
        ];
    }

    private function ensureTables(): void
    {
        if (!Database::tableExists($this->connectionsTable)) {
            Database::execute(
                "CREATE TABLE {$this->connectionsTable} (
                    _ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    channel_id VARCHAR(120) NOT NULL,
                    channel_title VARCHAR(220) NOT NULL DEFAULT '',
                    channel_handle VARCHAR(180) NOT NULL DEFAULT '',
                    thumbnail_url VARCHAR(800) NOT NULL DEFAULT '',
                    uploads_playlist_id VARCHAR(120) NOT NULL DEFAULT '',
                    subscriber_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
                    view_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
                    video_count INT UNSIGNED NOT NULL DEFAULT 0,
                    access_token_enc MEDIUMTEXT NULL,
                    refresh_token_enc MEDIUMTEXT NULL,
                    token_expires_at DATETIME NULL,
                    scopes TEXT NULL,
                    connected TINYINT(1) NOT NULL DEFAULT 0,
                    last_sync DATETIME NULL,
                    last_error TEXT NULL,
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL,
                    PRIMARY KEY (_ID),
                    UNIQUE KEY channel_unique (channel_id),
                    KEY connected (connected)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        if (!Database::tableExists($this->dailyTable)) {
            Database::execute(
                "CREATE TABLE {$this->dailyTable} (
                    _ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    metric_date DATE NOT NULL,
                    channel_id VARCHAR(120) NOT NULL,
                    views BIGINT UNSIGNED NOT NULL DEFAULT 0,
                    engaged_views BIGINT UNSIGNED NOT NULL DEFAULT 0,
                    watch_minutes BIGINT UNSIGNED NOT NULL DEFAULT 0,
                    average_view_duration DECIMAL(12,3) NOT NULL DEFAULT 0,
                    average_view_percentage DECIMAL(10,4) NOT NULL DEFAULT 0,
                    likes BIGINT UNSIGNED NOT NULL DEFAULT 0,
                    comments BIGINT UNSIGNED NOT NULL DEFAULT 0,
                    shares BIGINT UNSIGNED NOT NULL DEFAULT 0,
                    subscribers_gained INT UNSIGNED NOT NULL DEFAULT 0,
                    subscribers_lost INT UNSIGNED NOT NULL DEFAULT 0,
                    videos_published INT UNSIGNED NOT NULL DEFAULT 0,
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL,
                    PRIMARY KEY (_ID),
                    UNIQUE KEY channel_day (channel_id, metric_date),
                    KEY metric_date (metric_date)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        if (!Database::tableExists($this->videosTable)) {
            Database::execute(
                "CREATE TABLE {$this->videosTable} (
                    _ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    video_id VARCHAR(64) NOT NULL,
                    channel_id VARCHAR(120) NOT NULL,
                    title VARCHAR(300) NOT NULL DEFAULT '',
                    description MEDIUMTEXT NULL,
                    published_at DATETIME NULL,
                    thumbnail_url VARCHAR(800) NOT NULL DEFAULT '',
                    permalink VARCHAR(800) NOT NULL DEFAULT '',
                    duration_seconds INT UNSIGNED NOT NULL DEFAULT 0,
                    content_type VARCHAR(40) NOT NULL DEFAULT 'video',
                    privacy_status VARCHAR(40) NOT NULL DEFAULT '',
                    public_views BIGINT UNSIGNED NOT NULL DEFAULT 0,
                    public_likes BIGINT UNSIGNED NOT NULL DEFAULT 0,
                    public_comments BIGINT UNSIGNED NOT NULL DEFAULT 0,
                    views BIGINT UNSIGNED NOT NULL DEFAULT 0,
                    engaged_views BIGINT UNSIGNED NOT NULL DEFAULT 0,
                    watch_minutes BIGINT UNSIGNED NOT NULL DEFAULT 0,
                    average_view_duration DECIMAL(12,3) NOT NULL DEFAULT 0,
                    average_view_percentage DECIMAL(10,4) NOT NULL DEFAULT 0,
                    likes BIGINT UNSIGNED NOT NULL DEFAULT 0,
                    comments BIGINT UNSIGNED NOT NULL DEFAULT 0,
                    shares BIGINT UNSIGNED NOT NULL DEFAULT 0,
                    subscribers_gained INT UNSIGNED NOT NULL DEFAULT 0,
                    subscribers_lost INT UNSIGNED NOT NULL DEFAULT 0,
                    analytics_from DATE NULL,
                    analytics_to DATE NULL,
                    metrics_json MEDIUMTEXT NULL,
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL,
                    PRIMARY KEY (_ID),
                    UNIQUE KEY video_unique (video_id),
                    KEY channel_published (channel_id, published_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        if (!Database::tableExists($this->breakdownsTable)) {
            Database::execute(
                "CREATE TABLE {$this->breakdownsTable} (
                    _ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    channel_id VARCHAR(120) NOT NULL,
                    period_from DATE NOT NULL,
                    period_to DATE NOT NULL,
                    breakdown_type VARCHAR(64) NOT NULL,
                    label VARCHAR(240) NOT NULL,
                    secondary_label VARCHAR(240) NOT NULL DEFAULT '',
                    value DECIMAL(18,4) NOT NULL DEFAULT 0,
                    watch_minutes DECIMAL(18,4) NOT NULL DEFAULT 0,
                    metrics_json MEDIUMTEXT NULL,
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL,
                    PRIMARY KEY (_ID),
                    UNIQUE KEY breakdown_unique (channel_id, period_from, period_to, breakdown_type, label),
                    KEY period_type (period_from, period_to, breakdown_type)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
    }

    private function bulkUpsert(string $table, array $rows, array $updateColumns): void
    {
        foreach (array_chunk($rows, 100) as $chunk) {
            if ($chunk === []) {
                continue;
            }
            $columns = array_keys($chunk[0]);
            $placeholder = '(' . implode(',', array_fill(0, count($columns), '?')) . ')';
            $params = [];
            foreach ($chunk as $row) {
                foreach ($columns as $column) {
                    $params[] = (string) ($row[$column] ?? '');
                }
            }
            $updates = array_map(static fn (string $column): string => "`{$column}` = VALUES(`{$column}`)", $updateColumns);
            $sql = 'INSERT INTO ' . $table . ' (`' . implode('`,`', $columns) . '`) VALUES '
                . implode(',', array_fill(0, count($chunk), $placeholder))
                . ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);
            if (!Database::execute($sql, str_repeat('s', count($params)), $params)) {
                throw new \RuntimeException('No fue posible guardar los datos sincronizados de YouTube.');
            }
        }
    }

    private function text(mixed $value, int $length): string
    {
        return mb_substr(trim((string) $value), 0, $length);
    }

    private function textArea(mixed $value, int $length = 65535): string
    {
        return mb_substr(trim((string) $value), 0, $length);
    }

    private function date(mixed $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', substr($value, 0, 10));
        return $date ? $date->format('Y-m-d') : '';
    }

    private function dateTime(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        try {
            return (new \DateTimeImmutable($value))->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    private function json(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }
}
